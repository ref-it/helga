<?php

use App\Models\Plan;
use App\Models\Shift;
use App\Models\User;

/**
 * The free slots of a printed plan are also form fields, so the sheet can be
 * filled in on screen instead of by hand. None of this is visible in the
 * rendered page - an empty field prints nothing - so only the PDF objects
 * show whether it is there.
 */

/**
 * The plan as its owner gets it - defined here rather than shared, so this
 * file stands on its own when it is run alone.
 */
function formPdf(Plan $plan, User $owner): string
{
    return test()->actingAs($owner)->get(route('plan.export.pdf', $plan))->getContent();
}

/**
 * The /T names of the form fields in a PDF, in the order they were written.
 *
 * @return list<string>
 */
function formFieldNames(string $pdf): array
{
    preg_match_all('#/Subtype /Widget.*?/T \(([^)]*)\)#s', $pdf, $matches);

    return $matches[1];
}

function planWithFreeSlots(User $owner, bool $clothingSize = false): Shift
{
    $plan = createOwnedPlan($owner);
    $plan->update(['title' => 'Sommerfest']);

    $shift = $plan->shifts()->create([
        'title' => 'Bar Aufbau',
        'group' => 0,
        'start' => '2026-09-13 14:30:00',
        'end' => '2026-09-13 17:30:00',
        'team_size' => 3,
        'requires_clothing_size' => $clothingSize,
    ]);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    return $shift;
}

test('every empty cell becomes a field of its own, taken slot or not', function (): void {
    $owner = User::factory()->create();
    $shift = planWithFreeSlots($owner);

    $names = formFieldNames(formPdf($shift->plan, $owner));

    // Slot 1 is taken but carries neither phone number nor comment, so those
    // two cells are fillable while its name and address are not. The two free
    // slots are fillable throughout - and each value has its own field rather
    // than one line for name, address and number together.
    expect($names)->toBe([
        'shift-'.$shift->id.'-slot-1-phone',
        'shift-'.$shift->id.'-slot-1-comment',
        'shift-'.$shift->id.'-slot-2-name',
        'shift-'.$shift->id.'-slot-2-email',
        'shift-'.$shift->id.'-slot-2-phone',
        'shift-'.$shift->id.'-slot-2-comment',
        'shift-'.$shift->id.'-slot-3-name',
        'shift-'.$shift->id.'-slot-3-email',
        'shift-'.$shift->id.'-slot-3-phone',
        'shift-'.$shift->id.'-slot-3-comment',
    ]);

    // a name twice over would make two cells share one value
    expect($names)->toBe(array_values(array_unique($names)));
});

test('a shift that asks for a clothing size gets a field for it too', function (): void {
    $owner = User::factory()->create();
    $shift = planWithFreeSlots($owner, clothingSize: true);

    expect(formFieldNames(formPdf($shift->plan, $owner)))
        ->toContain('shift-'.$shift->id.'-slot-2-size');
});

test('the fields are reachable as a form and described for a screen reader', function (): void {
    $owner = User::factory()->create();
    $shift = planWithFreeSlots($owner);
    $pdf = formPdf($shift->plan, $owner);

    $widgets = substr_count($pdf, '/Subtype /Widget');
    expect($widgets)->toBe(10);

    // listed in the catalog, or a viewer offers no form at all
    expect(preg_match('#/AcroForm << /Fields \[([^\]]*)\]#', $pdf, $fields))->toBe(1);
    expect(substr_count($fields[1], '0 R'))->toBe($widgets);

    // ISO 14289 asks every widget for a description and for a Form element
    // around it - without the description a reader announces the bare name
    expect(substr_count($pdf, '/TU'))->toBe($widgets);
    expect(substr_count($pdf, '/S /Form'))->toBe($widgets);

    // and the description says which slot of which shift it belongs to
    // (the parentheses travel escaped, as a PDF string literal)
    expect($pdf)->toContain('slot 2 \(Bar Aufbau\)');
});

test('a plan with nothing left to fill in carries no form at all', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = $plan->shifts()->create([
        'title' => 'Bar Aufbau',
        'group' => 0,
        'start' => '2026-09-13 14:30:00',
        'end' => '2026-09-13 17:30:00',
        'team_size' => 1,
    ]);
    $shift->subscriptions()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '0123 456789',
        'comment' => 'Kommt später',
    ]);

    $pdf = formPdf($plan, $owner);

    expect($pdf)->not->toContain('/Subtype /Widget');
    expect($pdf)->not->toContain('/AcroForm');
});

test('a sheet with fields embeds the whole sans, one without keeps the subset', function (): void {
    $owner = User::factory()->create();

    $withFields = formPdf(planWithFreeSlots($owner)->plan, $owner);

    $plan = createOwnedPlan($owner);
    $shift = $plan->shifts()->create([
        'title' => 'Bar Aufbau',
        'group' => 0,
        'start' => '2026-09-13 14:30:00',
        'end' => '2026-09-13 17:30:00',
        'team_size' => 1,
    ]);
    $shift->subscriptions()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '0123 456789',
        'comment' => 'Kommt später',
    ]);
    $withoutFields = formPdf($plan, $owner);

    // A subset embeds only the glyphs the sheet itself uses and is named with
    // a six-letter tag; whatever someone types into a field can be neither,
    // so the sheet that has fields carries the face whole.
    expect($withFields)->toMatch('#/BaseFont /AdwaitaSans[ /]#');
    expect($withoutFields)->not->toMatch('#/BaseFont /AdwaitaSans[ /]#');
    expect($withoutFields)->toMatch('#/BaseFont /[A-Z]{6}\+AdwaitaSans#');
});
