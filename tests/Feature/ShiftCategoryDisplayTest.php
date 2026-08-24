<?php

use App\Livewire\Shift\Create;
use App\Livewire\Shift\Edit;
use App\Models\ShiftCategory;
use App\Models\User;
use Livewire\Livewire;

test('the shift category name is shown instead of its raw id on the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);
    $plan->shifts()->create([
        'title' => 'Categorized shift',
        'description' => 'Desc',
        'group' => 0,
        'type' => (string) $category->id,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);

    $this->actingAs($owner)->get(route('plan.manage', $plan))
        ->assertOk()
        ->assertSee('Kitchen');
});

test('the shift category name is shown instead of its raw id on the public show page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['published' => true, 'active' => true]);
    $category = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);
    $plan->shifts()->create([
        'title' => 'Categorized shift',
        'description' => 'Desc',
        'group' => 0,
        'type' => (string) $category->id,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);

    $this->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee('Kitchen');
});

test('the shift category name is shown instead of its raw id in the pdf export', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);
    $plan->shifts()->create([
        'title' => 'Categorized shift',
        'description' => 'Desc',
        'group' => 0,
        'type' => (string) $category->id,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);

    $html = view('pdf.plan', [
        'plan' => $plan,
        'categoryNames' => $plan->shiftCategories->pluck('name', 'id'),
    ])->render();

    expect($html)->toContain('Kitchen');
});

test('picking an existing category in the select stores its id, not its name', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);

    Livewire::actingAs($owner)->test(Create::class, ['plan' => $plan])
        ->set('category', (string) $category->id)
        ->assertSet('category', (string) $category->id);
});

test('shifts without a category are shown alongside categorized ones, without a fieldset, on the manage page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);
    $plan->shifts()->create([
        'title' => 'Uncategorized shift',
        'description' => 'Desc',
        'group' => 0,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);
    $plan->shifts()->create([
        'title' => 'Categorized shift',
        'description' => 'Desc',
        'group' => 0,
        'type' => (string) $category->id,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);

    $response = $this->actingAs($owner)->get(route('plan.manage', $plan));

    $response->assertOk()
        ->assertSee('Uncategorized shift')
        ->assertSee('Categorized shift')
        ->assertSee('Kitchen');

    expect(substr_count($response->getContent(), '<fieldset'))->toBe(1);
});

test('shifts without a category are shown, without a fieldset, on the public show page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['published' => true, 'active' => true]);
    $plan->shifts()->create([
        'title' => 'Only uncategorized shift',
        'description' => 'Desc',
        'group' => 0,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);

    $response = $this->get(route('plan.show', $plan));

    $response->assertOk()->assertSee('Only uncategorized shift');
    expect($response->getContent())->not->toContain('<fieldset');
});

test('the shift category select shows the category name, not the raw id, when editing', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Kitchen', 'plan_id' => $plan->id]);
    $shift = $plan->shifts()->create([
        'title' => 'Categorized shift',
        'description' => 'Desc',
        'group' => 0,
        'type' => (string) $category->id,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan, 'shift' => $shift])
        ->assertSet('category', (string) $category->id)
        ->assertSee('Kitchen');
});
