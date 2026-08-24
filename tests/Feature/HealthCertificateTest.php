<?php

use App\Livewire\Shift\Create as ShiftCreate;
use App\Livewire\Subscription\Create as SubscriptionCreate;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['captcha.disable' => true]);
});

test('a shift can be created with the health certificate requirement enabled', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(ShiftCreate::class, ['plan' => $plan])
        ->set('title', 'Food stall')
        ->set('description', 'Desc')
        ->set('start', now()->toDateTimeString())
        ->set('end', now()->addHour()->toDateTimeString())
        ->set('team_size', 2)
        ->set('requires_health_certificate', true)
        ->call('save')
        ->assertHasNoErrors();

    $shift = $plan->shifts()->where('title', 'Food stall')->firstOrFail();
    expect($shift->requires_health_certificate)->toBeTrue();
});

test('a shift defaults to not requiring a health certificate', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(ShiftCreate::class, ['plan' => $plan])
        ->set('title', 'Regular shift')
        ->set('description', 'Desc')
        ->set('start', now()->toDateTimeString())
        ->set('end', now()->addHour()->toDateTimeString())
        ->set('team_size', 2)
        ->call('save')
        ->assertHasNoErrors();

    $shift = $plan->shifts()->where('title', 'Regular shift')->firstOrFail();
    expect($shift->requires_health_certificate)->toBeFalse();
});

test('signing up for a shift that requires a health certificate fails without confirming it', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['requires_health_certificate' => true]);

    Livewire::test(SubscriptionCreate::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasErrors(['health_certificate_confirmed' => 'accepted']);

    expect($shift->subscriptions()->count())->toBe(0);
});

test('signing up for a shift that requires a health certificate succeeds once confirmed', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['requires_health_certificate' => true]);

    Livewire::test(SubscriptionCreate::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('health_certificate_confirmed', true)
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    $subscription = $shift->subscriptions()->where('email', 'jane@example.com')->firstOrFail();
    expect($subscription->health_certificate_confirmed)->toBeTrue();
});

test('signing up for a shift that does not require a health certificate needs no confirmation', function (): void {
    $shift = createPlanWithShift();

    Livewire::test(SubscriptionCreate::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    $subscription = $shift->subscriptions()->where('email', 'jane@example.com')->firstOrFail();
    expect($subscription->health_certificate_confirmed)->toBeFalse();
});

test('the health certificate badge shows on the public plan page for shifts that require it', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['published' => true]);
    $shift->update(['requires_health_certificate' => true]);

    $this->get(route('plan.show', $shift->plan))
        ->assertOk()
        ->assertSee(__('shift.healthCertificateRequired'));
});

test('the health certificate badge is hidden on the public plan page for shifts that do not require it', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['published' => true]);

    $this->get(route('plan.show', $shift->plan))
        ->assertOk()
        ->assertDontSee(__('shift.healthCertificateRequired'));
});
