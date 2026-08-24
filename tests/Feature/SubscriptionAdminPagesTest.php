<?php

use App\Livewire\Subscription\Edit;
use App\Models\User;
use Livewire\Livewire;

test('the owner can edit an existing subscription', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan, 'shift' => $shift, 'subscription' => $subscription])
        ->assertSet('name', 'Jane Doe')
        ->set('name', 'Jane Smith')
        ->call('save')
        ->assertHasNoErrors();

    expect($subscription->fresh()->name)->toBe('Jane Smith');
});

test('an unrelated user cannot edit a subscription', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('plan.shift.subscription.edit', ['plan' => $plan, 'shift' => $shift, 'subscription' => $subscription]))
        ->assertForbidden();
});
