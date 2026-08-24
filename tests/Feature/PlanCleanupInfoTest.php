<?php

test('the home page shows the plan cleanup notice when enabled', function (): void {
    config(['app.plan_cleanup_enabled' => true, 'app.plan_cleanup_days' => 30]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(__('home.deleteInfo', ['days' => 30]));
});

test('the home page hides the plan cleanup notice when disabled', function (): void {
    config(['app.plan_cleanup_enabled' => false, 'app.plan_cleanup_days' => 30]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee(__('home.deleteInfo', ['days' => 30]));
});
