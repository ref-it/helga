<?php

use App\Livewire\Subscription\Create;
use App\Models\User;
use Livewire\Livewire;

test('signing up for a shift without a captcha answer fails validation', function (): void {
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('captcha', '')
        ->call('save')
        ->assertHasErrors(['captcha' => 'required']);

    expect($shift->subscriptions()->count())->toBe(0);
});

test('signing up for a shift succeeds once the captcha is answered correctly', function (): void {
    config(['captcha.disable' => true]);
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('captcha', 'anything')
        ->call('save')
        ->assertHasNoErrors();

    expect($shift->subscriptions()->where('name', 'Jane Doe')->exists())->toBeTrue();
});

test('a logged-in visitor is not shown a captcha challenge', function (): void {
    $shift = createPlanWithShift();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertDontSee(__('subscription.captcha'));
});

test('a logged-in visitor can sign up without answering a captcha', function (): void {
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->call('save')
        ->assertHasNoErrors();

    expect($shift->subscriptions()->where('email', 'jane@example.com')->exists())->toBeTrue();
});
