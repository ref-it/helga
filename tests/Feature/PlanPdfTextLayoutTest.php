<?php

use App\Models\Plan;
use App\Models\User;
use App\Support\PlanPdfRenderer;
use Com\Tecnick\Pdf\Tcpdf;

/**
 * Guards the way the HTML cell renderer used to mangle a list: the defect was
 * visible only in the PDF, not on the web, and came from the shape of the
 * markup rather than from its content.
 */
/**
 * The page content operators of a PDF, decompressed - the streams are
 * Flate-encoded, so searching the raw bytes for an operator finds nothing.
 */
function pdfContent(string $pdf): string
{
    // "endstream" contains "stream" itself, so the opening keyword has to be
    // matched together with the payload rather than split on
    preg_match_all('#stream\r?\n(.*?)endstream#s', $pdf, $matches);

    $content = '';
    foreach ($matches[1] as $stream) {
        $inflated = @gzuncompress($stream);
        if ($inflated !== false) {
            $content .= $inflated."\n";
        }
    }

    return $content;
}

/**
 * The left margin in PDF points - where a line of body text starts. Read from
 * the renderer rather than written out, so a changed margin moves the
 * assertions with it instead of turning them into a puzzle.
 */
function leftMarginPt(): float
{
    $margin = (new ReflectionClass(PlanPdfRenderer::class))->getConstants()['MARGIN_LEFT'];

    return $margin * 72 / 25.4;
}

/**
 * The x positions, in PDF points, at which the description's runs are drawn.
 * The date beside a shift heading is the only other 10pt text, and it sits in
 * the right-hand column, so anything left of the middle is description.
 *
 * @return list<float>
 */
function descriptionRunPositions(string $pdf): array
{
    preg_match_all(
        '#/F\d+ 10\.0+ Tf.*?([\d.]+) ([\d.]+) Td#s',
        pdfContent($pdf),
        $matches,
        PREG_SET_ORDER,
    );

    // grouped by baseline, because the table head labels are 10pt too. The
    // description is the topmost such line: the plan's own text is cleared in
    // the fixture, the plan title is 18pt, the shift title 12pt, and the date
    // beside it sits right of the middle.
    $lines = [];
    foreach ($matches as $match) {
        $x = (float) $match[1];
        if ($x < 300.0) {
            $lines[$match[2]][] = $x;
        }
    }

    if ($lines === []) {
        return [];
    }

    krsort($lines, SORT_NUMERIC);
    $first = reset($lines);
    sort($first);

    return $first;
}

/**
 * The vertical metrics of the badge's face, in PDF points - the same numbers
 * the renderer sizes the box from.
 *
 * @return array{cap: float, descent: float, ascent: float}
 */
function badgeFontMetrics(): array
{
    $pdf = new Tcpdf('mm', true, true, true, 'pdfua2', null, [
        'allowedPaths' => [resource_path('fonts'), resource_path('hyphenation')],
    ]);
    $pdf->addPage(['format' => 'A4']);
    $pdf->font->insert($pdf->pon, 'adwaitasans', 'B', 8.0);
    $metrics = $pdf->font->getCurrentFont();

    return [
        'cap' => (float) $metrics['capheight'],
        'descent' => abs((float) $metrics['descent']),
        'ascent' => (float) $metrics['ascent'],
    ];
}

function planWithDescription(User $owner, string $description): Plan
{
    $plan = createOwnedPlan($owner);
    // the plan's own description and its contact line are the only other 10pt
    // text on the left-hand side, so they are cleared to leave the shift's
    // description alone in the measurement
    $plan->update(['description' => '', 'contact_email' => null, 'contact_phone' => null]);
    $plan->shifts()->create([
        'title' => 'Shift', 'group' => 0,
        'start' => now(), 'end' => now()->addHour(), 'team_size' => 0,
        'description' => $description,
    ]);

    return $plan;
}

test('a paragraph that fills a list item is unwrapped', function (): void {
    // what the rich-text editor stores
    expect(PlanPdfRenderer::unwrapListParagraphs('<ul><li><p>one</p></li><li><p>two</p></li></ul>'))
        ->toBe('<ul><li>one</li><li>two</li></ul>');

    // inline formatting inside the item survives
    expect(PlanPdfRenderer::unwrapListParagraphs('<li><p>a <strong>b</strong> c</p></li>'))
        ->toBe('<li>a <strong>b</strong> c</li>');
});

