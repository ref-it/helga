<?php

use App\Livewire\Shift\Subscriptions;
use App\Models\User;
use Livewire\Livewire;

test('the owner can reach a shift\'s subscriptions page and sees its subscribers', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $otherShift = createShiftForPlan($plan);
    $otherShift->subscriptions()->create(['name' => 'John Roe', 'email' => 'john@example.com']);

    $response = $this->actingAs($owner)->get(route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]));

    $response->assertOk()->assertSee($shift->title)->assertSee('Jane Doe');
    // only the requested shift's subscribers show up, not other shifts'
    $response->assertDontSee('John Roe');
});

test('a member of a shared manage group can reach a shift\'s subscriptions page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->sharedGroups()->create(['group' => 'helpers']);
    $shift = createShiftForPlan($plan);

    $member = User::factory()->create(['groups' => ['helpers']]);

    $this->actingAs($member)->get(route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]))->assertOk();
});

test('an unrelated user cannot reach a shift\'s subscriptions page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]))->assertForbidden();
});

test('a shift that does not belong to the given plan is rejected', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $otherPlan = createOwnedPlan($owner);
    $shift = createShiftForPlan($otherPlan);

    Livewire::actingAs($owner)->test(Subscriptions::class, ['plan' => $plan, 'shift' => $shift])
        ->assertStatus(403);
});

test('the shift table links to the shift\'s subscriptions page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);

    $this->actingAs($owner)->get(route('plan.manage', $plan))
        ->assertOk()
        ->assertSee(route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]));
});
