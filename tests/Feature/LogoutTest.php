<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.oidc.base_url' => 'https://idp.test',
        'services.oidc.client_id' => 'test-client',
    ]);
});

test('logging out ends the local session and redirects to the IdP end-session endpoint', function (): void {
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([
            'end_session_endpoint' => 'https://idp.test/logout',
        ]),
    ]);

    $user = User::factory()->create(['sub' => 'oidc-subject']);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://idp.test/logout?')
        ->toContain('client_id=test-client')
        ->toContain('post_logout_redirect_uri='.urlencode(route('home')));

    $this->assertGuest();
});

test('logging out falls back to a local-only logout if the IdP is unreachable', function (): void {
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([], 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('logging out falls back to a local-only logout if the provider has no end-session endpoint', function (): void {
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});

test('logging out from an active plan\'s public page redirects back there', function (): void {
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([
            'end_session_endpoint' => 'https://idp.test/logout',
        ]),
    ]);

    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $plan->update(['active' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['referer' => route('plan.show', $plan)])
        ->post(route('logout'));

    expect($response->headers->get('Location'))
        ->toContain('post_logout_redirect_uri='.urlencode(route('plan.show', $plan)));
});

test('logging out from an inactive plan\'s public page falls back to the home page', function (): void {
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([
            'end_session_endpoint' => 'https://idp.test/logout',
        ]),
    ]);

    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['referer' => route('plan.show', $plan)])
        ->post(route('logout'));

    expect($response->headers->get('Location'))
        ->toContain('post_logout_redirect_uri='.urlencode(route('home')));
});

test('logging out from a static informational page redirects back there', function (): void {
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([
            'end_session_endpoint' => 'https://idp.test/logout',
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['referer' => url('/imprint')])
        ->post(route('logout'));

    expect($response->headers->get('Location'))
        ->toContain('post_logout_redirect_uri='.urlencode(url('/imprint')));
});

test('logging out from an admin page falls back to the home page', function (): void {
    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([
            'end_session_endpoint' => 'https://idp.test/logout',
        ]),
    ]);

    $owner = User::factory()->create();
    $plan = createOwnedPlan($owner);

    $response = $this->actingAs($owner)
        ->withHeaders(['referer' => route('plan.manage', $plan)])
        ->post(route('logout'));

    expect($response->headers->get('Location'))
        ->toContain('post_logout_redirect_uri='.urlencode(route('home')));
});
