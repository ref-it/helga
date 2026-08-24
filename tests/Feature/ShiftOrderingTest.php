<?php

use App\Models\ShiftCategory;
use App\Models\User;

test('categories are ordered by when the earliest shift in each category starts', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    // created first, but its shift starts last
    $kitchen = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);
    $plan->shifts()->create([
        'title' => 'Cook dinner', 'description' => 'd', 'group' => 0,
        'type' => (string) $kitchen->id, 'start' => now()->addDays(3), 'end' => now()->addDays(3)->addHours(2), 'team_size' => 1,
    ]);

    // created second, but its shift starts first
    $security = ShiftCategory::create(['name' => 'Security', 'plan_id' => $plan->id]);
    $plan->shifts()->create([
        'title' => 'Watch entrance', 'description' => 'd', 'group' => 0,
        'type' => (string) $security->id, 'start' => now()->addDays(1), 'end' => now()->addDays(1)->addHours(2), 'team_size' => 1,
    ]);

    expect($plan->shifts->pluck('title')->all())->toBe(['Watch entrance', 'Cook dinner']);
});

test('uncategorized shifts always come first, even if they start later than every category', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $kitchen = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);
    $plan->shifts()->create([
        'title' => 'Cook dinner', 'description' => 'd', 'group' => 0,
        'type' => (string) $kitchen->id, 'start' => now()->addDays(1), 'end' => now()->addDays(1)->addHours(2), 'team_size' => 1,
    ]);

    // starts after the categorized shift, but should still be listed first
    $plan->shifts()->create([
        'title' => 'Uncategorized shift', 'description' => 'd', 'group' => 0,
        'start' => now()->addDays(5), 'end' => now()->addDays(5)->addHours(2), 'team_size' => 1,
    ]);

    expect($plan->shifts->pluck('title')->all())->toBe(['Uncategorized shift', 'Cook dinner']);
});

test('shifts within the same category are ordered by their own start', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $kitchen = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);

    $plan->shifts()->create([
        'title' => 'Second shift', 'description' => 'd', 'group' => 0,
        'type' => (string) $kitchen->id, 'start' => now()->addDays(2), 'end' => now()->addDays(2)->addHours(2), 'team_size' => 1,
    ]);
    $plan->shifts()->create([
        'title' => 'First shift', 'description' => 'd', 'group' => 0,
        'type' => (string) $kitchen->id, 'start' => now()->addDays(1), 'end' => now()->addDays(1)->addHours(2), 'team_size' => 1,
    ]);

    expect($plan->shifts->pluck('title')->all())->toBe(['First shift', 'Second shift']);
});
