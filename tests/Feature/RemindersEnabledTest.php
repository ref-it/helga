<?php

use App\Console\Commands\NotifySubscribers;
use App\Livewire\Subscription\Create;
use App\Livewire\Subscription\Edit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

test('the notify-subscribers command is a no-op when reminders are disabled', function (): void {
    config(['app.reminders_enabled' => false]);
    $shift = createPlanWithShift();
    $shift->update(['start' => now()->addHours(12), 'notified' => false]);
    $shift->subscriptions()->create([
        'name' => 'Jane Doe', 'email' => 'jane@example.com', 'notification' => true,
        'email_verified_at' => now(),
    ]);

    Artisan::call(NotifySubscribers::class);

    // untouched - so re-enabling the feature later can still catch up
    expect($shift->fresh()->notified)->toBeFalsy();
});

test('the "notify me" checkbox is hidden on the sign-up form when reminders are disabled', function (): void {
    config(['app.reminders_enabled' => false]);
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertDontSee(__('subscription.notifyMe'));
});

test('the "notify me" checkbox is shown on the sign-up form when reminders are enabled', function (): void {
    config(['app.reminders_enabled' => true]);
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertSee(__('subscription.notifyMe'));
});

test('a tampered notification value is ignored on sign-up when reminders are disabled', function (): void {
    config(['app.reminders_enabled' => false, 'captcha.disable' => true]);
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('notification', true)
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    expect($shift->subscriptions()->where('email', 'jane@example.com')->firstOrFail()->notification)->toBeFalsy();
});

test('a tampered notification value is ignored when editing a subscription while reminders are disabled', function (): void {
    config(['app.reminders_enabled' => false]);
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan, 'shift' => $shift, 'subscription' => $subscription])
        ->assertDontSee(__('subscription.notifyMe'))
        ->set('notification', true)
        ->call('save');

    expect($subscription->fresh()->notification)->toBeFalsy();
});
