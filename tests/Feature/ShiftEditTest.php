<?php

use App\Livewire\Shift\Edit;
use App\Models\ShiftCategory;
use App\Models\User;
use Livewire\Livewire;

test('the owner can reach the edit page and sees the current values prefilled', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan, 'shift' => $shift])
        ->assertSet('title', $shift->title)
        ->assertSet('description', $shift->description)
        ->assertSet('team_size', $shift->team_size);

    $this->actingAs($owner)->get(route('plan.shift.edit', ['plan' => $plan, 'shift' => $shift]))->assertOk();
});

test('an unrelated user cannot reach the edit page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('plan.shift.edit', ['plan' => $plan, 'shift' => $shift]))->assertForbidden();
});

test('saving updates the shift, including a newly created category', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan, 'shift' => $shift])
        ->set('title', 'Updated title')
        ->set('searchCategory', 'Bar')
        ->call('createCategory')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('plan.manage', $plan));

    expect($shift->fresh()->title)->toBe('Updated title');
    expect(ShiftCategory::where('plan_id', $plan->id)->where('name', 'Bar')->exists())->toBeTrue();
});
