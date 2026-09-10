<?php

use App\Models\Plan;
use App\Models\ShiftCategory;
use App\Models\User;

/**
 * Guards the structure-tree properties that PDF/UA validation turns on. Each
 * of these was a real veraPDF failure, and none of them is visible in the
 * rendered page - only in the tag tree - so nothing else would catch a
 * regression.
 *
 * These assert the tree, not conformance as a whole: a full check needs
 * veraPDF (`-f ua2`), which is not a test dependency.
 */
function exportedPdf(Plan $plan, User $owner): string
{
    return test()->actingAs($owner)->get(route('plan.export.pdf', $plan))->getContent();
}

/**
 * The /S values of the structure elements, in the order they appear.
 *
 * @return list<string>
 */
function structTags(string $pdf): array
{
    preg_match_all('#/Type\s*/StructElem\s*/S\s*/(\w+)#', $pdf, $matches);

    return $matches[1];
}

/**
 * The structure elements keyed by their object number, each with its tag and
 * the object number of its parent - enough to walk the tree.
 *
 * @return array<int, array{tag: string, parent: int}>
 */
function structTagsByObject(string $pdf): array
{
    preg_match_all(
        '#(\d+) 0 obj\s*<< /Type /StructElem /S /(\w+).{0,300}?/P (\d+) 0 R#s',
        $pdf,
        $matches,
        PREG_SET_ORDER,
    );

    $elements = [];
    foreach ($matches as $match) {
        $elements[(int) $match[1]] = ['tag' => $match[2], 'parent' => (int) $match[3]];
    }

    return $elements;
}

test('the export declares PDF/UA-2', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);

    $pdf = exportedPdf($plan, $owner);

    // UA-2 is a PDF 2.0 standard, and the declaration in the XMP metadata is
    // what a validator picks its profile by - the two have to move together
    // or the file claims a part it was never checked against
    expect($pdf)->toStartWith('%PDF-2.0');
    expect($pdf)->toContain('<pdfuaid:part>2</pdfuaid:part>');
    // the PDF 2.0 structure namespace has to reach the tag tree as well
    expect($pdf)->toContain('/Type /Namespace');
});

test('table header cells declare Scope under the Table attribute owner', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $pdf = exportedPdf($plan, $owner);

    preg_match_all('#/Type\s*/StructElem\s*/S\s*/TH(.{0,200}?)/K#s', $pdf, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $th) {
        // without /O the attribute is not read as a table attribute at all,
        // and veraPDF reports the column headers as not determinable
        expect($th)->toContain('/O /Table');
        expect($th)->toContain('/Scope /Column');
    }
});

test('a plan that mixes categorised and uncategorised shifts skips no heading level', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Bar', 'plan_id' => $plan->id]);
    createShiftForPlan($plan)->update(['type' => (string) $category->id, 'title' => 'Categorised']);
    // uncategorised shifts sort first, so this one's heading follows the H1 directly
    createShiftForPlan($plan)->update(['title' => 'Uncategorised']);

    $headings = array_values(array_filter(
        structTags(exportedPdf($plan, $owner)),
        fn (string $tag): bool => preg_match('/^H\d$/', $tag) === 1,
    ));

    expect($headings[0])->toBe('H1');
    // H1 > H2 (the uncategorised shift) > H2 (the category) > H3 (its shift)
    expect($headings)->toBe(['H1', 'H2', 'H2', 'H3']);
});

test('a plain-text description is wrapped in a paragraph rather than left as loose content', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    // descriptions saved before the rich-text editor carry no block element,
    // and addHTMLCell() renders those as marked content without a structure
    // element of their own
    $plan->update(['description' => 'A description with no block element.']);
    createShiftForPlan($plan)->update(['description' => 'A shift description with none either.']);

    $pdf = exportedPdf($plan, $owner);

    // Document is a grouping element: it may contain other elements but no
    // marked content of its own (PDF/UA-2, Table 5)
    preg_match_all('#/Type\s*/StructElem\s*/S\s*/Document.{0,400}?/K\s*\[(.*?)\]#s', $pdf, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $kids) {
        expect($kids)->not->toContain('/MCR');
    }
});

test('a description that brings its own blocks is not wrapped again', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['description' => '<p>A paragraph.</p><ul><li>An item</li></ul>']);
    createShiftForPlan($plan)->update(['description' => '<p>A shift description.</p>']);

    $tags = structTagsByObject(exportedPdf($plan, $owner));

    expect($tags)->not->toBeEmpty();
    // A P holds content, not other block-level elements: wrapping the whole
    // cell in a tag would nest P in P and put the list inside a P. Both have
    // to stay siblings, so nothing may have a P for a parent.
    foreach ($tags as ['tag' => $tag, 'parent' => $parent]) {
        expect($tags[$parent]['tag'] ?? null)->not->toBe('P', "a {$tag} has a P for a parent");
    }
});
