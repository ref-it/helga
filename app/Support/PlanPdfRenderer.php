<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Controllers\PlanController;
use App\Models\Plan;
use App\Models\Shift;
use App\Models\Subscription;
use Com\Tecnick\Pdf\Tcpdf;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
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
    /**
     * Base unit of the vertical rhythm, in mm - 5pt. Every gap between blocks
     * is a whole multiple of it, so the spacings relate to each other instead
     * of each having been tuned on its own. Two of them already were multiples
     * before this was named: the paragraph gap is 2 units, the gap between
     * shifts 4.
     *
     * Spacing inside a line or a cell is deliberately off this grid -
     * LINE_SPACING, HEAD_PADDING_BOTTOM, CELL_PADDING and the hairline under a
     * shift title are measured against the type, not against the rhythm of
     * the blocks.
     *
     * Headings take more space above than below, so that a heading binds to
     * what follows it rather than floating between two blocks. How much less
     * below depends on whether something else already separates the heading
     * from its content: a shift title has a rule under it and needs one unit,
     * a category has its rule beside it and needs the full block gap.
     */
    private const SPACE = 1.75;

    /**
     * The page frame repeats the proportion of the sheet: A4 is 1:sqrt(2), and
     * so is each pair of opposing margins. Two of the four are fixed by
     * something outside typography - the left one by the hole punch, the
     * bottom one by the footer - and the other two follow from them.
     *
     * Unlike the block spacings this is deliberately off the SPACE grid: a
     * margin is a proportion, set once per page, while gaps add up down the
     * page and have to stay commensurable. The frame and the rhythm inside it
     * are two different jobs.
     *
     * The text block itself cannot also be 1:sqrt(2). At the 172.4mm width
     * these margins leave, it would need 243.9mm of height, so 53.1mm of
     * vertical margin instead of 29.9mm - far too much paper to give up on a
     * form that gets filled in by hand.
     */
    private const SQRT2 = 1.4142135623730951;

    /** Wide enough to punch holes without hitting the text (DIN 5008 Lochrand). */
    private const MARGIN_LEFT = 22.0;

    private const MARGIN_RIGHT = self::MARGIN_LEFT / self::SQRT2;

    private const MARGIN_TOP = self::MARGIN_BOTTOM / self::SQRT2;

    /**
     * Wide enough to hold the page number strip below the text area with air
     * on both sides of it - at 15mm the footer had to sit 7mm from the paper
     * edge, which is past where many printers stop printing.
     */
    private const MARGIN_BOTTOM = 10 * self::SPACE;

    /** Distance from the bottom paper edge to the bottom of the page number. */
    private const FOOTER_EDGE_GAP = 10.0;

    /** Generous row height so empty slots can be filled in by hand after printing. */
    private const ROW_MIN_HEIGHT = 6 * self::SPACE;

    private const CELL_PADDING = 2.0;

    /**
     * Font size of the helper table, in pt - the column labels and the body
     * cells alike. One size for both keeps a row from reading louder than the
     * head above it, and it is the single place to change the table's scale:
     * the head is the reference the cells are meant to match.
     */
    private const TABLE_FONT_SIZE = 10.0;

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
     * three-line block 14.41mm tall, and 1.44 puts the HTML block at 14.43mm.
     * The two are matched at that font size and in FONT_SANS only -
     * LINE_SPACING is absolute and this is relative, so changing the
     * description size or the family needs a new value here. It was 1.34 for
     * Liberation Sans.
     */
    private const HTML_LINE_HEIGHT = '1.44';

    /**
     * Space after a text block, in mm. Has to stay clearly larger than the
     * gap LINE_SPACING leaves between the lines inside a block (0.8mm), or a
     * block boundary reads as just another wrapped line - which is what makes
     * the contact line run into the description above it.
     */
    private const PARAGRAPH_GAP = 2 * self::SPACE;

    /** Height the plan logo is drawn at, in mm. */
    private const LOGO_HEIGHT = 12.0;

    /** Resolution SVG logos are rasterised at, in dots per inch. */
    private const LOGO_DPI = 600;

    /** Grey shared by every rule in the document - table and headings alike. */
    /**
     * The palette, taken from the Tailwind theme the rest of the application
     * is built on, so that a plan on screen and the same plan on paper are
     * recognisably one document. sky-700 is the accent the interface uses
     * (see resources/css/theme.css).
     *
     * Tailwind 4 keeps its palette in oklch, which a PDF cannot carry: these
     * are the sRGB values of those entries. Regenerating them means reading
     * node_modules/tailwindcss/theme.css and converting - they are not
     * hand-picked approximations.
     */
    private const COLOR_BLACK = '#000000';

    private const COLOR_SKY_700 = '#0069a8';

    private const COLOR_AMBER_800 = '#973c00';

    private const COLOR_ZINC_400 = '#9f9fa9';

    private const COLOR_ZINC_500 = '#71717b';

    private const COLOR_ZINC_600 = '#52525c';

    private const COLOR_ZINC_700 = '#3f3f46';

    private const RULE_COLOR = self::COLOR_ZINC_400;

    /**
     * Width of the bar beside a block quote, in mm. Heavier than the hairlines
     * that separate the blocks of the document, because this one marks a span
     * of text rather than a boundary between two.
     */
    private const QUOTE_RULE_WIDTH = 0.6;

    /** Font size of the health certificate badge, in pt. */
    private const BADGE_FONT_SIZE = 8.0;

    /**
     * Padding inside the health certificate badge, in mm - one unit beside
     * the text, two thirds of one above and below it. A 3:2 ratio: the badge
     * stays a compact strip that does not pull the eye off the shift it
     * belongs to, while the type no longer touches the frame.
     *
     * Sideways it measures wider than it reads, because the side bearings of
     * the outer glyphs add to it - 1.95mm of visible air for the 1.75mm set
     * here.
     *
     * The vertical one is measured against the type rather than against the
     * line box, which carries leading above the capitals and below the
     * descenders: padding added to that came out smaller than it measured,
     * and unevenly, because whether the label has a descender decides where
     * its ink stops. It did - 1.14mm above the capitals against 0.68mm below
     * the "p" of "Gesundheitspass".
     */
    private const BADGE_PADDING_X = self::SPACE;

    private const BADGE_PADDING_Y = 2 * self::SPACE / 3;

    /**
     * Corner radius of the health certificate badge, in mm. The drawing
     * routine clamps it to half the shorter side, so half the badge height
     * turns it into a pill.
     */
    private const BADGE_RADIUS = self::SPACE / 2;

    /**
     * Colour of the health certificate badge, type and border alike. Amber
     * reads as a precondition to meet, where red would read as a prohibition
     * or an error, and the badge states a requirement for the shift.
     *
     * It carries 7.09:1 against the paper, above the 4.5:1 WCAG AA asks of
     * body text - and because that ratio is computed from luminance, it
     * survives a greyscale print. The colour is a second cue and never the
     * only one: the badge says what it means in words, so a reader who does
     * not see the difference loses nothing (WCAG 1.4.1).
     *
     * The box is left unfilled: a tint would carry further across the page
     * than this one notice deserves, and it would cost toner on a sheet whose
     * purpose is to be printed.
     */
    private const BADGE_COLOR = self::COLOR_AMBER_800;

    /**
     * Extra air above and below the health certificate badge, in mm, on top
     * of the gap the shift's rule already leaves. The badge is a boxed element
     * between two runs of text - the rule above it, the description below -
     * and its border needs to stand clear of both, or the box reads as
     * attached to whichever line it sits nearer.
     */
    private const BADGE_GAP = self::SPACE;

    /**
     * Space before a shift heading, in mm - and so between two blocks of the
     * shift list. A category heading keeps the same distance to its first
     * shift as the shifts keep to each other: a category can hold several
     * shifts, and a tighter gap would bind only the first one and break the
     * rhythm of the rest. Both now go through the same leading gap in
     * drawShift(), rather than one being a trailing gap somewhere else.
     */
    private const SHIFT_GAP = 4 * self::SPACE;

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

    /**
     * The two families the document is set in. Headings take the serif, so
     * they read as a different voice from the content rather than only a
     * larger size of it; everything else - descriptions, the helper table and
     * above all the 9pt contact lines - stays sans, where the disambiguation
     * of l/1/I and 0/O in e-mail addresses matters more than the character of
     * the face.
     *
     * Both are SIL OFL 1.1. See resources/fonts/README.md for how the
     * definitions are generated - Adwaita Sans in particular ships only as a
     * variable font and has to be instanced first.
     */
    /**
     * Indent of a list item or a block quote inside a description, in mm.
     * Four units, the same distance the shift blocks keep from each other, so
     * an indented run reads as one step in rather than as its own column.
     */
    private const RICH_INDENT = 4 * self::SPACE;

    /**
     * Indent of a block quote, in mm - two units, half of what a list item
     * takes. A list needs room for its marker beside the text; a quote only
     * has to clear its bar, and at the full four units the text drifted away
     * from the bar it belongs to.
     */
    private const QUOTE_INDENT = 2 * self::SPACE;

    /**
     * How far a word gap may be stretched to justify a line, as a multiple of
     * its natural width. Beyond that the line is left ragged: a line that had
     * to break early - because the next word is a URL too wide to share it -
     * would otherwise take the whole remainder into two or three gaps and
     * read far worse than an uneven right edge.
     */
    private const RICH_MAX_STRETCH = 2.0;

    /**
     * Colour of a link's text, wherever one appears - in a description, in the
     * helper table and in the plan's contact line.
     *
     * It carries 5.85:1 against the paper, so it clears the 4.5:1 WCAG AA asks
     * of body text, and 3.59:1 against the black around it, which is what
     * WCAG wants where a link inside running text is told apart by colour
     * (technique G183 asks for 3:1). The grey this replaced managed only
     * 2.16:1 against the text, so the change is an improvement on that count.
     */
    private const LINK_COLOR = self::COLOR_SKY_700;

    private const FONT_SANS = 'adwaitasans';

    private const FONT_SERIF = 'merriweather';

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

    /** Counts the lists in a description, so consecutive items share one L element. */
    private int $richListId = 0;

    /** Counts the block quotes in a description, so their blocks share one BlockQuote element. */
    private int $richQuoteId = 0;

    /** @var array<string, float> Space width per style and size, in mm. */
    private array $richSpaceWidths = [];

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
        $this->pdf->setCreator('HELGA');

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

        $this->font('B', self::HEADING_SIZE_PLAN, self::FONT_SERIF);
        $this->bookmark($plan->title, 0);
        $this->beginTag('H1');
        $this->text($plan->title, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth - $logoWidth);
        $this->endTag();
        // A logo's ink fills its whole box, while a line of text stops at its
        // baseline and leaves air below on its own - so the same gap reads as
        // tighter under a logo, and it gets the wider block gap instead.
        $this->cursorY += max($this->lastHeight(), $logoWidth > 0 ? self::LOGO_HEIGHT : 0.0)
            + ($logoWidth > 0 ? self::SHIFT_GAP : self::PARAGRAPH_GAP);

        if ($plan->description) {
            $this->cursorY = $this->html($plan->description, 10) + self::PARAGRAPH_GAP;
        }

        $contacts = $this->contactValues($plan);
        if ($contacts !== []) {
            // zinc-700 carries 10.44:1 against the paper, well above the
            // 4.5:1 WCAG AA asks for body text - a lighter step would look
            // right and come closer to failing it
            $label = __('plan.responsible').': ';
            $separator = ' | ';

            // Label and values are one paragraph for a screen reader, but
            // several draw calls: a text cell has a single weight and a single
            // colour, and each value carries its own link.
            $this->beginTag('P');

            $this->font('B', 10);
            $labelWidth = $this->measureWidth($label);
            $this->text($label, self::MARGIN_LEFT, $this->cursorY, $this->contentWidth, color: self::COLOR_ZINC_700);
            $labelHeight = $this->lastHeight();

            $this->font('', 10);
            $x = self::MARGIN_LEFT + $labelWidth;
            $separatorWidth = $this->measureWidth($separator);

            foreach ($contacts as $index => $value) {
                if ($index > 0) {
                    $this->text($separator, $x, $this->cursorY, $separatorWidth, color: self::COLOR_ZINC_700);
                    $x += $separatorWidth;
                }

                $href = self::contactLink($value);
                $width = $this->measureWidth($value);
                $this->text(
                    $value,
                    $x,
                    $this->cursorY,
                    $width,
                    color: $href !== '' ? self::LINK_COLOR : self::COLOR_ZINC_700,
                );

                if ($href !== '') {
                    $this->linkArea($href, $x, $this->cursorY, $width, 10.0);
                }

                $x += $width;
            }

            $this->endTag();

            $this->cursorY += max($labelHeight, $this->lastHeight());
        }

        // No trailing gap: the heading that follows brings its own leading
        // one, so the first block of a plan is spaced like every later one.
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
    /**
     * The plan's contact details, as the values to draw. Kept as a list rather
     * than one joined string because each value is drawn on its own: it gets
     * the link colour and a clickable area, while the separator between them
     * stays part of the surrounding line.
     *
     * @return list<string>
     */
    private function contactValues(Plan $plan): array
    {
        return array_values(array_filter([
            $plan->contact_email,
            $plan->contact_phone,
        ], fn (?string $v): bool => ! empty($v)));
    }

    private function drawShifts(Plan $plan): void
    {
        $shifts = $plan->shifts;

        foreach ($shifts as $index => $shift) {
            $previous = $index > 0 ? $shifts[$index - 1] : null;
            if (($previous === null || $previous->type !== $shift->type) && $shift->type !== '') {
                $this->drawGroupHeading(
                    $this->categoryLabel((string) $shift->type, $this->categoryNames),
                    $this->shiftBlockMinimum($shift),
                );
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

    private function drawGroupHeading(string $name, float $keepWithNext = 0.0): void
    {
        $this->font('B', self::HEADING_SIZE_CATEGORY, self::FONT_SERIF);
        $labelWidth = $this->measureWidth($name);
        $height = $this->measure('Xg', $this->contentWidth);

        // Eight units - twice the gap before a shift heading, so a category
        // reads as a section break rather than as one more block. At six it
        // was only 1.5x and barely told the two levels apart once the double
        // gap it used to inherit from the block above was gone.
        //
        // Not at the very top of a fresh page, though, where it would look
        // like a stray gap.
        $spacing = $this->cursorY > self::MARGIN_TOP ? 8 * self::SPACE : 0.0;

        // The heading, the gap down to its first shift and the opening of that
        // shift, so a category never ends up alone at the foot of a page - nor
        // with nothing under it but the heading of a shift whose own content
        // went to the next page.
        $this->ensureSpace($spacing + $height + self::SHIFT_GAP + $keepWithNext);

        // the check above may have started that fresh page itself
        if ($this->cursorY <= self::MARGIN_TOP) {
            $spacing = 0.0;
        }

        $this->cursorY += $spacing;

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

        $this->cursorY += $height;
    }

    /**
     * How much room the opening of a shift needs so that its heading is never
     * the last thing on a page: the heading itself, the rule under it, and
     * everything that has to follow before the table can break - a badge, the
     * first line of a description, the table head and its first slot row.
     *
     * Every part of it is measured rather than assumed. ROW_MIN_HEIGHT looks
     * like a fair estimate for the row but is only a floor: a row clears it by
     * some 7mm, because the name cell always stacks three lines. And the badge
     * and the description are drawn after the check, so leaving them out let
     * them push the table off the page and strand the heading anyway.
     */
    private function shiftBlockMinimum(Shift $shift): float
    {
        $columns = $this->columnLayout($shift);
        $first = $shift->subscriptions[0] ?? null;
        [$firstRowHeight] = $this->helperRowHeight(
            $this->helperRowStack($first),
            (string) ($first->comment ?? ''),
            $columns,
        );

        $minimum = $this->measureShiftHeading($shift)['height']
            + 1.0 + self::SPACE
            + $this->tableHeadHeight()
            + $firstRowHeight;

        if ($shift->requires_health_certificate) {
            $minimum += $this->badgeBlockHeight();
        }

        if ($shift->description) {
            $minimum += $this->lineHeight(10) + self::PARAGRAPH_GAP;
        }

        return $minimum;
    }

    private function drawShift(Shift $shift): void
    {
        // The gap before a heading belongs to the heading, not to whatever
        // block happens to precede it. Hung on the previous block it came out
        // position-dependent - 5.25mm after the plan header against 7mm after
        // another shift - so the first shift of a plan had less air than all
        // the others. Suppressed at the top of a page, where it would read as
        // a stray gap.
        $leading = $this->cursorY > self::MARGIN_TOP ? self::SHIFT_GAP : 0.0;

        $this->ensureSpace($leading + $this->shiftBlockMinimum($shift));

        // the check above may have started that fresh page itself
        if ($this->cursorY <= self::MARGIN_TOP) {
            $leading = 0.0;
        }

        $this->cursorY += $leading;

        $this->drawShiftHeading($shift);

        if ($shift->description) {
            $this->cursorY = $this->html($shift->description, 10) + self::PARAGRAPH_GAP;
        }

        $this->drawHelperTable($shift);
    }

    /**
     * Measures a shift's heading without drawing it, so that drawShift() can
     * check whether it still fits on the page together with the table head
     * and one row.
     *
     * @return array{date: string, height: float, baseline: float, titleBaseline: float, dateBaseline: float}
     */
    private function measureShiftHeading(Shift $shift): array
    {
        $date = str_replace('<br>', "\n", PlanController::buildDateString($shift->start, $shift->end));

        $this->font('', 10);
        $dateHeight = $this->measure($date, $this->contentWidth * 0.45);
        $dateBaseline = $this->lastBaselineOffset($dateHeight, $this->measure('X', $this->contentWidth * 0.45));

        $this->font('B', self::HEADING_SIZE_SHIFT, self::FONT_SERIF);
        $titleHeight = $this->measure($shift->title, $this->contentWidth * 0.55);
        $titleBaseline = $this->lastBaselineOffset($titleHeight, $this->measure('X', $this->contentWidth * 0.55));

        // Title and date share the baseline of their last line - see
        // lastBaselineOffset(). Whichever reaches its baseline later pushes it
        // down for both, and the block is as tall as the lower of the two ends
        // up needing.
        $baseline = max($titleBaseline, $dateBaseline);

        return [
            'date' => $date,
            'height' => max(
                $titleHeight + $baseline - $titleBaseline,
                $dateHeight + $baseline - $dateBaseline,
            ),
            'baseline' => $baseline,
            'titleBaseline' => $titleBaseline,
            'dateBaseline' => $dateBaseline,
        ];
    }

    private function drawShiftHeading(Shift $shift): void
    {
        ['date' => $date, 'height' => $height, 'baseline' => $baseline,
            'titleBaseline' => $titleBaseline, 'dateBaseline' => $dateBaseline] = $this->measureShiftHeading($shift);

        $dateWidth = $this->contentWidth * 0.45;
        $titleWidth = $this->contentWidth * 0.55;

        $this->font('B', self::HEADING_SIZE_SHIFT, self::FONT_SERIF);
        // the outline mirrors the heading levels, so a plan without
        // categories does not indent its shifts under a level that is not there
        $this->bookmark($shift->title, $this->shiftHeadingRole === 'H3' ? 2 : 1);
        $this->beginTag($this->shiftHeadingRole);
        $this->text(
            $shift->title,
            self::MARGIN_LEFT,
            $this->cursorY + $baseline - $titleBaseline,
            $titleWidth,
        );
        $this->endTag();

        // the date sits beside the title visually, but it reads after it
        $this->font('', 10);
        $this->beginTag('P');
        $this->text(
            $date,
            self::MARGIN_LEFT + $titleWidth,
            $this->cursorY + $baseline - $dateBaseline,
            $dateWidth,
            halign: 'R',
        );
        $this->endTag();

        $this->cursorY += $height;

        // The hairline offset stays off the grid - it belongs to the title,
        // not to the block boundary. Below the rule one unit is enough: the
        // rule itself already does the separating.
        $this->cursorY += 1.0;
        $this->rule($this->cursorY, 0.18, self::RULE_COLOR);
        $this->cursorY += self::SPACE;

        // below the rule, so it sits with the shift's details rather than
        // with its title
        if ($shift->requires_health_certificate) {
            $this->drawHealthCertificateBadge();
        }
    }

    /**
     * Outlined label marking a shift that requires a health certificate.
     */
    /**
     * Vertical space the health certificate badge occupies, its air above and
     * below included - what drawShift() has to hold back for it before it can
     * tell whether the heading still fits above its content.
     */
    private function badgeBlockHeight(): float
    {
        $this->font('B', self::BADGE_FONT_SIZE);
        $metrics = $this->pdf->font->getCurrentFont();
        $toMm = $metrics['usize'] / $metrics['size'];

        $box = ($metrics['capheight'] + abs($metrics['descent'])) * $toMm + (2 * self::BADGE_PADDING_Y);

        return self::BADGE_GAP + $box + self::BADGE_GAP + self::SPACE;
    }

    private function drawHealthCertificateBadge(): void
    {
        $this->cursorY += self::BADGE_GAP;

        $this->font('B', self::BADGE_FONT_SIZE);
        $label = __('shift.healthCertificateRequired');

        // the metrics are in pt while the document is in mm; usize is the
        // font size in document units, so their quotient converts
        $metrics = $this->pdf->font->getCurrentFont();
        $toMm = $metrics['usize'] / $metrics['size'];
        $ascent = $metrics['ascent'] * $toMm;
        $capHeight = $metrics['capheight'] * $toMm;
        $descent = abs($metrics['descent']) * $toMm;

        $width = $this->measureWidth($label) + (2 * self::BADGE_PADDING_X);
        $height = $capHeight + $descent + (2 * self::BADGE_PADDING_Y);

        $this->pushStyle(['lineWidth' => 0.18, 'lineColor' => self::BADGE_COLOR]);
        $this->artifact($this->pdf->graph->getRoundedRect(
            self::MARGIN_LEFT,
            $this->cursorY,
            $width,
            $height,
            self::BADGE_RADIUS,
            self::BADGE_RADIUS,
            corner: '1111',
            mode: 'D',
        ));
        $this->popStyle();

        // the box is decoration, but the requirement itself has to reach a
        // screen reader as ordinary text
        $this->beginTag('P');
        $this->text(
            $label,
            self::MARGIN_LEFT + self::BADGE_PADDING_X,
            // text() places a line box, so the leading above the capitals has
            // to come off: it puts the cap tops one padding below the frame,
            // and the descenders one padding above its bottom edge
            $this->cursorY + self::BADGE_PADDING_Y + $capHeight - $ascent,
            $width,
            color: self::BADGE_COLOR,
        );
        $this->endTag();

        $this->cursorY += $height + self::BADGE_GAP + self::SPACE;
    }

    /**
     * The three stacked lines of a helper's cell: name, e-mail, phone. They
     * are always all three, whether or not the slot is taken and whether or
     * not a value exists - the printout is filled in by hand, so the lines
     * have to be there to write on.
     *
     * @return list<array{0: string, 1: float, 2: bool, 3: float, 4: string}>
     */
    private function helperRowStack(?Subscription $subscription): array
    {
        return [
            [$subscription->name ?? '', self::TABLE_FONT_SIZE, true, 0.0, ''],
            [
                $subscription->email ?? '',
                self::CONTACT_FONT_SIZE,
                false,
                self::CONTACT_LINE_GAP,
                self::contactLink((string) ($subscription->email ?? '')),
            ],
            [
                $subscription->phone ?? '',
                self::CONTACT_FONT_SIZE,
                false,
                self::CONTACT_LINE_GAP,
                self::contactLink((string) ($subscription->phone ?? '')),
            ],
        ];
    }

    /**
     * Height of one row of the helper table and of each line in its name
     * cell, without drawing anything.
     *
     * A line with no value still takes up a normal line of its size, so a row
     * is the same height whether or not the slot is taken and there is a line
     * to write each value on. Measured once and reused for drawing, so the
     * lines and the borders cannot drift apart - and reused by drawShift(),
     * which has to know how tall the first row will be before it can tell
     * whether the heading still fits above it.
     *
     * @param  list<array{0: string, 1: float, 2: bool, 3: float, 4: string}>  $stack
     * @param  array<string, array{x: float, width: float, label: string}>  $columns
     * @return array{0: float, 1: list<float>}
     */
    private function helperRowHeight(array $stack, string $comment, array $columns): array
    {
        $nameWidth = $columns['name']['width'] - 2 * self::CELL_PADDING;

        $lineHeights = [];
        foreach ($stack as [$text, $fontSize, $hyphenate, $gap]) {
            $this->font('', $fontSize);
            $this->breakMode($hyphenate);
            $lineHeights[] = $gap + ($text === ''
                ? $this->lineHeight($fontSize)
                : $this->measure($text, $nameWidth));
        }
        $this->breakMode(true);

        // The index and clothing size are always a single short token and can
        // never drive the row height, so they are not measured.
        $this->font('', self::TABLE_FONT_SIZE);
        $commentHeight = $this->measure($comment, $columns['comment']['width'] - 2 * self::CELL_PADDING);

        return [
            max(
                self::ROW_MIN_HEIGHT,
                max(array_sum($lineHeights), $commentHeight) + 2 * self::CELL_PADDING,
            ),
            $lineHeights,
        ];
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

            $stack = $this->helperRowStack($subscription);
            $nameWidth = $columns['name']['width'] - 2 * self::CELL_PADDING;
            [$rowHeight, $lineHeights] = $this->helperRowHeight($stack, $comment, $columns);

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

            $this->font('', self::TABLE_FONT_SIZE);
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
                color: self::COLOR_ZINC_500,
            );
            $this->endTag();

            // name, e-mail and phone share one cell - they describe the same
            // person, so they are not columns of their own
            $this->beginTag('TD', null, [], true);
            $lineY = $textY;
            foreach ($stack as $line => [$text, $fontSize, $hyphenate, $gap, $href]) {
                if ($text !== '') {
                    $this->font('', $fontSize);
                    $this->breakMode($hyphenate);
                    $x = $columns['name']['x'] + self::CELL_PADDING;
                    $this->text(
                        $text,
                        $x,
                        $lineY + $gap,
                        $nameWidth,
                        color: $href !== '' ? self::LINK_COLOR : self::COLOR_BLACK,
                    );

                    // an e-mail address and a phone number are worth a tap on
                    // screen, even though the sheet exists to be printed
                    if ($href !== '') {
                        $this->linkArea($href, $x, $lineY + $gap, $this->measureWidth($text), $fontSize);
                    }
                }

                $lineY += $lineHeights[$line];
            }
            $this->breakMode(true);
            $this->endTag();

            // the stack above left the font on its last line's size, so the
            // remaining cells have to select their own again
            $this->font('', self::TABLE_FONT_SIZE);

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
        $this->font('B', self::TABLE_FONT_SIZE);

        return $this->measure('Xg', $this->contentWidth) + self::HEAD_PADDING_BOTTOM;
    }

    private function drawTableHead(array $columns): void
    {
        $top = $this->cursorY;
        $height = $this->tableHeadHeight();

        $this->font('B', self::TABLE_FONT_SIZE);

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
                    color: self::COLOR_ZINC_600,
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

    /**
     * The bar beside a block quote, drawn in segments as the quote is laid
     * out: one per line and one per gap between its blocks. Segments meet, so
     * they read as one bar - and a quote broken across pages gets a bar on
     * each of them without any bookkeeping.
     *
     * It sits at the left edge the surrounding text keeps, so the quote reads
     * as indented from the bar rather than the bar as hanging in the margin.
     */
    private function quoteRule(float $x, float $top, float $bottom): void
    {
        if ($bottom <= $top) {
            return;
        }

        $this->artifact($this->pdf->graph->getLine(
            $x,
            $top,
            $x,
            $bottom,
            ['lineWidth' => self::QUOTE_RULE_WIDTH, 'lineColor' => self::RULE_COLOR],
        ));
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
            $this->pushStyle(['fillColor' => self::COLOR_ZINC_500]);
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

    private function font(string $style, float $size, string $family = self::FONT_SANS): void
    {
        $this->pdf->font->insert($this->pdf->pon, $family, $style, $size);
    }

    private function text(
        string $txt,
        float $x,
        float $y,
        float $width,
        string $halign = 'L',
        string $color = self::COLOR_BLACK,
        bool $underline = false,
        bool $strike = false,
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
            // J means "stretch to $width", and the library treats the only
            // line of a single-line string as the last one, which it leaves
            // alone unless jlast says otherwise
            jlast: false,
            underline: $underline,
            linethrough: $strike,
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
        // keyed by family as well, or a size measured in one family would be
        // handed back for the other
        $key = self::FONT_SANS.'/'.$fontSize;

        if (! isset($this->lineHeightCache[$key])) {
            $this->font('', $fontSize);
            $this->lineHeightCache[$key] = $this->measure('X', $this->contentWidth);
        }

        return $this->lineHeightCache[$key];
    }

    /**
     * How far below the top of a text block the baseline of its last line
     * sits, in mm, for the currently selected font. Two blocks drawn so that
     * these offsets coincide share a baseline.
     *
     * That is what makes a heading and the date beside it read as one line
     * although they differ in size and family. Lining up the bottoms of their
     * boxes instead leaves the baselines apart by the difference of their
     * descenders - measured against the shift heading, 0.34mm for a one-line
     * date and 0.85mm for one that spans two days.
     */
    private function lastBaselineOffset(float $blockHeight, float $oneLineHeight): float
    {
        $metrics = $this->pdf->font->getCurrentFont();

        // the metrics are in pt while the document is in mm; usize is the
        // font size expressed in document units, so their quotient converts
        $ascent = $metrics['ascent'] * $metrics['usize'] / $metrics['size'];

        return $blockHeight - $oneLineHeight + $ascent;
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
     * Splits one of the sanitized rich text fields into blocks of words.
     *
     * The description is laid out here rather than handed to the HTML cell
     * renderer, because that one cannot justify a line that carries more than
     * one inline style - see html() for the mechanics. Everything the
     * sanitizer allows is covered: p and blockquote as blocks, ul/ol/li as
     * blocks with a marker, br as a forced break, and strong/em/u/s as the
     * styles of the words between them. An <a> keeps its text; the export
     * carries no link annotations either way.
     *
     * @return list<array{tag: string, list: int, quote: int, quoteIndent: float, ordered: bool, marker: string, indent: float, words: list<array{text: string, style: string, underline: bool, strike: bool, href: string, break: bool, space: bool}>}>
     */
    private function richBlocks(string $html): array
    {
        $doc = new DOMDocument;
        // the sanitized value is a fragment, and libxml needs the encoding
        // declared or it reads the bytes as Latin-1
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        $body = $doc->getElementsByTagName('body')->item(0);

        $blocks = [];
        if ($body instanceof DOMNode) {
            $this->collectRichBlocks($body, $blocks, 0, 0.0, 0.0);
        }

        return array_values(array_filter($blocks, static fn (array $b): bool => $b['words'] !== []));
    }

    /**
     * @param  list<array{tag: string, list: int, quote: int, quoteIndent: float, ordered: bool, marker: string, indent: float, words: list<array<string, mixed>>}>  $blocks
     */
    private function collectRichBlocks(DOMNode $node, array &$blocks, int $quote, float $indent, float $quoteIndent): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                // text outside any block - descriptions saved before the
                // rich-text editor are a single run of it
                $words = [];
                $this->collectRichWords($child, ['style' => '', 'underline' => false, 'strike' => false, 'href' => ''], $words);
                if ($words !== []) {
                    $blocks[] = ['tag' => 'P', 'list' => 0, 'quote' => $quote, 'quoteIndent' => $quoteIndent, 'ordered' => false, 'marker' => '', 'indent' => $indent, 'words' => $words];
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'ul' || $tag === 'ol') {
                $this->richListId++;
                $ordered = $tag === 'ol';
                $number = 0;
                foreach ($child->childNodes as $item) {
                    if (! $item instanceof DOMElement || strtolower($item->tagName) !== 'li') {
                        continue;
                    }

                    $number++;
                    $words = [];
                    $this->collectRichWords($item, ['style' => '', 'underline' => false, 'strike' => false, 'href' => ''], $words);
                    $blocks[] = [
                        'tag' => 'LI',
                        'list' => $this->richListId,
                        'quote' => $quote,
                        'quoteIndent' => $quoteIndent,
                        'ordered' => $ordered,
                        'marker' => $ordered ? $number.'.' : '•',
                        'indent' => $indent + self::RICH_INDENT,
                        'words' => $words,
                    ];
                }

                continue;
            }

            if ($tag === 'blockquote') {
                $this->richQuoteId++;
                // the bar belongs to the quote, so it is drawn at the indent
                // the quote starts from - not at the one of whatever block
                // inside it happens to be deeper, such as a list
                $this->collectRichBlocks(
                    $child,
                    $blocks,
                    $this->richQuoteId,
                    $indent + self::QUOTE_INDENT,
                    $indent,
                );

                continue;
            }

            // p, or anything else the sanitizer let through: its inline
            // content becomes one block
            $words = [];
            $this->collectRichWords($child, ['style' => '', 'underline' => false, 'strike' => false, 'href' => ''], $words);
            if ($words !== []) {
                $blocks[] = ['tag' => 'P', 'list' => 0, 'quote' => $quote, 'quoteIndent' => $quoteIndent, 'ordered' => false, 'marker' => '', 'indent' => $indent, 'words' => $words];
            }
        }
    }

    /**
     * @param  array{style: string, underline: bool, strike: bool, href: string}  $state
     * @param  list<array<string, mixed>>  $words
     * @param  array{break: bool, space: bool}  $flow
     * @return array{break: bool, space: bool}
     */
    private function collectRichWords(DOMNode $node, array $state, array &$words, array $flow = ['break' => false, 'space' => false]): array
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = $child->textContent;
                $chunks = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($chunks === []) {
                    // whitespace only, but it still separates what surrounds it
                    $flow['space'] = $flow['space'] || $text !== '';

                    continue;
                }

                // Whether a space precedes a word cannot be recovered from the
                // words alone: "<s>text</s>." has none, "</s> and" has one.
                // Splitting on whitespace throws that away, so it is carried
                // along here - otherwise a full stop after an inline tag ends
                // up detached from the word it belongs to.
                $leading = $flow['space'] || preg_match('/^\s/u', $text) === 1;
                foreach ($chunks as $index => $word) {
                    $words[] = $state + [
                        'text' => $word,
                        'break' => $flow['break'],
                        'space' => $words !== [] && ($index > 0 || $leading),
                    ];
                    $flow['break'] = false;
                }

                $flow['space'] = preg_match('/\s$/u', $text) === 1;

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'br') {
                $flow['break'] = true;

                continue;
            }

            if ($tag === 'a') {
                $link = $state;
                $link['href'] = trim($child->getAttribute('href'));
                $flow = $this->collectRichWords($child, $link, $words, $flow);

                continue;
            }

            $next = $state;
            if ($tag === 'strong' || $tag === 'b') {
                $next['style'] = str_contains($next['style'], 'I') ? 'BI' : 'B';
            } elseif ($tag === 'em' || $tag === 'i') {
                $next['style'] = str_contains($next['style'], 'B') ? 'BI' : 'I';
            } elseif ($tag === 'u') {
                $next['underline'] = true;
            } elseif ($tag === 's') {
                $next['strike'] = true;
            }

            $flow = $this->collectRichWords($child, $next, $words, $flow);
        }

        return $flow;
    }

    /**
     * Drops a <p> that is the only child of an <li>.
     *
     * Such a paragraph says nothing the <li> does not already say, but the
     * cell renderer treats it as a block: it breaks the line after the list
     * marker and puts paragraph margins between the items, so the bullet ends
     * up alone above its text. The rich-text editor emits this shape
     * routinely, so it is unwrapped at render time rather than in the stored
     * value - on the web it renders correctly either way.
     *
     * Only an <li> whose entire content is that one paragraph matches, so an
     * item that genuinely holds several blocks keeps its structure.
     */
    public static function unwrapListParagraphs(string $html): string
    {
        // The content may not contain a paragraph tag of its own: with a plain
        // .*? the match would span from the first <p> to the last </p> of an
        // item that holds two paragraphs and splice them into one broken run.
        return (string) preg_replace(
            '#(<li(?:\s[^>]*)?>)\s*<p(?:\s[^>]*)?>((?:(?!</?p[\s/>]).)*)</p>\s*(</li>)#is',
            '$1$2$3',
            $html,
        );
    }

    /**
     * Renders one of the sanitized rich text fields and returns the y position
     * just below it, so the caller can carry on flowing content.
     */
    private function html(string $html, float $size): float
    {
        $blocks = $this->richBlocks(self::unwrapListParagraphs($html));
        if ($blocks === []) {
            return $this->cursorY;
        }

        // The lines are broken here, not by the HTML cell renderer, because
        // that one cannot justify a line carrying more than one inline style:
        // it drops the TJ array that composite fonts need - the Tw operator
        // applies only to the single-byte code 32, which an Identity-H font
        // never emits (ISO 32000-2, 9.3.3) - writes each run as a plain Tj
        // instead and puts the whole of a run's allowance into the gap before
        // the next one, measured at 2.27 space widths where one belongs.
        //
        // The text API justifies a single run to any target width correctly,
        // so each run is drawn against its own target and the gaps between
        // runs are advanced by hand.
        $this->richSpaceWidths = [];
        $this->breakMode(false, false);

        $openList = 0;
        $openQuote = 0;
        $previous = null;
        foreach ($blocks as $block) {
            if ($previous !== null) {
                $gapTop = $this->cursorY;

                // items of one list stay tight; between blocks it is the same
                // gap the description keeps to whatever follows it
                $this->cursorY += $block['list'] !== 0 && $block['list'] === $previous['list']
                    ? 0.0
                    : self::PARAGRAPH_GAP;

                // the bar runs through the gap as well, or it would break into
                // one piece per block
                if ($block['quote'] !== 0 && $block['quote'] === $previous['quote']) {
                    $this->quoteRule(
                        self::MARGIN_LEFT + $block['quoteIndent'],
                        $gapTop,
                        $this->cursorY,
                    );
                }
            }

            // a quote may hold a list, so it is opened first and closed last
            if ($block['quote'] !== $openQuote) {
                if ($openList !== 0) {
                    $this->endTag();
                    $openList = 0;
                }

                if ($openQuote !== 0) {
                    $this->endTag();
                }

                $openQuote = $block['quote'];
                if ($openQuote !== 0) {
                    $this->beginTag('BlockQuote');
                }
            }

            if ($block['list'] !== $openList) {
                if ($openList !== 0) {
                    $this->endTag();
                }

                $openList = $block['list'];
                if ($openList !== 0) {
                    // An L whose items carry an Lbl has to say how they are
                    // numbered, and not with None (PDF/UA-2, 8.2.5.25).
                    // ListNumbering belongs to the List attribute owner, so /O
                    // has to be declared with it - the library writes the pairs
                    // into /A verbatim and adds no owner of its own.
                    $this->beginTag('L', null, [
                        'O' => 'List',
                        'ListNumbering' => $block['ordered'] === true ? 'Decimal' : 'Disc',
                    ]);
                }
            }

            $this->drawRichBlock($block, $size);
            $previous = $block;
        }

        if ($openList !== 0) {
            $this->endTag();
        }

        if ($openQuote !== 0) {
            $this->endTag();
        }

        $this->breakMode(true);

        return $this->cursorY;
    }

    /**
     * @param  array{tag: string, list: int, quote: int, quoteIndent: float, ordered: bool, marker: string, indent: float, words: list<array<string, mixed>>}  $block
     */
    private function drawRichBlock(array $block, float $size): void
    {
        $available = $this->contentWidth - $block['indent'];
        $lines = $this->breakRichLines($block['words'], $available, $size);
        $lineHeight = $this->lineHeight($size);
        $item = $block['tag'] === 'LI';

        if ($item) {
            $this->beginTag('LI');
        }

        foreach ($lines as $index => $line) {
            // A single word wider than the column - a URL, typically. Our own
            // breaking works on words and cannot see inside one, but the
            // library can break it at the zero-width points it finds, so that
            // one line is handed over whole. Left ragged: there is nothing to
            // distribute slack between.
            $word = count($line['words']) === 1 ? $line['words'][0] : null;
            $long = $word !== null && $word['width'] > $available + 0.01;

            if ($long) {
                $this->breakMode(false, true);
                $this->font((string) $word['style'], $size);
                $height = $this->measure((string) $word['text'], $available);
            } else {
                $height = $lineHeight;
            }

            $this->ensureSpace($height);

            if ($index > 0) {
                $this->cursorY += self::LINE_SPACING;
            }

            // captured after the space check, so a page break has already
            // moved the cursor and the segment lands on the right page
            $ruleTop = $this->cursorY - ($index > 0 ? self::LINE_SPACING : 0.0);

            if ($item && $index === 0) {
                // the marker sits in the indent, right-aligned against the
                // text so single and double digit numbers line up
                $this->beginTag('Lbl');
                $this->font('', $size);
                $this->text(
                    $block['marker'],
                    self::MARGIN_LEFT + $block['indent'] - self::RICH_INDENT,
                    $this->cursorY,
                    self::RICH_INDENT - self::SPACE,
                    halign: 'R',
                );
                $this->endTag();
                $this->beginTag('LBody');
            } elseif ($index === 0 && ! $item) {
                $this->beginTag('P');
            }

            if ($long) {
                $this->text(
                    (string) $word['text'],
                    self::MARGIN_LEFT + $block['indent'],
                    $this->cursorY,
                    $available,
                    underline: $word['underline'] === true,
                    strike: $word['strike'] === true,
                );
                $this->breakMode(false, false);
            } else {
                $this->drawRichLine(
                    $line['words'],
                    self::MARGIN_LEFT + $block['indent'],
                    $available,
                    $size,
                    $line['justify'],
                );
            }

            $this->cursorY += $height;

            if ($block['quote'] !== 0) {
                $this->quoteRule(
                    self::MARGIN_LEFT + $block['quoteIndent'],
                    $ruleTop,
                    $this->cursorY,
                );
            }
        }

        if ($item) {
            $this->endTag();
            $this->endTag();
        } elseif ($lines !== []) {
            $this->endTag();
        }
    }

    /**
     * Greedy line breaking over the words of one block.
     *
     * A line is only stretched when it was broken because it ran out of room.
     * One that ends at a <br>, like the last line of the block, keeps its
     * natural width - stretching it would leave a hole rather than a straight
     * edge.
     *
     * @param  list<array<string, mixed>>  $words
     * @return list<array{words: list<array<string, mixed>>, justify: bool}>
     */
    private function breakRichLines(array $words, float $available, float $size): array
    {
        $lines = [];
        $line = [];
        $width = 0.0;

        foreach ($words as $word) {
            $wordWidth = $this->richWordWidth($word, $size);
            $space = $line === [] || $word['space'] !== true
                ? 0.0
                : $this->richSpaceWidth((string) end($line)['style'], $size);

            $forced = $word['break'] === true && $line !== [];
            $overflows = $line !== [] && ($width + $space + $wordWidth) > $available + 0.01;

            if ($forced || $overflows) {
                $lines[] = ['words' => $line, 'justify' => ! $forced];
                $line = [];
                $width = 0.0;
                $space = 0.0;
            }

            $line[] = $word + ['width' => $wordWidth];
            $width += $space + $wordWidth;
        }

        if ($line !== []) {
            $lines[] = ['words' => $line, 'justify' => false];
        }

        return $lines;
    }

    /**
     * Draws one line, run by run. A run is a maximal group of words sharing a
     * style: the library justifies each of them to the target width handed to
     * it, and the gaps between runs are advanced here.
     *
     * @param  list<array<string, mixed>>  $line
     */
    private function drawRichLine(array $line, float $x, float $available, float $size, bool $justify): void
    {
        $runs = [];
        foreach ($line as $word) {
            $key = $word['style'].($word['underline'] ? 'u' : '').($word['strike'] ? 's' : '')
                .'|'.$word['href'];
            if ($runs !== [] && $runs[count($runs) - 1]['key'] === $key) {
                $runs[count($runs) - 1]['words'][] = $word;

                continue;
            }

            $runs[] = ['key' => $key, 'word' => $word, 'words' => [$word]];
        }

        // natural width of the line: every run as its own string, plus a space
        // at each boundary that had one, in the font of the run before it
        $natural = 0.0;
        $gaps = 0;
        foreach ($runs as $index => $run) {
            $this->font((string) $run['word']['style'], $size);
            $natural += $this->measureWidth($this->richRunText($run['words']));
            $gaps += $this->richRunGaps($run['words']);

            $next = $runs[$index + 1] ?? null;
            if ($next !== null && $next['word']['space'] === true) {
                $natural += $this->richSpaceWidth((string) $run['word']['style'], $size);
                $gaps++;
            }
        }

        $share = $justify && $gaps > 0 ? max(0.0, $available - $natural) / $gaps : 0.0;

        // more slack than the gaps can absorb: leave the line ragged
        if ($share > self::RICH_MAX_STRETCH * $this->richSpaceWidth((string) $runs[0]['word']['style'], $size)) {
            $share = 0.0;
        }

        foreach ($runs as $index => $run) {
            $style = (string) $run['word']['style'];
            $this->font($style, $size);
            $text = $this->richRunText($run['words']);
            $inner = $this->richRunGaps($run['words']);
            $target = $this->measureWidth($text) + ($inner * $share);

            $href = (string) $run['word']['href'];

            $this->text(
                $text,
                $x,
                $this->cursorY,
                $target,
                // stretching needs a gap to put the slack in
                halign: $inner > 0 && $share > 0.0 ? 'J' : 'L',
                // Set apart by colour, like the contact line, rather than by
                // an underline: the address itself is not written out, so the
                // text has to carry the hint that it leads somewhere.
                color: $href !== '' ? self::LINK_COLOR : self::COLOR_BLACK,
                underline: $run['word']['underline'] === true,
                strike: $run['word']['strike'] === true,
            );

            if ($href !== '') {
                $this->linkArea($href, $x, $this->cursorY, $target, $size);
            }

            $x += $target;
            $next = $runs[$index + 1] ?? null;
            if ($next !== null && $next['word']['space'] === true) {
                $x += $this->richSpaceWidth($style, $size) + $share;
            }
        }
    }

    /**
     * The address a contact detail leads to, or an empty string when it is
     * neither an e-mail nor a phone number. A dialler wants the number
     * without its spacing, so tel: gets the digits and a leading plus only.
     */
    public static function contactLink(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            return 'mailto:'.$value;
        }

        $dialled = preg_replace('/(?!^\+)[^0-9]/', '', $value) ?? '';

        return $dialled === '' ? '' : 'tel:'.$dialled;
    }

    /**
     * Makes the box a run occupies clickable.
     *
     * The annotation has to be referenced from its page, and the library hangs
     * it into the structure tree itself - it walks the page's annotations when
     * writing and gives each one that no structure element claims a Link
     * element of its own.
     */
    private function linkArea(string $href, float $x, float $y, float $width, float $size): void
    {
        $oid = $this->pdf->setLink($x, $y, $width, $this->lineHeight($size), $href);
        if ($oid > 0) {
            $this->pdf->page->addAnnotRef($oid);
        }
    }

    /**
     * The text of a run, with a space only where one stood in the markup.
     *
     * @param  list<array<string, mixed>>  $words
     */
    private function richRunText(array $words): string
    {
        $text = '';
        foreach ($words as $index => $word) {
            if ($index > 0 && $word['space'] === true) {
                $text .= ' ';
            }

            $text .= (string) $word['text'];
        }

        return $text;
    }

    /**
     * How many spaces inside a run can take a share of the slack.
     *
     * @param  list<array<string, mixed>>  $words
     */
    private function richRunGaps(array $words): int
    {
        $gaps = 0;
        foreach ($words as $index => $word) {
            if ($index > 0 && $word['space'] === true) {
                $gaps++;
            }
        }

        return $gaps;
    }

    /**
     * @param  array<string, mixed>  $word
     */
    private function richWordWidth(array $word, float $size): float
    {
        $this->font((string) $word['style'], $size);

        return $this->measureWidth((string) $word['text']);
    }

    /**
     * Width of a space in one style, in mm. Measured as the difference between
     * two glyphs with and without a space between them - measuring the space
     * on its own returns nothing, the text cell trims it.
     */
    private function richSpaceWidth(string $style, float $size): float
    {
        $key = $style.'/'.$size;

        if (! isset($this->richSpaceWidths[$key])) {
            $this->font($style, $size);
            $this->richSpaceWidths[$key] = $this->measureWidth('a a') - $this->measureWidth('aa');
        }

        return $this->richSpaceWidths[$key];
    }
}
