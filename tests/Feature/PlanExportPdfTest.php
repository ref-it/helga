<?php

use App\Models\User;

test('the owner can export the plan as a pdf', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $response = $this->actingAs($owner)->get(route('plan.export.pdf', $plan));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');

    // shown in the browser's viewer rather than dropped into the download
    // folder, with the filename still there for the viewer's save button
    expect($response->headers->get('content-disposition'))->toStartWith('inline;');
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
});

test('an unrelated user cannot export the plan as a pdf', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('plan.export.pdf', $plan))->assertForbidden();
});

test('guests are redirected to login', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $this->get(route('plan.export.pdf', $plan))->assertRedirect(route('login'));
});
