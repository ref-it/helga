<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\PlanController;
use App\Models\Plan;
use App\Models\Shift;
use Com\Tecnick\Pdf\Tcpdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Draws the printable shift plan directly onto the PDF page instead of
 * rendering an HTML template through a CSS layout engine.
 *
 * Why: the plan is a fixed grid (header, group heading, shift block, a table
 * of helper slots), and laying that out as HTML cost ~1.8ms per table cell -
 * a 100-shift plan took ~7s and 184MB, which blows the default 128MB
 * memory_limit somewhere above 60 shifts. Drawing the same grid costs a
 * fraction of that and the memory stays flat, because there is no frame tree.
 *
 * The two rich text fields are the exception: plan and shift descriptions come
 * from a flux:editor and may contain the tags listed in
 * DescriptionSanitizer::ALLOWED_HTML, so those keep going through the HTML
 * cell renderer. They are short and there are few of them, so the layout cost
 * stays bounded.
 *
 * All positions are in millimetres, matching the DIN 5008 margins the print
 * layout is designed around (see self::MARGIN_LEFT for the punch hole edge).
 */
final class PlanPdfRenderer
{
    /** Wide enough to punch holes without hitting the text (DIN 5008 Lochrand). */
    private const MARGIN_LEFT = 22.0;

    private const MARGIN_RIGHT = 15.0;

    private const MARGIN_TOP = 12.0;

    /**
     * Wide enough to hold the page number strip below the text area with air
     * on both sides of it - at 15mm the footer had to sit 7mm from the paper
     * edge, which is past where many printers stop printing.
     */
    private const MARGIN_BOTTOM = 18.0;

    /** Distance from the bottom paper edge to the bottom of the page number. */
    private const FOOTER_EDGE_GAP = 10.0;

    /** Generous row height so empty slots can be filled in by hand after printing. */
    private const ROW_MIN_HEIGHT = 10.0;

    private const CELL_PADDING = 2.0;

    /**
     * Font size of a helper's e-mail address and phone number, in pt. Smaller
     * than the name above them, because the name is what a reader scans for -
     * but not so small that a printed contact is hard to read out over the
     * phone, which is the one thing these two lines exist for.
     */
    private const CONTACT_FONT_SIZE = 9.0;

    /**
     * Air above each of those two lines, in mm. Without it the three lines
     * sit in one block and the eye has to pick the name out of it; with it
     * the name reads as the row's heading and the contacts as its detail.
     * Applied to measuring and drawing alike, like LINE_SPACING.
     */
    private const CONTACT_LINE_GAP = 1.0;

    /**
     * Padding below the column header labels, in mm. There is deliberately
     * none above: the table has no top edge, so any padding there merges with
     * the block gap above it and reads as a hole rather than as padding. The
     * label's own line box already leaves a little air over the capitals.
     */
    private const HEAD_PADDING_BOTTOM = 1.2;

    /**
     * Extra space between the wrapped lines of a text cell, in mm. Applied to
     * drawing and measuring alike - a value used for only one of the two would
     * make the row borders miss the text they enclose.
     */
    private const LINE_SPACING = 0.8;

    /**
     * Line height for the rich text fields, which go through the HTML cell
     * renderer and so cannot take LINE_SPACING. Measured to match it: for the
     * 10pt the descriptions are drawn at, LINE_SPACING of 0.8mm makes a
     * three-line block 13.42mm tall, and 1.34 puts the HTML block at 13.39mm.
     * The two are matched at that font size only - LINE_SPACING is absolute
     * and this is relative, so changing the description size needs a new
     * value here.
     */
    private const HTML_LINE_HEIGHT = '1.34';

    /**
     * Space after a text block, in mm. Has to stay clearly larger than the
     * gap LINE_SPACING leaves between the lines inside a block (0.8mm), or a
     * block boundary reads as just another wrapped line - which is what makes
     * the contact line run into the description above it.
     */
    private const PARAGRAPH_GAP = 3.5;

    /** Height the plan logo is drawn at, in mm. */
    private const LOGO_HEIGHT = 12.0;

    /** Resolution SVG logos are rasterised at, in dots per inch. */
    private const LOGO_DPI = 600;

    /** Grey shared by every rule in the document - table and headings alike. */
    private const RULE_COLOR = '#999999';

    /**
     * Space between two blocks of the shift list, in mm. A category heading
     * keeps the same distance to its first shift as the shifts keep to each
     * other: a category can hold several shifts, and a tighter gap would bind
     * only the first one and break the rhythm of the rest.
     */
    private const SHIFT_GAP = 7.0;

    /**
     * Index column ("1", "2", ...), in mm. Wide enough that a two-digit slot
     * number still fits inside the cell padding once right-aligned - at 6mm it
     * reached past the table's left edge.
     */
    private const COL_INDEX_WIDTH = 7.5;

