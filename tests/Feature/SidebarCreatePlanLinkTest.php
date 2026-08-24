<?php

test('the sidebar "create plan" link has no wire:navigate, so a guest\'s redirect to the external OIDC provider is a full page load', function (): void {
    // wire:navigate uses fetch(), which can't follow a redirect chain that
    // ends up on a different origin (the OIDC provider) - the browser blocks
    // reading a cross-origin redirected response via CORS, so the click
    // would silently do nothing for a logged-out visitor
    $response = $this->get(route('home'));

    $response->assertOk();

    preg_match_all('/<[^>]*href="'.preg_quote(route('plan.create'), '/').'"[^>]*>/', $response->getContent(), $matches);

    expect($matches[0])->not->toBeEmpty();
    foreach ($matches[0] as $link) {
        expect($link)->not->toContain('wire:navigate');
    }
});
