<?php

test('visiting /login remembers the previous page as the intended URL', function (): void {
    $this->get(route('login'), ['referer' => url('/s/some-view-id')]);

    expect(session('url.intended'))->toBe(url('/s/some-view-id'));
});

test('logging in redirects back to the page the visitor came from', function (): void {
    fakeOidcLogin([]);

    $this->get(route('login'), ['referer' => url('/s/some-view-id')]);
    $this->get(route('oidc.callback'))->assertRedirect(url('/s/some-view-id'));
});

test('visiting /login does not overwrite an intended URL already set by the auth middleware', function (): void {
    // simulates being redirected here by the 'auth' middleware after hitting
    // a protected route directly (e.g. /plan/create) - that destination
    // should win over whatever the referer header says
    session(['url.intended' => url('/plan/create')]);

    $this->get(route('login'), ['referer' => url('/some/other/page')]);

    expect(session('url.intended'))->toBe(url('/plan/create'));
});

test('visiting /login with no referer does not set an intended URL', function (): void {
    $this->get(route('login'));

    expect(session()->has('url.intended'))->toBeFalse();
});

test('visiting /login with the login page itself as referer does not set an intended URL', function (): void {
    $this->get(route('login'), ['referer' => url('/login')]);

    expect(session()->has('url.intended'))->toBeFalse();
});

test('logging in with no intended URL falls back to the default destination', function (): void {
    fakeOidcLogin([]);

    $this->get(route('oidc.callback'))->assertRedirect(route('plan.mine'));
});