    /**
     * Heading sizes in pt, one ladder so the steps stay visible next to each
     * other: plan title, category, shift title.
     *
     * The steps are wide enough to read as levels at a glance - category and
     * shift used to sit 1pt apart, which looked like the same heading twice.
     * The shift title is the bottom rung and cannot go below 12pt: helper
     * names in the table are 11pt, and a title level with the content it
     * labels reads as body text. So the room comes from stretching the ladder
     * upwards instead - in even 3pt steps, which keeps the plan title clearly
     * above the category without letting it dominate an A4 page.
     */
    private const HEADING_SIZE_PLAN = 18.0;

    private const HEADING_SIZE_CATEGORY = 15.0;

    private const HEADING_SIZE_SHIFT = 12.0;

    private const FONT = 'liberationsans';

    /**
     * TeX hyphenation pattern file per locale, relative to
     * resources/hyphenation. Without these, a long compound overflows its
     * column instead of breaking - and justified descriptions pull their word
     * spaces apart to almost twice the normal width to fill the line.
     */
    private const HYPHENATION_PATTERNS = [
        'de' => 'hyph-de-1996.tex',
        'en' => 'hyph-en-us.tex',
        'es' => 'hyph-es.tex',
    ];

    private Tcpdf $pdf;

    private float $pageWidth;

    private float $pageHeight;

    private float $contentWidth;

    /** Vertical cursor, in mm from the top of the current page. */
    private float $cursorY;

    /**
     * Hyphenation patterns for this render, so they can be switched off around
     * the fields that must not be hyphenated.
     *
     * @var array<string, int|string>
     */
    private array $hyphenPatterns = [];

    /**
     * Natural height of one line per font size, in mm. Keyed by size, because
     * every empty slot line asks for it and measuring a reference string each
     * time would cost a layout pass per line.
     *
     * @var array<string, float>
     */
    private array $lineHeightCache = [];

    private Collection $categoryNames;

    /**
     * Heading level for a shift title. Shifts sit under a category heading
     * when the plan uses categories, and directly under the plan title when
     * it does not - skipping a level would leave a gap in the outline that
     * assistive technology reports as a structural error.
     */
    private string $shiftHeadingRole = 'H3';

    public function render(Plan $plan, Collection $categoryNames): string
    {
        $this->categoryNames = $categoryNames;

        // 'pdfua2' turns on tagged output: every call below that writes text
        // attaches it to the open structure element, so the document carries a
        // real reading order and table semantics instead of loose glyphs.
        // PDF/UA-2 (ISO 14289-2) also raises the file to PDF 2.0 and adds the
        // PDF 2.0 structure namespace to the tag tree. The two parts are
        // mutually exclusive declarations, not a superset: a UA-2 file fails
        // veraPDF's ua1 profile on the file header (%PDF-2.0 instead of
        // %PDF-1.n) and on pdfuaid:part, even though all 104 substantive UA-1
        // rules still pass. EN 301 549 and BITV 2.0 name ISO 14289-1, so if a
        // procurement requirement ever asks for UA-1 by name, this is the one
        // line to change back.
        // Subsetting embeds only the glyphs actually used, which keeps the
        // output around 70KB instead of carrying the whole face.
        // allowedPaths replaces the library's own defaults rather than adding
        // to them, so the font directory has to be listed alongside the
        // hyphenation one or the faces stop being found
        $this->pdf = new Tcpdf('mm', true, true, true, 'pdfua2', null, [
            'allowedPaths' => [resource_path('fonts'), resource_path('hyphenation')],
        ]);
        $this->lineHeightCache = [];
        $this->pdf->setCreator('helga');

        // the title is what a screen reader announces for the document, and
        // PDF/UA mode makes the viewer show it instead of the file name
        $this->pdf->setTitle($plan->title);
        $this->pdf->setLanguage(str_replace('_', '-', app()->getLocale()));

        $this->hyphenPatterns = $this->hyphenationPatterns();
        $this->breakMode(true);

        $page = $this->pdf->addPage(['format' => 'A4']);
        $this->pageWidth = $page['width'];
        $this->pageHeight = $page['height'];
        $this->contentWidth = $this->pageWidth - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $this->cursorY = self::MARGIN_TOP;

        // No Document tag of our own: the library writes one unconditionally
        // under StructTreeRoot and parents every element that is nobody's
        // child to it, so opening a second one only nested the whole tree a
        // level deeper. Both are legal - PDF 2.0 permits Document inside
        // Document - but the library puts the PDF 2.0 structure namespace on
        // the outer one, which would leave the element that actually carries
        // the content in the default namespace.
        $this->drawPlanHeader($plan);
        $this->drawShifts($plan);

        // once every page exists, the total is known and the footer can be
        // stamped onto each one - no second render pass needed
        $this->drawPageNumbers();

        return $this->pdf->getOutPDFString();
    }

