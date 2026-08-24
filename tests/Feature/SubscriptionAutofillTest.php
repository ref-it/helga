<?php

declare(strict_types=1);

use App\Livewire\Subscription\Create;
use App\Models\User;
use Livewire\Livewire;

test('an anonymous visitor gets an empty subscription form', function (): void {
    $shift = createPlanWithShift();

    Livewire::test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('phone', '');
});

test('a logged-in visitor gets their name and email prefilled', function (): void {
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertSet('name', 'Jane Doe')
        ->assertSet('email', 'jane@example.com');
});

test('a logged-in visitor gets their phone prefilled from their last subscription, still editable', function (): void {
    $firstShift = createPlanWithShift();
    $secondShift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $firstShift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0170 1111111']);
    $secondShift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0170 2222222']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $secondShift->plan, 'shift' => $secondShift])
        ->assertSet('phone', '0170 2222222')
        ->assertSet('phoneFromAccount', false);
});

test('a logged-in visitor gets their phone prefilled and locked when the OIDC provider supplied one', function (): void {
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0170 3333333']);

    // an older, guessed-from-last-subscription phone should be overridden
    // by the authoritative account value
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0170 1111111']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertSet('phone', '0170 3333333')
        ->assertSet('phoneFromAccount', true)
        ->assertSeeHtml('disabled');
});

test('a tampered phone value is ignored on save when it was locked to the account', function (): void {
    config(['captcha.disable' => true]);
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0170 3333333']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('phone', '0170 6666666')
        ->set('captcha', 'anything')
        ->call('save');

    expect($shift->subscriptions()->firstOrFail()->phone)->toBe('0170 3333333');
});

test('a logged-in visitor with no past subscriptions gets an empty phone field', function (): void {
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->assertSet('phone', '');
});

test('the phone field stays editable for a logged-in visitor', function (): void {
    $shift = createPlanWithShift();
    $user = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Create::class, ['plan' => $shift->plan, 'shift' => $shift])
        ->set('phone', '0170 9999999')
        ->assertSet('phone', '0170 9999999');
});
