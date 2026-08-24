<?php

use App\Livewire\Plan\Edit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('public');
});

test('the owner can upload a logo', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan])
        ->set('newLogo', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('plan.manage', $plan));

    $plan->refresh();
    expect($plan->logo)->not->toBeNull();
    Storage::disk('public')->assertExists($plan->logo);
    expect($plan->logoUrl())->toContain($plan->logo);
});

test('an svg logo is accepted', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan])
        ->set('newLogo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('plan.manage', $plan));

    $plan->refresh();
    expect($plan->logo)->not->toBeNull();
    Storage::disk('public')->assertExists($plan->logo);
});

test('uploading a new logo replaces and deletes the old one', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['logo' => 'plan-logos/old.png']);
    Storage::disk('public')->put('plan-logos/old.png', 'fake-content');

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan])
        ->set('newLogo', UploadedFile::fake()->image('new.png'))
        ->call('save')
        ->assertHasNoErrors();

    $plan->refresh();
    Storage::disk('public')->assertMissing('plan-logos/old.png');
    Storage::disk('public')->assertExists($plan->logo);
    expect($plan->logo)->not->toBe('plan-logos/old.png');
});

test('the owner can remove an existing logo without uploading a new one', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['logo' => 'plan-logos/existing.png']);
    Storage::disk('public')->put('plan-logos/existing.png', 'fake-content');

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan])
        ->assertSet('existingLogo', 'plan-logos/existing.png')
        ->call('removeLogo')
        ->call('save')
        ->assertHasNoErrors();

    $plan->refresh();
    expect($plan->logo)->toBeNull();
    Storage::disk('public')->assertMissing('plan-logos/existing.png');
});

test('a non-image file is rejected', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    Livewire::actingAs($owner)->test(Edit::class, ['plan' => $plan])
        ->set('newLogo', UploadedFile::fake()->create('logo.pdf', 100))
        ->call('save')
        ->assertHasErrors(['newLogo' => 'image']);

    expect($plan->fresh()->logo)->toBeNull();
});

test('the uploaded logo is shown on the public show page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['published' => true, 'active' => true, 'logo' => 'plan-logos/shown.png']);
    Storage::disk('public')->put('plan-logos/shown.png', 'fake-content');

    $this->get(route('plan.show', $plan))
        ->assertOk()
        ->assertSee($plan->logoUrl(), escape: false);
});

test('a plan without a logo does not render an image on the show page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['published' => true, 'active' => true]);

    $response = $this->get(route('plan.show', $plan));

    $response->assertOk();
    expect($response->getContent())->not->toContain('<img');
});
