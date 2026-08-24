<?php

use App\Livewire\Plan\Create;
use App\Models\Plan;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login when trying to create a plan', function (): void {
    $this->get(route('plan.create'))->assertRedirect(route('login'));
});

test('creating a plan sets the logged-in user as its owner', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Create::class)
        ->set('title', 'Test plan')
        ->set('description', 'Some description')
        ->set('contact_email', 'contact@example.com')
        ->set('contact_phone', '0123 456789')
        ->call('save')
        ->assertHasNoErrors();

    $plan = Plan::where('title', 'Test plan')->firstOrFail();
    expect($plan->user_id)->toBe($user->id);
    expect($plan->owner_email)->toBe($user->email);
});
