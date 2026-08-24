<?php

use App\Livewire\Plan\Admin;
use App\Models\User;
use Livewire\Livewire;

test('the owner can activate and deactivate a plan from the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->assertSet('plan.active', false)
        ->call('toggleActive');

    expect($plan->fresh()->active)->toBeTrue();

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan->fresh()])
        ->call('toggleActive');

    expect($plan->fresh()->active)->toBeFalse();
});

test('a shared-group member can also toggle active state', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);
    $plan->sharedGroups()->create(['group' => 'helpers']);
    $member = User::factory()->create(['groups' => ['helpers']]);

    Livewire::actingAs($member)->test(Admin::class, ['plan' => $plan])
        ->call('toggleActive');

    expect($plan->fresh()->active)->toBeTrue();
});

test('an unrelated user cannot toggle active state', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)->test(Admin::class, ['plan' => $plan])
        ->call('toggleActive')
        ->assertForbidden();

    expect($plan->fresh()->active)->toBeFalse();
});
