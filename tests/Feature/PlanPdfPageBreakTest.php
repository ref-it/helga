<?php

use App\Models\ShiftCategory;
use App\Models\User;

/**
 * The structure elements of a PDF, grouped by the page they sit on and kept in
 * document order within it.
 *
 * @return array<int, list<array{order: int, tag: string}>>
 */
function structElementsByPage(string $pdf): array
{
    preg_match_all(
        '#(\d+) 0 obj\s*<< /Type /StructElem /S /(\w+).{0,200}?/Pg (\d+) 0 R#s',
        $pdf,
        $matches,
        PREG_SET_ORDER,
    );

    $pages = [];
    foreach ($matches as $match) {
        $pages[(int) $match[3]][] = ['order' => (int) $match[1], 'tag' => $match[2]];
    }

    foreach ($pages as &$elements) {
        usort($elements, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);
    }

    return $pages;
}

/**
 * The pages whose last heading has no table row after it - a heading left
 * standing at the foot of a page with its content on the next one.
 *
 * @return list<int>
 */
function pagesWithStrandedHeading(string $pdf): array
{
    $stranded = [];
    foreach (structElementsByPage($pdf) as $page => $elements) {
        $headings = [];
        $rows = [];
        foreach ($elements as $element) {
            if (preg_match('/^H[23]$/', $element['tag']) === 1) {
                $headings[] = $element['order'];
            } elseif ($element['tag'] === 'TR') {
                $rows[] = $element['order'];
            }
        }

        if ($headings !== [] && ($rows === [] || max($headings) > max($rows))) {
            $stranded[] = $page;
        }
    }

    return $stranded;
}

test('no heading is left standing at the foot of a page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['description' => '']);

    $categories = [];
    foreach (['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'] as $name) {
        $categories[] = ShiftCategory::create(['name' => $name, 'plan_id' => $plan->id]);
    }

    // enough shifts of differing height that headings land at every offset
    // from the bottom margin, with the three things that are drawn after the
    // space check and used to push the table off the page: a category above
    // the shift, a badge and a description
    for ($i = 0; $i < 42; $i++) {
        $shift = $plan->shifts()->create([
            'title' => "Schicht {$i}",
            'group' => 0,
            'start' => now()->addHours($i * 3),
            'end' => now()->addHours($i * 3 + 2),
            'team_size' => ($i % 3) + 1,
            'requires_health_certificate' => $i % 7 === 0,
            'description' => $i % 4 === 0 ? 'Eine kurze Beschreibung zur Schicht.' : '',
        ]);
        $shift->update(['type' => (string) $categories[intdiv($i, 7)]->id]);
    }

    $pdf = $this->actingAs($owner)->get(route('plan.export.pdf', $plan))->getContent();

    expect(structElementsByPage($pdf))->not->toBeEmpty();
    expect(pagesWithStrandedHeading($pdf))->toBe([]);
});

test('a plain shift heading is not left standing either', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['description' => '', 'contact_email' => null, 'contact_phone' => null]);

    // No category, no badge, no description: nothing but the heading, the
    // table head and the rows. This isolates the row height, which the space
    // check used to take from ROW_MIN_HEIGHT - a floor a row clears by some
    // 7mm, because the name cell always stacks three lines.
    for ($i = 0; $i < 40; $i++) {
        $plan->shifts()->create([
            'title' => "Schicht {$i}",
            'group' => 0,
            'start' => now()->addHours($i * 3),
            'end' => now()->addHours($i * 3 + 2),
            'team_size' => ($i % 3) + 1,
        ]);
    }

    $pdf = $this->actingAs($owner)->get(route('plan.export.pdf', $plan))->getContent();

    expect(structElementsByPage($pdf))->not->toBeEmpty();
    expect(pagesWithStrandedHeading($pdf))->toBe([]);
});
