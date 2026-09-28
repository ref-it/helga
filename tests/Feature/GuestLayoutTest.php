<?php

use App\Models\User;

test('a guest gets no sidebar on wide screens, with the brand in the header instead', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<div class="lg:hidden">', false)
        ->assertSee('class="hidden lg:flex items-center"', false);
});

test('a logged-in user keeps the sidebar and gets no second brand in the header', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertDontSee('<div class="lg:hidden">', false)
        ->assertDontSee('class="hidden lg:flex items-center"', false);
});
