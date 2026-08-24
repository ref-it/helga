<?php

use App\Models\User;

test('the owner can reach the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $this->actingAs($owner)->get(route('plan.manage', $plan))->assertOk();
});

test('a member of a shared group can reach the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers']);

    $member = User::factory()->create(['groups' => ['helpers']]);

    $this->actingAs($member)->get(route('plan.manage', $plan))->assertOk();
});

test('an unrelated user cannot reach the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $stranger = User::factory()->create(['groups' => ['some-other-group']]);

    $this->actingAs($stranger)->get(route('plan.manage', $plan))->assertForbidden();
});