test('a list item holding more than one block keeps its structure', function (): void {
    foreach ([
        '<li><p>one</p><p>two</p></li>',
        '<li><p>intro</p><ul><li>nested</li></ul></li>',
        '<li>bare text</li>',
    ] as $html) {
        expect(PlanPdfRenderer::unwrapListParagraphs($html))->toBe($html);
    }
});

test('punctuation after an inline tag keeps no space before it', function (): void {
    $owner = User::factory()->create();

    $glued = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription($owner, '<p><strong>Wort</strong>, dahinter.</p>')))
        ->getContent();
    $spaced = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription($owner, '<p><strong>Wort</strong> , dahinter.</p>')))
        ->getContent();

    // Splitting the markup into words loses whether a space stood between two
    // runs, so it is carried along. Without that the comma is pushed away
    // from the word it belongs to.
    $gluedRuns = descriptionRunPositions($glued);
    $spacedRuns = descriptionRunPositions($spaced);

    expect($gluedRuns)->toHaveCount(2);
    expect($spacedRuns)->toHaveCount(2);
    expect($gluedRuns[1])->toBeLessThan($spacedRuns[1]);
});

test('a list declares how its items are numbered', function (): void {
    $owner = User::factory()->create();

    $unordered = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription($owner, '<ul><li>one</li></ul>')))
        ->getContent();
    $ordered = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription($owner, '<ol><li>one</li></ol>')))
        ->getContent();

    // An L whose items carry an Lbl has to say how they are numbered, and not
    // with None (PDF/UA-2, 8.2.5.25). ListNumbering belongs to the List
    // attribute owner, so /O has to be declared with it.
    expect($unordered)->toContain('/O /List');
    expect($unordered)->toContain('/ListNumbering /Disc');
    expect($ordered)->toContain('/ListNumbering /Decimal');
});

test('a word wider than the column is broken rather than run off the page', function (): void {
    $owner = User::factory()->create();
    $long = 'https://example.com/'.implode('/', array_fill(0, 20, 'segment'));

    $pdf = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription($owner, '<p>'.$long.'</p>')))
        ->getContent();

    // Our own breaking works on words and cannot see inside one, so such a
    // word is handed to the library, which breaks it at the zero-width points
    // it finds. Left unbroken it ran past the right margin and off the sheet.
    preg_match_all('#/F\d+ 10\.0+ Tf.*?([\d.]+) ([\d.]+) Td#s', pdfContent($pdf), $matches, PREG_SET_ORDER);

    // a description line starts at the left margin; the table head labels sit
    // inside their cells and start further right
    $lines = [];
    foreach ($matches as $match) {
        if (abs(((float) $match[1]) - leftMarginPt()) < 0.5) {
            $lines[$match[2]] = true;
        }
    }

    expect(count($lines))->toBeGreaterThan(1);
});

test('a line that had to break early is not stretched apart', function (): void {
    $owner = User::factory()->create();
    $long = 'https://example.com/'.implode('/', array_fill(0, 18, 'segment'));

    $pdf = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription(
            $owner,
            '<p>Ein kurzer Satz '.$long.' und danach geht es weiter, mit genug Text für mehrere '
                .'vollständige Zeilen, damit mindestens eine davon wirklich justiert wird und die '
                .'Anpassungen im Inhaltsstrom auftauchen, an denen sich die Streckung messen lässt.</p>',
        )))->getContent();

    // The TJ adjustments are the stretch, in thousandths of the font size. A
    // space is about 281 of those at 10pt, and the cap allows three times a
    // natural gap - so a line that broke early because the next word is a URL
    // too wide to share it stays ragged instead of taking the whole
    // remainder into its two or three gaps.
    preg_match_all('#\)\s*(-?\d+(?:\.\d+)?)\s*\(#', pdfContent($pdf), $matches);

    $stretches = array_map(static fn (string $v): float => abs((float) $v), $matches[1]);

    expect($stretches)->not->toBeEmpty();
    expect(max($stretches))->toBeLessThan(900.0);
});

