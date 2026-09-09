<?php

use App\Models\Plan;
use App\Models\ShiftCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('exporting and re-importing a plan preserves each subscriber\'s email and phone in the right field', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->subscriptions()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '0123456789']);

    $csv = $this->actingAs($owner)->get(route('plan.export', $plan))->streamedContent();
    $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

    $this->actingAs($owner)->post(route('plan.import'), ['import' => $file])->assertRedirect();

    $imported = Plan::latest('id')->first();
    $importedSubscription = $imported->shifts()->firstOrFail()->subscriptions()->firstOrFail();

    expect($importedSubscription->email)->toBe('jane@example.com');
    expect($importedSubscription->phone)->toBe('0123456789');
});

test('exporting and re-importing a plan preserves the health certificate requirement and confirmation', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->update(['requires_health_certificate' => true]);
    $shift->subscriptions()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'health_certificate_confirmed' => true,
    ]);

    $csv = $this->actingAs($owner)->get(route('plan.export', $plan))->streamedContent();
    $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

    $this->actingAs($owner)->post(route('plan.import'), ['import' => $file])->assertRedirect();

    $imported = Plan::latest('id')->first();
    $importedShift = $imported->shifts()->firstOrFail();
    $importedSubscription = $importedShift->subscriptions()->firstOrFail();

    expect($importedShift->requires_health_certificate)->toBeTrue();
    expect($importedSubscription->health_certificate_confirmed)->toBeTrue();
});

test('exporting and re-importing a plan preserves a shift that does not require a health certificate', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);
    $shift->update(['requires_health_certificate' => false]);
    $shift->subscriptions()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'health_certificate_confirmed' => false,
    ]);

    $csv = $this->actingAs($owner)->get(route('plan.export', $plan))->streamedContent();
    $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

    $this->actingAs($owner)->post(route('plan.import'), ['import' => $file])->assertRedirect();

    $imported = Plan::latest('id')->first();
    $importedShift = $imported->shifts()->firstOrFail();
    $importedSubscription = $importedShift->subscriptions()->firstOrFail();

    expect($importedShift->requires_health_certificate)->toBeFalse();
    expect($importedSubscription->health_certificate_confirmed)->toBeFalse();
});

test('exporting and re-importing a plan with multiple shifts keeps each health certificate flag independent', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $requiring = createShiftForPlan($plan);
    $requiring->update(['requires_health_certificate' => true, 'title' => 'Requires cert']);
    $notRequiring = createShiftForPlan($plan);
    $notRequiring->update(['requires_health_certificate' => false, 'title' => 'No cert needed']);

    $csv = $this->actingAs($owner)->get(route('plan.export', $plan))->streamedContent();
    $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

    $this->actingAs($owner)->post(route('plan.import'), ['import' => $file])->assertRedirect();

    $imported = Plan::latest('id')->first();
    $importedRequiring = $imported->shifts()->where('title', 'Requires cert')->firstOrFail();
    $importedNotRequiring = $imported->shifts()->where('title', 'No cert needed')->firstOrFail();

    expect($importedRequiring->requires_health_certificate)->toBeTrue();
    expect($importedNotRequiring->requires_health_certificate)->toBeFalse();
});

test('exporting and re-importing a plan carries a shift category over by name', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Bar shifts', 'plan_id' => $plan->id]);
    createShiftForPlan($plan)->update(['type' => (string) $category->id]);

    $csv = $this->actingAs($owner)->get(route('plan.export', $plan))->streamedContent();
    $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

    $this->actingAs($owner)->post(route('plan.import'), ['import' => $file])->assertRedirect();

    $imported = Plan::latest('id')->first();
    $importedShift = $imported->shifts()->firstOrFail();

    // the id changes with the plan, so the name is what has to survive
    expect($imported->shiftCategories()->pluck('name')->all())->toBe(['Bar shifts']);
    expect($importedShift->type)->toBe((string) $imported->shiftCategories()->firstOrFail()->id);
});

test('re-importing a plan puts shifts that shared a category back into one category', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $category = ShiftCategory::create(['name' => 'Bar shifts', 'plan_id' => $plan->id]);
    createShiftForPlan($plan)->update(['type' => (string) $category->id, 'title' => 'Early']);
    createShiftForPlan($plan)->update(['type' => (string) $category->id, 'title' => 'Late']);

    $csv = $this->actingAs($owner)->get(route('plan.export', $plan))->streamedContent();
    $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

    $this->actingAs($owner)->post(route('plan.import'), ['import' => $file])->assertRedirect();

    $imported = Plan::latest('id')->first();

    expect($imported->shiftCategories()->count())->toBe(1);
    expect($imported->shifts()->pluck('type')->unique())->toHaveCount(1);
});

test('exporting and re-importing a plan leaves an uncategorised shift uncategorised', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    createShiftForPlan($plan);

    $csv = $this->actingAs($owner)->get(route('plan.export', $plan))->streamedContent();
    $file = UploadedFile::fake()->createWithContent('plan.csv', $csv);

    $this->actingAs($owner)->post(route('plan.import'), ['import' => $file])->assertRedirect();

    $imported = Plan::latest('id')->first();

    expect($imported->shifts()->firstOrFail()->type)->toBe('');
    expect($imported->shiftCategories()->count())->toBe(0);
});
