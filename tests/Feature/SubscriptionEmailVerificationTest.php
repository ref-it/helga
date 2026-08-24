<?php

use App\Livewire\Subscription\Create;
use App\Models\User;
use App\Notifications\SendEmailVerification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['captcha.disable' => true]);
});

test('an anonymous visitor must provide an email address', function (): void {
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', '')
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasErrors(['email' => 'required']);

    expect($shift->subscriptions()->count())->toBe(0);
});

test('an anonymous visitor signing up gets a verification email and starts unverified', function (): void {
    Notification::fake();
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    $subscription = $shift->subscriptions()->where('email', 'jane@example.com')->firstOrFail();

    expect($subscription->isEmailVerified())->toBeFalse();
    Notification::assertSentTo($subscription, SendEmailVerification::class);
});

test('a logged-in visitor is automatically verified using their account email', function (): void {
    Notification::fake();
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    $subscription = $shift->subscriptions()->where('email', 'jane@example.com')->firstOrFail();

    expect($subscription->isEmailVerified())->toBeTrue();
    Notification::assertNothingSent();
});

test('a logged-in visitor cannot submit a different name or email than their account', function (): void {
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Someone Else')
        ->set('email', 'someone-else@example.com')
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    $subscription = $shift->subscriptions()->firstOrFail();

    expect($subscription->name)->toBe('Jane Doe');
    expect($subscription->email)->toBe('jane@example.com');
});

test('visiting a valid signed verification link marks the subscription verified', function (): void {
    $shift = createPlanWithShift();
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $link = URL::temporarySignedRoute('plan.subscription.verifyEmail', now()->addDays(7), [
        'plan' => $shift->plan,
        'shift' => $shift,
        'subscription' => $subscription,
    ]);

    $this->get($link)->assertRedirect(route('plan.show', $shift->plan));

    expect($subscription->fresh()->isEmailVerified())->toBeTrue();
});

test('the verification email includes an unsubscribe link when the plan allows unsubscribing', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save');

    $subscription = $shift->subscriptions()->where('email', 'jane@example.com')->firstOrFail();
    $mail = (new SendEmailVerification('https://example.test/verify', 'https://example.test/unsubscribe'))->toMail($subscription);

    expect($subscription->confirmation)->not->toBeNull();
    expect($mail->actionUrl)->toBe('https://example.test/verify');
    expect(collect($mail->outroLines)->contains(fn ($line): bool => str_contains($line, 'https://example.test/unsubscribe')))->toBeTrue();
});

test('the verification email has no unsubscribe link when the plan disallows unsubscribing', function (): void {
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save');

    $subscription = $shift->subscriptions()->where('email', 'jane@example.com')->firstOrFail();

    expect($subscription->confirmation)->toBeNull();
});

test('a tampered verification link is rejected', function (): void {
    $shift = createPlanWithShift();
    $subscription = $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $link = URL::temporarySignedRoute('plan.subscription.verifyEmail', now()->addDays(7), [
        'plan' => $shift->plan,
        'shift' => $shift,
        'subscription' => $subscription,
    ]);

    $this->get($link.'&tampered=1')->assertForbidden();

    expect($subscription->fresh()->isEmailVerified())->toBeFalse();
});