test('a link in a description is clickable and set apart by colour', function (): void {
    $owner = User::factory()->create();
    $href = 'https://example.com/anmeldung';

    $pdf = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription(
            $owner,
            '<p>Details unter <a href="'.$href.'" rel="noopener">Anmeldung</a> nachlesen.</p>',
        )))->getContent();

    // asserted as booleans: a failed toContain() on a PDF prints the whole
    // binary, which buries the reason
    $content = pdfContent($pdf);

    // a Link annotation over the text, carrying the address. The annotation
    // dictionary is not inside a content stream, so the raw bytes are searched
    expect(str_contains($pdf, '/Subtype /Link'))->toBeTrue('no link annotation');
    expect(str_contains($pdf, $href))->toBeTrue('the annotation does not carry the address');

    // and claimed by a Link structure element, so it reaches assistive
    // technology rather than staying a bare rectangle
    expect(str_contains($pdf, '/S /Link'))->toBeTrue('the annotation is not in the structure tree');

    // Set apart by colour (#0069a8), not by an underline, and the address is
    // not written out - the text itself carries the link.
    expect(str_contains($content, '0.000000 0.411765 0.658824 rg'))->toBeTrue('link text is not in the link colour');
});

test('contact details are clickable in the plan header and in the helper table', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['description' => '']);
    $shift = $plan->shifts()->create([
        'title' => 'Shift', 'group' => 0,
        'start' => now(), 'end' => now()->addHour(), 'team_size' => 1,
    ]);
    $shift->subscriptions()->create([
        'name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0170 1234567',
    ]);

    $pdf = $this->actingAs($owner)->get(route('plan.export.pdf', $plan))->getContent();

    // The plan's own contact line and the helper's row: an address is worth a
    // tap on screen, even though the sheet exists to be printed. The dialler
    // wants the number without its spacing.
    expect(str_contains($pdf, 'mailto:contact@example.com'))->toBeTrue('the plan contact e-mail is not a link');
    expect(str_contains($pdf, 'tel:0123456789'))->toBeTrue('the plan contact phone is not a link');
    expect(str_contains($pdf, 'mailto:jane@example.com'))->toBeTrue('the helper e-mail is not a link');
    expect(str_contains($pdf, 'tel:01701234567'))->toBeTrue('the helper phone is not a link');
});

test('a phone number keeps only what a dialler can use', function (): void {
    // covers the shapes the plan and subscription forms accept
    expect(PlanPdfRenderer::contactLink('0123 456789'))->toBe('tel:0123456789');
    expect(PlanPdfRenderer::contactLink('+49 170 1234567'))->toBe('tel:+491701234567');
    expect(PlanPdfRenderer::contactLink('jane@example.com'))->toBe('mailto:jane@example.com');
    expect(PlanPdfRenderer::contactLink(''))->toBe('');
});

