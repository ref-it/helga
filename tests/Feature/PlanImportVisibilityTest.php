<?php

use App\Models\User;

test('guests do not see the import button on the home page', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(__('plan.import'));
});

test('logged-in users see the import button on the home page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertSee(__('plan.import'));
});

test('guests are redirected to login when posting to the import route directly', function (): void {
    $this->post(route('plan.import'))->assertRedirect(route('login'));
});