    /**
     * Hyphenation patterns for the current locale, or an empty array when the
     * locale has none. Parsing the pattern file costs ~35ms and its result is
     * the same for every export, so it is cached - keyed by the file's mtime,
     * so replacing a pattern file takes effect without clearing the cache.
     *
     * A missing file is not an error: the export still works, it just breaks
     * lines worse.
     *
     * @return array<string, int|string>
     */
    private function hyphenationPatterns(): array
    {
        $locale = strtolower(explode('_', str_replace('-', '_', app()->getLocale()))[0]);
        $file = self::HYPHENATION_PATTERNS[$locale] ?? null;

        if ($file === null) {
            return [];
        }

        $path = resource_path('hyphenation/'.$file);
        if (! is_file($path)) {
            return [];
        }

        return Cache::rememberForever(
            'pdf.hyphenation.'.$locale.'.'.filemtime($path),
            fn (): array => $this->pdf->loadTexHyphenPatterns($path),
        );
    }

    /**
     * Display name for a shift's category. A shift stores the category id in
     * its type column, so the id has to be resolved against the plan's
     * categories or the heading shows a bare number. An id with no matching
     * category falls back to the raw value, which makes stale data visible
     * instead of silently dropping the heading.
     *
     * @param  Collection<int|string, string>  $names
     */
    public function categoryLabel(string $type, Collection $names): string
    {
        return (string) ($names[$type] ?? $type);
    }

    private function drawPlanHeader(Plan $plan): void
    {
        $logoWidth = $this->drawLogo($plan);

        $this->font('B', self::HEADING_SIZE_PLAN);
        $this->bookmark($plan->title, 0);
        $this->beginTag('H1');
        $this->text($plan->title, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth - $logoWidth);
        $this->endTag();
        // A logo's ink fills its whole box, while a line of text stops at its
        // baseline and leaves air below on its own - so the same gap reads as
        // tighter under a logo, and it gets the wider block gap instead.
        $this->cursorY += max($this->lastHeight(), $logoWidth > 0 ? self::LOGO_HEIGHT : 0.0)
            + ($logoWidth > 0 ? self::SHIFT_GAP : 4.0);

        if ($plan->description) {
            $this->cursorY = $this->html($plan->description, 10) + self::PARAGRAPH_GAP;
        }

        $contact = $this->contactLine($plan);
        if ($contact !== null) {
            // #444 keeps the contrast ratio above the 4.5:1 that WCAG AA asks
            // for body text - a lighter grey would look right but fail it
            $label = __('plan.responsible').': ';

            // Label and values are one paragraph for a screen reader, but two
            // draw calls, because a text cell has a single weight. The values
            // start where the bold label ends.
            $this->beginTag('P');

            $this->font('B', 10);
            $labelWidth = $this->measureWidth($label);
            $this->text($label, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth, color: '#444444');
            $labelHeight = $this->lastHeight();

            $this->font('', 10);
            $this->text(
                $contact,
                self::MARGIN_LEFT + $labelWidth,
                $this->cursorY,
                $this->contentWidth - $labelWidth,
                color: '#444444',
            );

            $this->endTag();

            $this->cursorY += max($labelHeight, $this->lastHeight());
        }

        $this->cursorY += 5.0;
    }

    /**
     * Returns the horizontal space the logo occupies, so the title can be
     * wrapped short of it. 0 when the plan has no logo.
     */
    private function drawLogo(Plan $plan): float
    {
        if (! $plan->logo) {
            return 0.0;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($plan->logo)) {
            return 0.0;
        }

        $height = self::LOGO_HEIGHT;
        $raw = $disk->get($plan->logo);
        if ($raw === null) {
            return 0.0;
        }

        // A logo must never be able to break the export - it is decoration on
        // a document whose point is the shift list. Anything that cannot be
        // read or drawn is reported and skipped.
        try {
            if ($this->isSvg($raw)) {
                $raw = $this->rasterizeSvg($raw, $height);
            }

            // Taken after any rasterisation, because getimagesizefromstring
            // cannot be trusted on SVG: it returns false outright for files
            // whose width/height carry decimals, which is what silently lost
            // the logo before.
            $size = @getimagesizefromstring($raw);
            if ($size === false || $size[1] <= 0) {
                return 0.0;
            }

            $width = $height * ($size[0] / $size[1]);
            $content = $this->rasterLogoContent(
                $raw,
                $this->pageWidth - self::MARGIN_RIGHT - $width,
                $width,
                $height,
            );

            // a Figure without alternative text is the single most common
            // PDF/UA failure - the plan title is the only description we have
            // for a logo the owner uploaded, and it is the right one here
            $this->pdf->addTaggedFigureContent($content, $this->pdf->page->getPageId(), $plan->title);
        } catch (\Throwable $e) {
            report($e);

            return 0.0;
        }

        // plus the 8mm gutter the old .plan-logo-cell padding kept
        return $width + 8.0;
    }

