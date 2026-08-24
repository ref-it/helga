<?php

use App\Livewire\Plan\Admin;
use App\Models\User;
use Livewire\Livewire;

test('unpublished plans do not show up on the home page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    expect($plan->fresh()->published)->toBeFalse();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee($plan->title);
});

test('published plans show up on the home page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['published' => true, 'active' => true]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($plan->title);
});

test('a published but inactive plan does not show up on the home page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['published' => true, 'active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee($plan->title);
});

test('the owner can publish and unpublish a plan from the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->assertSet('plan.published', false)
        ->call('togglePublished');

    expect($plan->fresh()->published)->toBeTrue();

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan->fresh()])
        ->call('togglePublished');

    expect($plan->fresh()->published)->toBeFalse();
});

test('a shared-group member can also toggle publish state', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);
    $plan->sharedGroups()->create(['group' => 'helpers']);
    $member = User::factory()->create(['groups' => ['helpers']]);

    Livewire::actingAs($member)->test(Admin::class, ['plan' => $plan])
        ->call('togglePublished');

    expect($plan->fresh()->published)->toBeTrue();
});

test('an unrelated user cannot toggle publish state', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)->test(Admin::class, ['plan' => $plan])
        ->call('togglePublished')
        ->assertForbidden();

    expect($plan->fresh()->published)->toBeFalse();
});
