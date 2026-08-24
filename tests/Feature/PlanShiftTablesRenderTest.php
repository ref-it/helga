<?php

use App\Models\User;

test('the manage page renders the shift table, grouped by category, with edit/delete actions', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $uncategorized = createShiftForPlan($plan);
    $categorized = $plan->shifts()->create([
        'title' => 'Bar shift',
        'description' => 'Serve drinks',
        'group' => 1,
        'type' => 'Bar',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'team_size' => 3,
    ]);

    $this->actingAs($owner)->get(route('plan.manage', $plan))
        ->assertOk()
        ->assertSee('data-flux-table', escape: false)
        ->assertSee($uncategorized->title)
        ->assertSee($categorized->title)
        ->assertSee('Bar');
});

test('the public show page renders the shift table with a subscribe action', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['published' => true, 'active' => true]);
    $shift = createShiftForPlan($plan);

    $this->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee('data-flux-table', escape: false)
        ->assertSee($shift->title)
        ->assertSee($plan->contact_email)
        ->assertSee('mailto:'.$plan->contact_email, escape: false);
});

test('the shift subscriptions page renders the subscription table', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->actingAs($owner)->get(route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]))
        ->assertOk()
        ->assertSee('data-flux-table', escape: false)
        ->assertSee('Jane Doe');
});