    /**
     * SVG is detected from the content, not from a mime lookup: the stored
     * mime type comes from the upload and getimagesizefromstring is unreliable
     * here, while the root element is unambiguous.
     */
    private function isSvg(string $raw): bool
    {
        return str_contains(strtolower(substr($raw, 0, 1024)), '<svg');
    }

    /**
     * Uploaded logos are usually Inkscape SVGs, and the library's own SVG
     * parser silently paints nothing for several constructs those files use -
     * a clip path anywhere in the document combined with content inside a
     * group is enough. Rasterising at print resolution avoids relying on that
     * parser at all, and 600dpi over a 12mm box is well beyond what the print
     * can show.
     *
     * @throws \ImagickException when the SVG cannot be rasterised
     */
    private function rasterizeSvg(string $raw, float $height): string
    {
        $image = new \Imagick;
        $image->setBackgroundColor(new \ImagickPixel('transparent'));
        $image->readImageBlob($raw);
        $image->setImageFormat('png');
        $image->resizeImage(0, (int) round($height / 25.4 * self::LOGO_DPI), \Imagick::FILTER_LANCZOS, 1);

        return $image->getImageBlob();
    }

    private function rasterLogoContent(string $raw, float $posx, float $width, float $height): string
    {
        $iid = $this->pdf->image->add('@'.$raw);

        return $this->pdf->image->getSetImage(
            $iid,
            $posx,
            $this->cursorY,
            $width,
            $height,
            $this->pageHeight,
        );
    }

    /**
     * The contact values only - the "Kontakt:" label is drawn separately so it
     * can be bold while the values stay regular.
     */
    private function contactLine(Plan $plan): ?string
    {
        $parts = array_values(array_filter([
            $plan->contact_email,
            $plan->contact_phone,
        ], fn (?string $v): bool => ! empty($v)));

        if ($parts === []) {
            return null;
        }

        return implode(' | ', $parts);
    }

    private function drawShifts(Plan $plan): void
    {
        $shifts = $plan->shifts;

        foreach ($shifts as $index => $shift) {
            $previous = $index > 0 ? $shifts[$index - 1] : null;
            if (($previous === null || $previous->type !== $shift->type) && $shift->type !== '') {
                $this->drawGroupHeading($this->categoryLabel((string) $shift->type, $this->categoryNames));
            }

            // A shift is only one level deeper when a category heading (H2)
            // actually precedes it. Deciding this once per plan made every
            // shift an H3 as soon as any shift had a category - and since
            // uncategorised shifts sort first, such a plan opened with H1
            // followed by H3 and skipped a level (PDF/UA-1 clause 7.4.2).
            $this->shiftHeadingRole = $shift->type !== '' ? 'H3' : 'H2';

            $this->drawShift($shift);
        }
    }

    private function drawGroupHeading(string $name): void
    {
        // air above, but not at the very top of a fresh page, where it would
        // look like a stray gap
        $spacing = $this->cursorY > self::MARGIN_TOP ? 12.0 : 0.0;
        $this->ensureSpace($spacing + 16.0);
        $this->cursorY += $spacing;

        $this->font('B', self::HEADING_SIZE_CATEGORY);
        $labelWidth = $this->measureWidth($name);
        $height = $this->measure('Xg', $this->contentWidth);

        $this->bookmark($name, 1);
        $this->beginTag('H2');
        $this->text($name, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth);
        $this->endTag();

        // The rule starts where the label ends and runs out to the margin, so
        // the category reads as a section break without a filled band.
        $ruleStart = self::MARGIN_LEFT + $labelWidth + 3.0;
        $ruleEnd = $this->pageWidth - self::MARGIN_RIGHT;

        // a label wide enough to fill the line leaves no room for a rule
        if ($ruleEnd - $ruleStart > 5.0) {
            $ruleY = $this->cursorY + $this->capMiddleOffset($height);
            $this->artifact($this->pdf->graph->getLine(
                $ruleStart,
                $ruleY,
                $ruleEnd,
                $ruleY,
                ['lineWidth' => 0.3, 'lineColor' => self::RULE_COLOR],
            ));
        }

        $this->cursorY += $height + self::SHIFT_GAP;
    }

    private function drawShift(Shift $shift): void
    {
        // keep the heading with at least the table header and one slot row,
        // so a shift never starts at the very bottom of a page
        $this->ensureSpace(18.0 + self::ROW_MIN_HEIGHT);

        $this->drawShiftHeading($shift);

        if ($shift->description) {
            $this->cursorY = $this->html($shift->description, 10) + self::PARAGRAPH_GAP;
        }

        $this->drawHelperTable($shift);

        $this->cursorY += self::SHIFT_GAP;
    }

