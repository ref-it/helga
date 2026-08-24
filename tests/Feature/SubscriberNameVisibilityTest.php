<?php

use App\Models\User;

test('subscriber names are never shown to a guest, even when the owner opted in', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true, 'show_subscriber_names' => true]);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee('Jane Doe');
});

test('subscriber names are hidden from a logged-in visitor by default', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true]);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs(User::factory()->create())
        ->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee('Jane Doe');
});

test('subscriber names are shown to a logged-in visitor once the owner opts in', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true, 'show_subscriber_names' => true]);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs(User::factory()->create())
        ->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee('Jane Doe');
});
