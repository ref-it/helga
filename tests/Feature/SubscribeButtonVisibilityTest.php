<?php

use App\Models\User;

test('the subscribe button is hidden for a shift the logged-in user already joined', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true]);
    $shift = createShiftForPlan($plan);
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $shift->subscriptions()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $this->actingAs($user)->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee(__('plan.subscribe'));
});

test('the subscribe button still shows for a shift the logged-in user has not joined', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true]);
    $shift = createShiftForPlan($plan);
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $shift->subscriptions()->create(['name' => 'Someone else', 'email' => 'other@example.com']);

    $this->actingAs($user)->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee(__('plan.subscribe'));
});

test('the unsubscribe button is hidden from a guest even when unsubscribing is allowed', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true, 'allow_unsubscribe' => true]);
    $shift = createShiftForPlan($plan);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $shift->subscriptions()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $this->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee(__('subscription.unsubscribe'));
});

test('the unsubscribe button shows for the matching logged-in subscriber when unsubscribing is allowed', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true, 'allow_unsubscribe' => true]);
    $shift = createShiftForPlan($plan);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $shift->subscriptions()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
    $user = User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($user)->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee(__('subscription.unsubscribe'));
});

test('the unsubscribe button is hidden from a logged-in visitor who is not subscribed to the shift', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->update(['published' => true, 'active' => true, 'allow_unsubscribe' => true]);
    $shift = createShiftForPlan($plan);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $shift->subscriptions()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $this->actingAs(User::factory()->create())->get(route('plan.show', $plan))
        ->assertOk()
        ->assertDontSee(__('subscription.unsubscribe'));
});
