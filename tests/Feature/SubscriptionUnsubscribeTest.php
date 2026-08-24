<?php

use App\Livewire\Subscription\ConfirmRemove;
use App\Models\User;
use Livewire\Livewire;

test('a guest is redirected to login when unsubscribing themselves', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);

    $this->delete(route('plan.subscription.unsubscribeSelf', ['plan' => $shift->plan->view_id, 'shift' => $shift]))
        ->assertRedirect(route('login'));
});

test('a logged-in user can unsubscribe themselves from a shift', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs($user)
        ->delete(route('plan.subscription.unsubscribeSelf', ['plan' => $shift->plan->view_id, 'shift' => $shift]))
        ->assertRedirect(route('plan.show', ['plan' => $shift->plan]));

    expect($shift->subscriptions()->whereKey($subscription->id)->exists())->toBeFalse();
});

test('unsubscribing only removes the logged-in user\'s own subscription', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $mine = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $someoneElse = $shift->subscriptions()->create(['name' => 'John Doe', 'email' => 'john@example.com']);

    $this->actingAs($user)
        ->delete(route('plan.subscription.unsubscribeSelf', ['plan' => $shift->plan->view_id, 'shift' => $shift]));

    expect($shift->subscriptions()->whereKey($mine->id)->exists())->toBeFalse();
    expect($shift->subscriptions()->whereKey($someoneElse->id)->exists())->toBeTrue();
});

test('a logged-in user cannot unsubscribe when the plan disallows it', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs($user)
        ->delete(route('plan.subscription.unsubscribeSelf', ['plan' => $shift->plan->view_id, 'shift' => $shift]))
        ->assertForbidden();

    expect($shift->subscriptions()->whereKey($subscription->id)->exists())->toBeTrue();
});

test('a logged-in user cannot unsubscribe within 2 days of the shift start', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addHour(), 'end' => now()->addHours(2)]);
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs($user)
        ->delete(route('plan.subscription.unsubscribeSelf', ['plan' => $shift->plan->view_id, 'shift' => $shift]))
        ->assertForbidden();

    expect($shift->subscriptions()->whereKey($subscription->id)->exists())->toBeTrue();
});

test('a guest can reach the emailed confirmation-link removal page', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);

    $this->get(route('plan.subscription.confirmRemove', ['plan' => $shift->plan->view_id, 'shift' => $shift, 'confirmation' => 'tok123']))
        ->assertOk();
});

test('confirming removal via the emailed link deletes the subscription when the plan allows unsubscribing', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    // 'confirmation' is intentionally not mass-assignable (mirrors how
    // Subscription::sendEmailVerification() sets it in production)
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $subscription->confirmation = 'tok123';
    $subscription->save();

    Livewire::test(ConfirmRemove::class, ['plan' => $shift->plan, 'shift' => $shift, 'confirmation' => 'tok123'])
        ->call('confirm')
        ->assertRedirect(route('plan.show', ['plan' => $shift->plan]));

    expect($shift->subscriptions()->whereKey($subscription->id)->exists())->toBeFalse();
});

test('confirming removal is a no-op when the plan does not allow unsubscribing', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    // 'confirmation' is intentionally not mass-assignable (mirrors how
    // Subscription::sendEmailVerification() sets it in production)
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $subscription->confirmation = 'tok123';
    $subscription->save();

    Livewire::test(ConfirmRemove::class, ['plan' => $shift->plan, 'shift' => $shift, 'confirmation' => 'tok123'])
        ->call('confirm');

    expect($shift->subscriptions()->whereKey($subscription->id)->exists())->toBeTrue();
});

test('confirming removal is a no-op within 2 days of the shift start', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addHour(), 'end' => now()->addHours(2)]);
    // 'confirmation' is intentionally not mass-assignable (mirrors how
    // Subscription::sendEmailVerification() sets it in production)
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $subscription->confirmation = 'tok123';
    $subscription->save();

    Livewire::test(ConfirmRemove::class, ['plan' => $shift->plan, 'shift' => $shift, 'confirmation' => 'tok123'])
        ->call('confirm');

    expect($shift->subscriptions()->whereKey($subscription->id)->exists())->toBeTrue();
});

test('a mismatched confirmation token does not delete any subscription', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    // 'confirmation' is intentionally not mass-assignable (mirrors how
    // Subscription::sendEmailVerification() sets it in production)
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $subscription->confirmation = 'tok123';
    $subscription->save();

    Livewire::test(ConfirmRemove::class, ['plan' => $shift->plan, 'shift' => $shift, 'confirmation' => 'wrong-token'])
        ->call('confirm');

    expect($shift->subscriptions()->whereKey($subscription->id)->exists())->toBeTrue();
});
