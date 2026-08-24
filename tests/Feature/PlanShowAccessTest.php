<?php

use App\Livewire\Subscription\Create as SubscriptionCreate;
use App\Models\User;
use Livewire\Livewire;

test('a guest cannot view an inactive plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $this->get(route('plan.show', $plan))->assertForbidden();
});

test('the owner can preview an inactive plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $this->actingAs($owner)->get(route('plan.show', $plan))->assertOk();
});

test('a shared-group member can preview an inactive plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers']);
    $member = User::factory()->create(['groups' => ['helpers']]);

    $this->actingAs($member)->get(route('plan.show', $plan))->assertOk();
});

test('an unrelated logged-in user cannot view an inactive plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('plan.show', $plan))->assertForbidden();
});

test('anyone can view an active plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);

    $this->get(route('plan.show', $plan))->assertOk();
});

test('an active but unpublished plan is still reachable via its direct link', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true, 'published' => false]);

    $this->get(route('plan.show', $plan))->assertOk();
});

test('a published but inactive plan is not reachable by a guest', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => false, 'published' => true]);

    $this->get(route('plan.show', $plan))->assertForbidden();
});

test('a guest cannot subscribe to a shift of an inactive plan', function (): void {
    $shift = createShiftForPlan(createOwnedPlan(User::factory()->create()));

    Livewire::test(SubscriptionCreate::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertForbidden();
});
