<?php

use App\Models\Plan;
use App\Models\Shift;
use App\Models\User;

test('the owner can delete their plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);

    $this->actingAs($owner)
        ->delete(route('plan.destroy', $plan))
        ->assertRedirect(route('home'));

    expect(Plan::find($plan->id))->toBeNull();
    expect(Shift::find($shift->id))->toBeNull();
});

test('a shared-group manager cannot delete the plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers']);
    $member = User::factory()->create(['groups' => ['helpers']]);

    $this->actingAs($member)
        ->delete(route('plan.destroy', $plan))
        ->assertForbidden();

    expect(Plan::find($plan->id))->not->toBeNull();
});

test('an unrelated user cannot delete the plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $unrelated = User::factory()->create();

    $this->actingAs($unrelated)
        ->delete(route('plan.destroy', $plan))
        ->assertForbidden();

    expect(Plan::find($plan->id))->not->toBeNull();
});

test('a guest is redirected to login', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $this->delete(route('plan.destroy', $plan))
        ->assertRedirect(route('login'));

    expect(Plan::find($plan->id))->not->toBeNull();
});
