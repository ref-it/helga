<?php

use App\Models\User;

test('guests do not see the import button on the home page', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(__('plan.import'));
});

test('logged-in users see the import button on the home page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertSee(__('plan.import'));
});

test('guests are redirected to login when posting to the import route directly', function (): void {
    $this->post(route('plan.import'))->assertRedirect(route('login'));
});

test('the plan management page does not offer importing into the existing plan', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);

    $this->actingAs($owner)->get(route('plan.manage', $plan))
        ->assertOk()
        ->assertSee(__('plan.export'))
        ->assertDontSee(__('plan.import'));
});

test('the import route takes no plan, so a plan-scoped import url is gone', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $this->actingAs($owner)->post('/plans/import/'.$plan->id)->assertNotFound();
});
