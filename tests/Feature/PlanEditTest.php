<?php

use App\Livewire\Plan\Edit;
use App\Models\User;
use Livewire\Livewire;

test('the owner can reach the edit page and sees the current values prefilled', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan])
        ->assertSet('title', $plan->title)
        ->assertSet('description', $plan->description)
        ->assertSet('owner_email', $plan->owner_email)
        ->assertSet('contact_email', $plan->contact_email)
        ->assertSet('contact_phone', $plan->contact_phone);

    $this->actingAs($owner)->get(route('plan.edit', $plan))->assertOk();
});

test('an unrelated user cannot reach the edit page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $stranger = User::factory()->create(['groups' => ['some-other-group']]);

    $this->actingAs($stranger)->get(route('plan.edit', $plan))->assertForbidden();
});

test('saving updates the plan and redirects to the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan])
        ->set('title', 'Updated title')
        ->set('contact_email', 'new-contact@example.com')
        ->set('contact_phone', '9876 543210')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('plan.manage', $plan));

    expect($plan->fresh()->title)->toBe('Updated title');
    expect($plan->fresh()->contact_email)->toBe('new-contact@example.com');
    expect($plan->fresh()->contact_phone)->toBe('9876 543210');
});