    private function drawShiftHeading(Shift $shift): void
    {
        $date = str_replace('<br>', "\n", PlanController::buildDateString($shift->start, $shift->end));

        $this->font('', 10);
        $dateHeight = $this->measure($date, $this->contentWidth * 0.45);

        $this->font('B', self::HEADING_SIZE_SHIFT);
        $titleHeight = $this->measure($shift->title, $this->contentWidth * 0.55);

        $height = max($titleHeight, $dateHeight);

        $this->font('B', self::HEADING_SIZE_SHIFT);
        // the outline mirrors the heading levels, so a plan without
        // categories does not indent its shifts under a level that is not there
        $this->bookmark($shift->title, $this->shiftHeadingRole === 'H3' ? 2 : 1);
        $this->beginTag($this->shiftHeadingRole);
        $this->text($shift->title, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth * 0.55);
        $this->endTag();

        // the date sits beside the title visually, but it reads after it
        $this->font('', 10);
        $this->beginTag('P');
        $this->text(
            $date,
            self::MARGIN_LEFT + $this->contentWidth * 0.55,
            $this->cursorY,
            $this->contentWidth * 0.45,
            halign: 'R',
        );
        $this->endTag();

        $this->cursorY += $height;

        $this->cursorY += 1.0;
        $this->rule($this->cursorY, 0.18, self::RULE_COLOR);
        $this->cursorY += 2.0;

        // below the rule, so it sits with the shift's details rather than
        // with its title
        if ($shift->requires_health_certificate) {
            $this->drawHealthCertificateBadge();
        }
    }

    /**
     * Outlined label marking a shift that requires a health certificate.
     */
    private function drawHealthCertificateBadge(): void
    {
        $this->font('B', 8);
        $label = __('shift.healthCertificateRequired');
        $width = $this->measureWidth($label) + 3.0;
        $height = $this->measure($label, $width) + 1.0;

        $this->pushStyle(['lineWidth' => 0.18, 'lineColor' => '#666666']);
        $this->artifact($this->pdf->graph->getBasicRect(
            self::MARGIN_LEFT,
            $this->cursorY,
            $width,
            $height,
            'D',
        ));
        $this->popStyle();

        // the box is decoration, but the requirement itself has to reach a
        // screen reader as ordinary text
        $this->beginTag('P');
        $this->text($label, self::MARGIN_LEFT + 1.5, $this->cursorY + 0.5, $width, color: '#666666');
        $this->endTag();

        $this->cursorY += $height + 2.0;
    }

