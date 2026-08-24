<?php

use App\Livewire\Plan\Admin;
use App\Models\PlanShare;
use App\Models\Shift;
use App\Models\User;
use Livewire\Livewire;

test('a read-only group member can view the manage page but not the edit/delete/export controls', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    $response = $this->actingAs($member)->get(route('plan.manage', $plan));

    $response->assertOk()
        ->assertSee($shift->title)
        ->assertDontSee(__('plan.export'))
        ->assertDontSee(__('plan.exportPdf'))
        ->assertDontSee(__('plan.import'))
        ->assertDontSee(__('shift.add'));
});

test('a read-only group member can view the subscriptions page but not edit/delete a subscription', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    $this->actingAs($member)->get(route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]))
        ->assertOk()
        ->assertSee('Jane Doe');
});

test('a read-only group member cannot toggle publish state', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    Livewire::actingAs($member)->test(Admin::class, ['plan' => $plan])
        ->call('togglePublished')
        ->assertForbidden();

    expect($plan->fresh()->published)->toBeFalse();
});

test('a read-only group member cannot create a shift', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    expect($member->can('create', [Shift::class, $plan]))->toBeFalse();
});

test('a read-only group member cannot edit or delete an existing shift', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    $this->actingAs($member)->get(route('plan.shift.edit', ['plan' => $plan, 'shift' => $shift]))->assertForbidden();
    $this->actingAs($member)->delete(route('plan.shift.destroy', ['plan' => $plan, 'shift' => $shift]))->assertForbidden();
});

test('a read-only group member cannot export or import the plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    $this->actingAs($member)->get(route('plan.export', $plan))->assertForbidden();
    $this->actingAs($member)->get(route('plan.export.pdf', $plan))->assertForbidden();
});

test('a read-only group member cannot edit the plan itself', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    $this->actingAs($member)->get(route('plan.edit', $plan))->assertForbidden();
});

test('a read-only group member can preview an unpublished plan on the public page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'observers', 'access' => PlanShare::READ]);
    $member = User::factory()->create(['groups' => ['observers']]);

    $this->actingAs($member)->get(route('plan.show', $plan))->assertOk();
});

test('a management-access group member still has full management rights', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'managers', 'access' => PlanShare::MANAGE]);
    $member = User::factory()->create(['groups' => ['managers']]);

    expect($plan->isManageableBy($member))->toBeTrue();
    expect($plan->isViewableBy($member))->toBeTrue();
    $this->actingAs($member)->get(route('plan.export', $plan))->assertOk();
});

test('an unrelated user has neither view nor management access', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $stranger = User::factory()->create();

    expect($plan->isViewableBy($stranger))->toBeFalse();
    expect($plan->isManageableBy($stranger))->toBeFalse();
    $this->actingAs($stranger)->get(route('plan.manage', $plan))->assertForbidden();
});
