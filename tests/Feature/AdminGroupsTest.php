<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['services.oidc.admin_groups' => ['sr-admins']]);
});

test('a member of an admin group can reach the manage page of a plan owned by someone else', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $admin = User::factory()->create(['groups' => ['sr-admins']]);

    $this->actingAs($admin)->get(route('plan.manage', $plan))->assertOk();
});

test('a member of an admin group can reach a shift\'s subscriptions page of a plan owned by someone else', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $shift = createShiftForPlan($plan);

    $admin = User::factory()->create(['groups' => ['sr-admins']]);

    $this->actingAs($admin)->get(route('plan.shift.subscriptions', ['plan' => $plan, 'shift' => $shift]))->assertOk();
});

test('a member of an admin group can force-delete a plan owned by someone else', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $admin = User::factory()->create(['groups' => ['sr-admins']]);

    $this->actingAs($admin)->delete(route('plan.destroy', $plan));

    expect(Plan::find($plan->id))->toBeNull();
});

test('a user not in an admin group cannot reach the manage page of a plan owned by someone else', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $stranger = User::factory()->create(['groups' => ['some-other-group']]);

    $this->actingAs($stranger)->get(route('plan.manage', $plan))->assertForbidden();
});

test('a user with no admin groups configured never gets admin rights', function (): void {
    config(['services.oidc.admin_groups' => []]);

    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $other = User::factory()->create(['groups' => []]);

    $this->actingAs($other)->get(route('plan.manage', $plan))->assertForbidden();
});

test('a member of an admin group sees every plan on the dedicated admin plans page', function (): void {
    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $admin = User::factory()->create(['groups' => ['sr-admins']]);

    $this->actingAs($admin)->get(route('plan.admin_all'))->assertOk()->assertSee($plan->title);
});

test('a user not in an admin group cannot reach the admin plans page', function (): void {
    $regular = User::factory()->create(['groups' => []]);

    $this->actingAs($regular)->get(route('plan.admin_all'))->assertForbidden();
});

test('the admin plans page paginates instead of listing every plan at once', function (): void {
    $owner = User::factory()->create();
    $titles = [];
    foreach (range(1, 16) as $i) {
        $title = 'PaginationPlan-'.Str::random(12);
        createOwnedPlan($owner)->update(['title' => $title]);
        $titles[] = $title;
    }
    // newest (highest id) is listed first, oldest (lowest id) last
    $newestTitle = $titles[array_key_last($titles)];
    $oldestTitle = $titles[0];

    $admin = User::factory()->create(['groups' => ['sr-admins']]);

    $this->actingAs($admin)->get(route('plan.admin_all'))
        ->assertOk()
        ->assertSee($newestTitle)
        ->assertDontSee($oldestTitle);

    $this->actingAs($admin)->get(route('plan.admin_all', ['page' => 2]))
        ->assertOk()
        ->assertSee($oldestTitle)
        ->assertDontSee($newestTitle);
});

test('the admin plans sidebar link only appears for admin group members', function (): void {
    $admin = User::factory()->create(['groups' => ['sr-admins']]);
    $regular = User::factory()->create(['groups' => []]);

    $this->actingAs($admin)->get(route('plan.mine'))->assertOk()->assertSee(__('plan.adminPlans'));
    $this->actingAs($regular)->get(route('plan.mine'))->assertOk()->assertDontSee(__('plan.adminPlans'));
});
