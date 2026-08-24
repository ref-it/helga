<?php

use App\Models\User;

test('totalSlotsCount sums team_size across all shifts', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->shifts()->create(['title' => 'A', 'description' => 'D', 'start' => now(), 'end' => now()->addHour(), 'team_size' => 3]);
    $plan->shifts()->create(['title' => 'B', 'description' => 'D', 'start' => now(), 'end' => now()->addHour(), 'team_size' => 2]);

    expect($plan->fresh(['shifts'])->totalSlotsCount())->toBe(5);
});

test('filledSlotsCount sums subscriptions, capped per shift at team_size', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $shift = $plan->shifts()->create(['title' => 'A', 'description' => 'D', 'start' => now(), 'end' => now()->addHour(), 'team_size' => 2]);
    $shift->subscriptions()->create(['name' => 'One', 'email' => 'one@example.com']);
    $shift->subscriptions()->create(['name' => 'Two', 'email' => 'two@example.com']);
    $shift->subscriptions()->create(['name' => 'Three', 'email' => 'three@example.com']);

    expect($plan->fresh(['shifts.subscriptions'])->filledSlotsCount())->toBe(2);
});

test('an empty plan has zero total and filled slots', function (): void {
    $plan = createOwnedPlan(User::factory()->create());

    expect($plan->totalSlotsCount())->toBe(0);
    expect($plan->filledSlotsCount())->toBe(0);
});

test('firstShiftStart and lastShiftEnd span across all shifts, regardless of creation order', function (): void {
    $plan = createOwnedPlan(User::factory()->create());
    $plan->shifts()->create(['title' => 'Middle', 'description' => 'D', 'start' => '2026-06-02 10:00:00', 'end' => '2026-06-02 12:00:00', 'team_size' => 1]);
    $plan->shifts()->create(['title' => 'Last', 'description' => 'D', 'start' => '2026-06-03 10:00:00', 'end' => '2026-06-03 18:00:00', 'team_size' => 1]);
    $plan->shifts()->create(['title' => 'First', 'description' => 'D', 'start' => '2026-06-01 08:00:00', 'end' => '2026-06-01 09:00:00', 'team_size' => 1]);

    $plan = $plan->fresh(['shifts']);

    expect($plan->firstShiftStart())->toBe('2026-06-01 08:00:00');
    expect($plan->lastShiftEnd())->toBe('2026-06-03 18:00:00');
});

test('an empty plan has no schedule range', function (): void {
    $plan = createOwnedPlan(User::factory()->create());

    expect($plan->firstShiftStart())->toBeNull();
    expect($plan->lastShiftEnd())->toBeNull();
});
