<?php

use App\Livewire\Subscription\Mine;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function (): void {
    $this->get(route('subscription.mine'))->assertRedirect(route('login'));
});

test('a logged-in user sees shifts signed up under their email', function (): void {
    $shift = createPlanWithShift();
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $user = User::factory()->create(['email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Mine::class)
        ->assertOk()
        ->assertSee($shift->plan->title)
        ->assertSee($shift->title);
});

test('a logged-in user does not see shifts signed up under a different email', function (): void {
    $shift = createPlanWithShift();
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $user = User::factory()->create(['email' => 'someone-else@example.com']);

    Livewire::actingAs($user)->test(Mine::class)
        ->assertOk()
        ->assertDontSee($shift->plan->title);
});

test('each plan is grouped into its own fieldset', function (): void {
    $shift = createPlanWithShift();
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $user = User::factory()->create(['email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Mine::class)->assertOk()->assertSeeHtml('data-flux-fieldset');
});

test('the unsubscribe button shows when the plan allows it and the shift is far enough out', function (): void {
    $shift = createPlanWithShift();
    $shift->plan->update(['allow_unsubscribe' => true]);
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $user = User::factory()->create(['email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Mine::class)
        ->assertOk()
        ->assertSee(__('subscription.unsubscribe'));
});

test('the unsubscribe button is hidden when the plan does not allow it', function (): void {
    $shift = createPlanWithShift();
    $shift->update(['start' => now()->addDays(5), 'end' => now()->addDays(5)->addHour()]);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $user = User::factory()->create(['email' => 'jane@example.com']);

    Livewire::actingAs($user)->test(Mine::class)
        ->assertOk()
        ->assertDontSee(__('subscription.unsubscribe'));
});
