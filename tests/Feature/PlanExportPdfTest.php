<?php

use App\Models\Plan;
use App\Models\Shift;
use App\Models\ShiftCategory;
use App\Models\User;

test('the owner can export the plan as a pdf', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $response = $this->actingAs($owner)->get(route('plan.export.pdf', $plan));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');

    // shown in the browser's viewer rather than dropped into the download
    // folder, with the filename still there for the viewer's save button
    expect($response->headers->get('content-disposition'))->toStartWith('inline;');
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
});

test('an unrelated user cannot export the plan as a pdf', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('plan.export.pdf', $plan))->assertForbidden();
});

test('guests are redirected to login', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $this->get(route('plan.export.pdf', $plan))->assertRedirect(route('login'));
});

/**
 * How many shift tables a rendered sheet holds - one per shift, so this is
 * what says which shifts made it onto the sheet.
 */
function shiftTableCount(string $pdf): int
{
    return preg_match_all('#/Type /StructElem /S /Table[^A-Za-z]#', $pdf);
}

/**
 * A shift of $plan, in $category (the raw type value) unless left empty.
 */
function exportShift(Plan $plan, string $title, string $category = ''): Shift
{
    return $plan->shifts()->create([
        'title' => $title,
        'group' => 0,
        'type' => $category,
        'start' => '2026-09-13 14:30:00',
        'end' => '2026-09-13 17:30:00',
        'team_size' => 1,
    ]);
}

test('the owner can export a single shift as a pdf', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $first = exportShift($plan, 'Bar Aufbau');
    exportShift($plan, 'Bar Abbau');

    $whole = $this->actingAs($owner)->get(route('plan.export.pdf', $plan));
    $single = $this->actingAs($owner)->get(route('plan.shift.export.pdf', ['plan' => $plan, 'shift' => $first]));

    $single->assertOk();
    expect($single->headers->get('content-type'))->toBe('application/pdf');
    expect($single->headers->get('content-disposition'))->toStartWith('inline;');

    // the sheet carries that one shift, where the whole plan carries both
    expect(shiftTableCount($whole->getContent()))->toBe(2);
    expect(shiftTableCount($single->getContent()))->toBe(1);
});

test('the owner can export one category as a pdf', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $bar = ShiftCategory::create(['plan_id' => $plan->id, 'name' => 'Bar']);
    $kitchen = ShiftCategory::create(['plan_id' => $plan->id, 'name' => 'Küche']);

    exportShift($plan, 'Bar Aufbau', (string) $bar->id);
    exportShift($plan, 'Bar Abbau', (string) $bar->id);
    exportShift($plan, 'Spülen', (string) $kitchen->id);

    $response = $this->actingAs($owner)
        ->get(route('plan.category.export.pdf', ['plan' => $plan, 'category' => $bar]));

    $response->assertOk();
    $pdf = $response->getContent();

    // the category's two shifts, not the third one
    expect(shiftTableCount($pdf))->toBe(2);

    // and the category heading above them, so the sheet says which one it is
    expect(preg_match_all('#/Type /StructElem /S /H2[^0-9]#', $pdf))->toBe(1);
});

test('a category without shifts has nothing to export', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $empty = ShiftCategory::create(['plan_id' => $plan->id, 'name' => 'Leer']);

    $this->actingAs($owner)
        ->get(route('plan.category.export.pdf', ['plan' => $plan, 'category' => $empty]))
        ->assertNotFound();
});

test('a shift or category of another plan is not exported under this one', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $other = createOwnedPlan($owner);
    $shift = exportShift($other, 'Fremde Schicht');
    $category = ShiftCategory::create(['plan_id' => $other->id, 'name' => 'Fremd']);

    // the ids travel in the URL, so belonging to the plan is checked, not assumed
    $this->actingAs($owner)
        ->get(route('plan.shift.export.pdf', ['plan' => $plan, 'shift' => $shift]))
        ->assertNotFound();

    $this->actingAs($owner)
        ->get(route('plan.category.export.pdf', ['plan' => $plan, 'category' => $category]))
        ->assertNotFound();
});

test('an unrelated user cannot export a shift or a category as a pdf', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = exportShift($plan, 'Bar Aufbau');
    $category = ShiftCategory::create(['plan_id' => $plan->id, 'name' => 'Bar']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('plan.shift.export.pdf', ['plan' => $plan, 'shift' => $shift]))
        ->assertForbidden();

    $this->actingAs($stranger)
        ->get(route('plan.category.export.pdf', ['plan' => $plan, 'category' => $category]))
        ->assertForbidden();
});
