<?php

use App\Models\PlanShare;
use App\Models\User;

test('the owner sees a link to the admin page on the public show page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);

    $this->actingAs($owner)->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee(__('plan.admin'));
});

test('a shared manage-group member sees a link to the admin page on the public show page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);
    $plan->sharedGroups()->create(['group' => 'managers', 'access' => PlanShare::MANAGE]);
    $member = User::factory()->create(['groups' => ['managers']]);

    $this->actingAs($member)->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee(__('plan.admin'));
});

test('a shared read-only group member does not see a link to the admin page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    $this->actingAs($member)->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee(__('plan.admin'));
});

test('a guest does not see a link to the admin page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);

    $this->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee(__('plan.admin'));
});

test('an unrelated logged-in user does not see a link to the admin page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee(__('plan.admin'));
});