    private function drawHelperTable(Shift $shift): void
    {
        $columns = $this->columnLayout($shift);
        $slots = max($shift->team_size, $shift->subscriptions->count());

        $this->beginTag('Table');

        // The head is only drawn once the row that follows it is known to fit
        // below it on the same page. Drawing it up front stranded it at the
        // foot of a page whenever the first row had to break to the next one,
        // where the head was then repeated - a column heading with nothing
        // under it, which reads as an empty table.
        $headPending = true;

        for ($slot = 0; $slot < $slots; $slot++) {
            $subscription = $shift->subscriptions[$slot] ?? null;

            $index = (string) ($slot + 1);
            $size = $subscription->clothing_size ?? '';
            $comment = $subscription->comment ?? '';

            // Name, e-mail and phone are always three stacked lines, whether
            // or not the slot is taken and whether or not a value exists: the
            // printout is filled in by hand, so the lines have to be there to
            // write on.
            $stack = [
                [$subscription->name ?? '', 11.0, true, 0.0],
                [$subscription->email ?? '', self::CONTACT_FONT_SIZE, false, self::CONTACT_LINE_GAP],
                [$subscription->phone ?? '', self::CONTACT_FONT_SIZE, false, self::CONTACT_LINE_GAP],
            ];

            $nameWidth = $columns['name']['width'] - 2 * self::CELL_PADDING;

            // A line with no value still takes up a normal line of its size,
            // so a row is the same height whether or not the slot is taken and
            // there is a line to write each value on. Measured once and reused
            // for drawing, so the lines and the borders cannot drift apart.
            $lineHeights = [];
            foreach ($stack as [$text, $fontSize, $hyphenate, $gap]) {
                $this->font('', $fontSize);
                $this->breakMode($hyphenate);
                $lineHeights[] = $gap + ($text === ''
                    ? $this->lineHeight($fontSize)
                    : $this->measure($text, $nameWidth));
            }
            $this->breakMode(true);

            // The index and clothing size are always a single short token and
            // can never drive the row height, so they are not measured.
            $this->font('', 11);
            $commentHeight = $this->measure($comment, $columns['comment']['width'] - 2 * self::CELL_PADDING);

            $rowHeight = max(
                self::ROW_MIN_HEIGHT,
                max(array_sum($lineHeights), $commentHeight) + 2 * self::CELL_PADDING,
            );

            if (! $this->hasSpace(($headPending ? $this->tableHeadHeight() : 0.0) + $rowHeight)) {
                $this->newPage();
                $headPending = true;
            }

            if ($headPending) {
                $this->drawTableHead($columns);
                $headPending = false;
            }

            $top = $this->cursorY;
            $textY = $top + self::CELL_PADDING;

            // Cells are drawn in reading order and each one is bracketed, so
            // the row reads "1, Jane Doe jane@example.com, L, comment" rather
            // than as four unrelated runs of text. Empty slots keep their
            // cells (required) so a reader can still count the free places.
            $this->beginTag('TR');

            $this->font('', 11);
            $this->beginTag('TD', null, [], true);
            // Right-aligned, ending a cell padding short of the column rule:
            // left-aligned in the full column it sat flush against the table
            // edge with no padding at all, while the leftover of the 6mm
            // column looked like an oversized padding on the right. Aligning
            // right puts both gaps at the padding the other cells use, and
            // two-digit numbers grow leftwards into the free space.
            $this->text(
                $index,
                $columns['index']['x'],
                $textY,
                self::COL_INDEX_WIDTH - self::CELL_PADDING,
                halign: 'R',
                color: '#666666',
            );
            $this->endTag();

            // name, e-mail and phone share one cell - they describe the same
            // person, so they are not columns of their own
            $this->beginTag('TD', null, [], true);
            $lineY = $textY;
            foreach ($stack as $line => [$text, $fontSize, $hyphenate, $gap]) {
                if ($text !== '') {
                    $this->font('', $fontSize);
                    $this->breakMode($hyphenate);
                    $this->text($text, $columns['name']['x'] + self::CELL_PADDING, $lineY + $gap, $nameWidth);
                }

                $lineY += $lineHeights[$line];
            }
            $this->breakMode(true);
            $this->endTag();

            // the stack above left the font on its last line's size, so the
            // remaining cells have to select their own again
            $this->font('', 11);

            if (isset($columns['size'])) {
                $this->beginTag('TD', null, [], true);
                $this->text($size, $columns['size']['x'] + self::CELL_PADDING, $textY, $columns['size']['width'] - 2 * self::CELL_PADDING);
                $this->endTag();
            }

            $this->beginTag('TD', null, [], true);
            $this->text($comment, $columns['comment']['x'] + self::CELL_PADDING, $textY, $columns['comment']['width'] - 2 * self::CELL_PADDING);
            $this->endTag();

            $this->endTag();

            $this->cursorY = $top + $rowHeight;
            $this->rule($this->cursorY, 0.1, self::RULE_COLOR);
            $this->columnRules($columns, $top, $this->cursorY);
        }

        // A shift with no slots and no helpers has no row to wait for, but the
        // table still needs its head - both so the columns are named and so
        // the element is not an empty Table in the tag tree.
        if ($headPending) {
            $this->ensureSpace($this->tableHeadHeight());
            $this->drawTableHead($columns);
        }

        $this->endTag();
    }

    /**
     * Column geometry for one shift. The clothing size column only exists when
     * the shift asks for it, exactly as the old template's @if did.
     *
     * @return array<string, array{x: float, width: float, label: string}>
     */
    private function columnLayout(Shift $shift): array
    {
        $left = self::MARGIN_LEFT;
        $nameWidth = $this->contentWidth * 0.45;
        $sizeWidth = $shift->requires_clothing_size ? 12.0 : 0.0;

        $columns = [
            'index' => ['x' => $left, 'width' => self::COL_INDEX_WIDTH, 'label' => ''],
            'name' => ['x' => $left + self::COL_INDEX_WIDTH, 'width' => $nameWidth, 'label' => __('subscription.name')],
        ];

        $next = $left + self::COL_INDEX_WIDTH + $nameWidth;

        if ($shift->requires_clothing_size) {
            $columns['size'] = ['x' => $next, 'width' => $sizeWidth, 'label' => __('subscription.clothingSizeAbbr')];
            $next += $sizeWidth;
        }

        $columns['comment'] = [
            'x' => $next,
            'width' => $left + $this->contentWidth - $next,
            'label' => __('subscription.comment'),
        ];

        return $columns;
    }

    /**
     * @param  array<string, array{x: float, width: float, label: string}>  $columns
     */
    /**
     * Height the table head takes, without drawing it - needed to decide
     * whether head and first row still fit on the current page together.
     */
    private function tableHeadHeight(): float
    {
        $this->font('B', 10);

        return $this->measure('Xg', $this->contentWidth) + self::HEAD_PADDING_BOTTOM;
    }