test('a block quote gets one unbroken bar beside it', function (): void {
    $owner = User::factory()->create();

    $pdf = $this->actingAs($owner)
        ->get(route('plan.export.pdf', planWithDescription(
            $owner,
            '<blockquote><p>Ein Zitat, das über mehrere Zeilen läuft, damit der Strich daneben eine '
                .'gewisse Länge bekommt und mehrere Segmente braucht.</p>'
                .'<p>Ein zweiter Absatz im selben Zitat.</p>'
                .'<ul><li>und ein Punkt darin</li></ul></blockquote>',
        )))->getContent();

    // The bar is drawn in segments, one per line and one per gap between the
    // quote's blocks, so that a quote broken across pages gets a bar on each
    // of them. They have to meet, or it reads as a dashed line - and they all
    // belong at the quote's own left edge, not at the deeper indent of a list
    // inside it.
    preg_match_all(
        '#([\d.]+)\s+([\d.]+)\s+m\s+([\d.]+)\s+([\d.]+)\s+l#',
        pdfContent($pdf),
        $matches,
        PREG_SET_ORDER,
    );

    // every vertical segment, by the x it sits at
    $vertical = [];
    foreach ($matches as $match) {
        [, $x1, $y1, $x2, $y2] = array_map('floatval', $match);
        if (abs($x1 - $x2) > 0.01) {
            continue;
        }

        $vertical[] = ['x' => $x1, 'from' => min($y1, $y2), 'to' => max($y1, $y2)];
    }

    // the left margin, in points: where the surrounding text starts
    $spans = [];
    foreach ($vertical as $segment) {
        if (abs($segment['x'] - leftMarginPt()) < 0.5) {
            $spans[] = [$segment['from'], $segment['to']];
        }
    }

    expect(count($spans))->toBeGreaterThan(1);

    usort($spans, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

    $bottom = $spans[0][0];
    $top = $spans[0][1];
    foreach (array_slice($spans, 1) as [$from, $to]) {
        expect($from)->toBeLessThanOrEqual($top + 0.01);
        $top = max($top, $to);
    }

    // The bar has to reach every line of the quote, the list at its end
    // included: one segment per line and one per gap, which for the quote
    // above is two lines, a gap, a line, a gap and a line. Taking the x from
    // the block rather than from the quote moved the list's segment and the
    // gap before it one indent to the right, leaving four here.
    expect($spans)->toHaveCount(6);

    // and nothing else vertical alongside it, which is where that second bar
    // would have shown up. The table's column rules are vertical too, but
    // they sit below the quote.
    foreach ($vertical as $segment) {
        if ($segment['to'] <= $bottom || $segment['from'] >= $top) {
            continue;
        }

        expect(abs($segment['x'] - leftMarginPt()))
            ->toBeLessThan(0.5, 'a second bar sits beside the quote at x='.$segment['x']);
    }
});

test('the health certificate badge keeps the same padding above and below its type', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['description' => '', 'contact_email' => null, 'contact_phone' => null]);
    $plan->shifts()->create([
        'title' => 'Shift', 'group' => 0,
        'start' => now(), 'end' => now()->addHour(), 'team_size' => 0,
        'requires_health_certificate' => true,
    ]);

    $content = pdfContent($this->actingAs($owner)->get(route('plan.export.pdf', $plan))->getContent());

    // The frame is drawn as an artifact. Its rounded corners make it a path
    // of lines and curves rather than a single re operator, so the extent
    // comes from the coordinates: every operand in such a block is a point.
    $frame = null;
    preg_match_all('#/Artifact BMC(.*?)EMC#s', $content, $blocks);
    foreach ($blocks[1] as $block) {
        preg_match_all('#(-?[\d.]+)\s+(-?[\d.]+)(?=\s+(?:m|l|c|\d))#', $block, $points, PREG_SET_ORDER);
        if ($points === []) {
            continue;
        }

        $xs = array_map(static fn (array $p): float => (float) $p[1], $points);
        $ys = array_map(static fn (array $p): float => (float) $p[2], $points);

        // the badge is the only artifact at the left margin that is a few
        // millimetres tall; the rules are hairlines spanning the text width
        if (abs(min($xs) - leftMarginPt()) < 0.5 && (max($ys) - min($ys)) > 8.0) {
            $frame = ['top' => max($ys), 'height' => max($ys) - min($ys)];
            break;
        }
    }

    expect($frame)->not->toBeNull('no badge frame found');

    // and the label, the only 8pt text in the document
    expect(preg_match('#/F\d+ 8\.0+ Tf.*?([\d.]+) ([\d.]+) Td#s', $content, $run))->toBe(1);

    $frameTop = $frame['top'];
    $frameHeight = $frame['height'];
    $baseline = (float) $run[2];

    // The box is sized from the type rather than from the line box, which
    // carries leading above the capitals and below the descenders: padding
    // added to that came out at 1.14mm above the capitals against 0.68mm
    // below the "p" of "Gesundheitspass". Read the metrics the renderer reads.
    $metrics = badgeFontMetrics();
    $padding = $frameTop - $baseline - $metrics['cap'];

    // one padding above the capitals, the same below the descender line
    expect($frameTop - ($baseline + $metrics['cap']))->toEqualWithDelta($padding, 0.01);
    expect(($baseline - $metrics['descent']) - ($frameTop - $frameHeight))->toEqualWithDelta($padding, 0.01);

    // which is what makes the height the type's own extent plus the padding
    expect($frameHeight)->toEqualWithDelta($metrics['cap'] + $metrics['descent'] + (2 * $padding), 0.01);
});
