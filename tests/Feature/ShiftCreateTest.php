<?php

use App\Livewire\Shift\Create;
use App\Models\ShiftCategory;
use App\Models\User;
use Livewire\Livewire;

test('creating a shift with an existing category succeeds', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);

    Livewire::actingAs($owner)->test(Create::class, ['plan' => $plan])
        ->set('title', 'New shift')
        ->set('description', 'Desc')
        ->set('start', now()->toDateTimeString())
        ->set('end', now()->addHour()->toDateTimeString())
        ->set('team_size', 2)
        ->set('category', (string) $category->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('plan.manage', $plan));

    expect($plan->shifts()->where('title', 'New shift')->exists())->toBeTrue();
});

test('setting the start fills in an empty end one hour later', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Create::class, ['plan' => $plan])
        ->set('start', '2026-06-01T10:00')
        ->assertSet('end', '2026-06-01T11:00');
});

test('setting the start does not overwrite an already-filled end', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Create::class, ['plan' => $plan])
        ->set('end', '2026-06-01T15:00')
        ->set('start', '2026-06-01T10:00')
        ->assertSet('end', '2026-06-01T15:00');
});

test('creating a shift while adding a new category inline succeeds', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Create::class, ['plan' => $plan])
        ->set('title', 'New shift')
        ->set('description', 'Desc')
        ->set('start', now()->toDateTimeString())
        ->set('end', now()->addHour()->toDateTimeString())
        ->set('team_size', 2)
        ->set('searchCategory', 'Bar')
        ->call('createCategory')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('plan.manage', $plan));

    expect(ShiftCategory::where('plan_id', $plan->id)->where('name', 'Bar')->exists())->toBeTrue();
    expect($plan->shifts()->where('title', 'New shift')->exists())->toBeTrue();
});