    private function drawTableHead(array $columns): void
    {
        $top = $this->cursorY;
        $height = $this->tableHeadHeight();

        $this->font('B', 10);

        $this->beginTag('TR');
        foreach ($columns as $column) {
            // Scope tells a screen reader that this cell labels its whole
            // column, which is what lets it announce "Name: Jane Doe" when the
            // reader lands on a body cell. Empty cells stay in the tree
            // (required) so the column count matches every body row.
            //
            // Scope belongs to the Table attribute owner (ISO 32000-1 table
            // 349), so /O has to be declared alongside it - the library writes
            // the pairs into /A verbatim and adds no owner of its own, and
            // without one the attribute is not read as a table attribute at
            // all: veraPDF then reports the column headers as not
            // determinable (PDF/UA-1 clause 7.5).
            $this->beginTag('TH', null, ['O' => 'Table', 'Scope' => 'Column'], true);

            if ($column['label'] !== '') {
                $this->text(
                    $column['label'],
                    $column['x'] + self::CELL_PADDING,
                    $top,
                    $column['width'] - self::CELL_PADDING,
                    color: '#555555',
                );
            }

            $this->endTag();
        }
        $this->endTag();

        $this->cursorY = $top + $height;
        $this->rule($this->cursorY, 0.18, self::RULE_COLOR);
        $this->columnRules($columns, $top, $this->cursorY);
    }

    /**
     * Vertical separators between columns. The last column has no right rule,
     * matching table.helpers td:last-child in the old stylesheet.
     *
     * @param  array<string, array{x: float, width: float, label: string}>  $columns
     */
    private function columnRules(array $columns, float $top, float $bottom): void
    {
        $keys = array_keys($columns);
        array_shift($keys); // the index column's left edge is the table edge

        foreach ($keys as $key) {
            $this->artifact($this->pdf->graph->getLine(
                $columns[$key]['x'],
                $top,
                $columns[$key]['x'],
                $bottom,
                ['lineWidth' => 0.1, 'lineColor' => self::RULE_COLOR],
            ));
        }
    }

    private function rule(float $y, float $width, string $color): void
    {
        $this->artifact($this->pdf->graph->getLine(
            self::MARGIN_LEFT,
            $y,
            $this->pageWidth - self::MARGIN_RIGHT,
            $y,
            ['lineWidth' => $width, 'lineColor' => $color],
        ));
    }

    private function drawPageNumbers(): void
    {
        $pages = $this->pdf->page->getPages();
        $total = count($pages);

        $this->font('', 10);

        $number = 0;
        foreach ($pages as $page) {
            $pid = (int) $page['pid'];
            $this->pdf->setCurrentPage($pid);
            $number++;

            // Pagination is page furniture, not document content: marking it
            // as an artifact keeps "3 / 68" out of the reading order instead
            // of interrupting the last table row on every page.
            $this->pushStyle(['fillColor' => '#666666']);
            // Anchored to the paper edge rather than to the text area, so the
            // gap that matters for printing cannot drift when the margin
            // changes.
            $content = $this->pdf->getTextCell(
                $number.' / '.$total,
                self::MARGIN_LEFT,
                $this->pageHeight - self::FOOTER_EDGE_GAP - $this->lineHeight(10),
                $this->contentWidth,
                halign: 'R',
                drawcell: false,
            );
            $this->pdf->addArtifactContent($content, $pid, 'Pagination', 'Footer');
            $this->popStyle();
        }
    }

    private function hasSpace(float $need): bool
    {
        return $this->cursorY + $need <= $this->pageHeight - self::MARGIN_BOTTOM;
    }

    private function ensureSpace(float $need): void
    {
        if (! $this->hasSpace($need)) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        $this->pdf->addPage(['format' => 'A4']);
        $this->cursorY = self::MARGIN_TOP;
    }

    private function font(string $style, float $size): void
    {
        $this->pdf->font->insert($this->pdf->pon, self::FONT, $style, $size);
    }

    private function text(
        string $txt,
        float $x,
        float $y,
        float $width,
        string $halign = 'L',
        string $color = '#000000',
    ): void {
        if ($txt === '') {
            return;
        }

        // addTextCellXY takes the glyph colour from the current graphics
        // style, not from its own $styles argument (see Text::setPageContext),
        // so it has to be pushed onto the style stack around the call
        $this->pushStyle(['fillColor' => $color]);
        $this->pdf->addTextCellXY(
            $txt,
            posx: $x,
            posy: $y,
            width: $width,
            linespace: self::LINE_SPACING,
            halign: $halign,
            drawcell: false,
        );
        $this->popStyle();
    }

    /**
     * Opens a structure element. Everything drawn until the matching endTag()
     * is attached to it, which is what gives the document its reading order.
     *
     * @param  array<string, string|int>  $attr
     */
    private function beginTag(string $role, ?string $alt = null, array $attr = [], bool $required = false): void
    {
        $this->pdf->beginStructElem($role, $this->pdf->page->getPageId(), $alt, $attr, $required);
    }

    private function endTag(): void
    {
        $this->pdf->endStructElem();
    }

    /**
     * Adds purely visual content - rules, separators, the shaded heading band.
     * In a tagged document every mark has to be either meaningful or declared
     * an artifact, or assistive technology tries to read the decoration.
     */
    private function artifact(string $content): void
    {
        $this->pdf->addArtifactContent($content, $this->pdf->page->getPageId());
    }

