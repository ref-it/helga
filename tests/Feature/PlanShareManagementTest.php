<?php

use App\Livewire\Plan\Admin;
use App\Models\Group;
use App\Models\PlanShare;
use App\Models\User;
use Livewire\Livewire;

test('the owner can add and remove a management-access group via the pillbox', function (): void {
    Group::create(['name' => 'helpers']);
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->set('manageGroupsInput', ['helpers'])
        ->assertHasNoErrors();

    expect($plan->sharedGroups()->where('group', 'helpers')->where('access', PlanShare::MANAGE)->exists())->toBeTrue();

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->set('manageGroupsInput', []);

    expect($plan->sharedGroups()->where('group', 'helpers')->exists())->toBeFalse();
});

test('the owner can add and remove a read-only group via the second pillbox', function (): void {
    Group::create(['name' => 'observers']);
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->set('readGroupsInput', ['observers'])
        ->assertHasNoErrors();

    expect($plan->sharedGroups()->where('group', 'observers')->where('access', PlanShare::READ)->exists())->toBeTrue();

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->set('readGroupsInput', []);

    expect($plan->sharedGroups()->where('group', 'observers')->exists())->toBeFalse();
});

test('a group cannot be both management and read-only at once - selecting it in one removes it from the other', function (): void {
    Group::create(['name' => 'helpers']);
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers', 'access' => PlanShare::READ]);

    $component = Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->assertSet('readGroupsInput', ['helpers'])
        ->assertSet('manageGroupsInput', []);

    $component->set('manageGroupsInput', ['helpers']);

    expect($plan->sharedGroups()->where('group', 'helpers')->where('access', PlanShare::MANAGE)->exists())->toBeTrue();
    expect($plan->sharedGroups()->where('group', 'helpers')->where('access', PlanShare::READ)->exists())->toBeFalse();
    expect($plan->sharedGroups()->where('group', 'helpers')->count())->toBe(1);
    $component->assertSet('readGroupsInput', []);
});

test('the owner can share with any known group, not just their own', function (): void {
    Group::create(['name' => 'helpers']);
    $owner = User::factory()->create(['groups' => []]);
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->set('manageGroupsInput', ['helpers']);

    expect($plan->sharedGroups()->where('group', 'helpers')->exists())->toBeTrue();
});

test('a tampered, unknown group name is silently dropped', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Admin::class, ['plan' => $plan])
        ->set('manageGroupsInput', ['made-up-group']);

    expect($plan->sharedGroups()->where('group', 'made-up-group')->exists())->toBeFalse();
});

test('a shared-group member cannot manage the shared groups list', function (): void {
    Group::create(['name' => 'more-helpers']);
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers', 'access' => PlanShare::MANAGE]);

    $member = User::factory()->create(['groups' => ['helpers']]);

    Livewire::actingAs($member)->test(Admin::class, ['plan' => $plan])
        ->set('manageGroupsInput', ['helpers', 'more-helpers'])
        ->assertForbidden();

    expect($plan->sharedGroups()->where('group', 'more-helpers')->exists())->toBeFalse();
});
