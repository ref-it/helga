<?php

use App\Models\User;

test('a shared-group member can create, update and delete shifts', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers']);
    $member = User::factory()->create(['groups' => ['helpers']]);

    $this->actingAs($member)
        ->post(route('plan.shift.store', $plan), [
            'title' => 'New shift',
            'description' => 'Desc',
            'start' => now()->toDateTimeString(),
            'end' => now()->addHour()->toDateTimeString(),
            'team_size' => 1,
            'group' => 0,
        ])
        ->assertRedirect(route('plan.manage', $plan));

    $shift = $plan->shifts()->where('title', 'New shift')->firstOrFail();

    $this->actingAs($member)
        ->delete(route('plan.shift.destroy', ['plan' => $plan, 'shift' => $shift]))
        ->assertRedirect(route('plan.manage', $plan));

    expect($plan->shifts()->whereKey($shift->id)->exists())->toBeFalse();
});

test('an unrelated user cannot create shifts on a plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('plan.shift.store', $plan), [
            'title' => 'New shift',
            'description' => 'Desc',
            'start' => now()->toDateTimeString(),
            'end' => now()->addHour()->toDateTimeString(),
            'team_size' => 1,
            'group' => 0,
        ])
        ->assertForbidden();
});

test('a shared-group member cannot delete the whole plan, only the owner can', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers']);
    $member = User::factory()->create(['groups' => ['helpers']]);

    expect($member->can('forceDelete', $plan))->toBeFalse();
    expect($owner->can('forceDelete', $plan))->toBeTrue();
});