    /**
     * @param  array<string, string|float>  $style
     */
    private function pushStyle(array $style): void
    {
        $this->pdf->page->addContent($this->pdf->graph->add($style));
    }

    private function popStyle(): void
    {
        $this->pdf->page->addContent($this->pdf->graph->pop());
    }

    /** Height the text would need in the given width, without drawing it. */
    private function measure(string $txt, float $width): float
    {
        if ($txt === '') {
            return 0.0;
        }

        $this->pdf->getTextCell($txt, 0, 0, $width, 0, linespace: self::LINE_SPACING, drawcell: false);

        return $this->pdf->getLastCellBBox()['h'];
    }

    /**
     * Adds an entry to the document outline - the bookmark tree a viewer shows
     * in its sidebar. This is a separate PDF feature from the structure tree:
     * the H1/H2/H3 elements carry the semantics for assistive technology, but
     * a viewer's outline panel reads /Outlines and stays empty without it.
     */
    private function bookmark(string $name, int $level): void
    {
        $this->pdf->setBookmark($name, '', $level, -1, self::MARGIN_LEFT, $this->cursorY);
    }

    /**
     * Chooses how the text drawn next may be broken.
     *
     * Words get hyphenated; e-mail addresses and phone numbers must not be,
     * because a hyphen inserted into them cannot be told apart from one that
     * belongs to the value when read back from paper. They instead get
     * zero-width break opportunities after their separators (. - @), which
     * keeps them inside their column without adding a character.
     */
    private function breakMode(bool $hyphenate, bool $zeroWidth = true): void
    {
        $this->pdf->setTexHyphenPatterns($hyphenate ? $this->hyphenPatterns : []);
        $this->pdf->enableZeroWidthBreakPoints(! $hyphenate && $zeroWidth);
    }

    /** Height of a single line at the given font size. */
    private function lineHeight(float $fontSize): float
    {
        $key = (string) $fontSize;

        if (! isset($this->lineHeightCache[$key])) {
            $this->font('', $fontSize);
            $this->lineHeightCache[$key] = $this->measure('X', $this->contentWidth);
        }

        return $this->lineHeightCache[$key];
    }

    /**
     * How far below the top of a line box the optical middle of the capitals
     * sits, in mm: the baseline is one ascent down, and the middle of a
     * capital half a cap height back up from there.
     *
     * Derived from the metrics of the currently selected font instead of kept
     * as a tuned fraction, so it follows the heading sizes on its own. It
     * comes out at 0.502 of the line box for every size; the fixed 0.45 it
     * replaces was set by eye and left the rule 0.23mm above the middle at
     * the category's old 13pt, and 0.34mm at 15pt, where it showed.
     */
    private function capMiddleOffset(float $lineHeight): float
    {
        $metrics = $this->pdf->font->getCurrentFont();

        return $lineHeight * ($metrics['ascent'] - $metrics['capheight'] / 2) / $metrics['height'];
    }

    private function measureWidth(string $txt): float
    {
        $this->pdf->getTextCell($txt, 0, 0, $this->contentWidth, 0, linespace: self::LINE_SPACING, drawcell: false);

        return $this->pdf->getLastTextBBox()['w'];
    }

    private function lastHeight(): float
    {
        return $this->pdf->getLastCellBBox()['h'];
    }

    /**
     * Renders one of the sanitized rich text fields and returns the y position
     * just below it, so the caller can carry on flowing content.
     */
    private function html(string $html, float $size): float
    {
        $this->font('', $size);
        $this->ensureSpace(8.0);

        // addHTMLCell() creates a structure element for every HTML block
        // element it renders, but writes bare text as loose marked content -
        // which would hang directly off the enclosing Document, and a grouping
        // element may not hold content items (PDF/UA-2, Table 5). Descriptions
        // saved before the rich-text editor are plain text, so those get a
        // paragraph of their own. Doing it here rather than wrapping the whole
        // cell in a P tag keeps content that already brings its own blocks
        // from ending up as a P inside a P, or a list inside a P.
        $wrapper = preg_match('#<(?:p|ul|ol|blockquote)[\s/>]#i', $html) === 1 ? 'div' : 'p';

        // The wrapper is ours, not user input - the field content itself is
        // already restricted to DescriptionSanitizer::ALLOWED_HTML. Descriptions
        // are set justified; the table around them is the document's grid, and
        // a ragged right edge beside it reads as unfinished.
        $wrapped = '<'.$wrapper.' style="line-height: '.self::HTML_LINE_HEIGHT.'; text-align: justify">'
            .$html.'</'.$wrapper.'>';

        $this->pdf->addHTMLCell($wrapped, posx: self::MARGIN_LEFT, posy: $this->cursorY, width: $this->contentWidth);

        $box = $this->pdf->getLastBBox();

        return $box['y'] + $box['h'];
    }
}
