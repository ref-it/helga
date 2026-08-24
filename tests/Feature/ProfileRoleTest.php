<?php

use App\Models\User;

beforeEach(function (): void {
    config(['services.oidc.admin_groups' => ['sr-admins']]);
});

test('a global admin sees the administrator role in the profile dropdown', function (): void {
    $admin = User::factory()->create(['groups' => ['sr-admins']]);

    $this->actingAs($admin)->get(route('plan.mine'))
        ->assertOk()
        ->assertSee(__('roles.admin'))
        ->assertDontSee(__('roles.user'));
});

test('a regular user sees the user role in the profile dropdown', function (): void {
    $user = User::factory()->create(['groups' => []]);

    $this->actingAs($user)->get(route('plan.mine'))
        ->assertOk()
        ->assertSee(__('roles.user'))
        ->assertDontSee(__('roles.admin'));
});
