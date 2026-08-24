<?php

use App\Models\OidcSession;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

function signLogoutToken(array $keyPair, array $overrides = []): string
{
    $payload = array_merge([
        'iss' => 'https://idp.test',
        'aud' => 'test-client',
        'iat' => time(),
        'jti' => bin2hex(random_bytes(8)),
        'sub' => 'oidc-subject',
        'events' => ['http://schemas.openid.net/event/backchannel-logout' => new stdClass],
    ], $overrides);

    return JWT::encode($payload, $keyPair['private'], 'RS256', 'test-key');
}

function fakeOidcDiscovery(array $keyPair): void
{
    $details = openssl_pkey_get_details($keyPair['resource']);

    Http::fake([
        'https://idp.test/.well-known/openid-configuration' => Http::response([
            'jwks_uri' => 'https://idp.test/jwks',
        ]),
        'https://idp.test/jwks' => Http::response([
            'keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-key',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ]],
        ]),
    ]);
}

function generateTestRsaKeyPair(): array
{
    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($resource, $private);
    $public = openssl_pkey_get_details($resource)['key'];

    return ['resource' => $resource, 'private' => $private, 'public' => $public];
}

beforeEach(function (): void {
    config([
        'services.oidc.base_url' => 'https://idp.test',
        'services.oidc.client_id' => 'test-client',
    ]);
});

test('a valid logout token deletes the matching session', function (): void {
    $keyPair = generateTestRsaKeyPair();
    fakeOidcDiscovery($keyPair);

    $user = User::factory()->create(['sub' => 'oidc-subject']);
    $sessionId = 'test-session-'.uniqid();
    File::put(storage_path('framework/sessions/'.$sessionId), 'dummy-session-data');

    $oidcSession = OidcSession::create([
        'user_id' => $user->id,
        'sub' => 'oidc-subject',
        'sid' => null,
        'laravel_session_id' => $sessionId,
    ]);

    $token = signLogoutToken($keyPair);

    $this->post(route('oidc.backchannel-logout'), ['logout_token' => $token])
        ->assertOk();

    expect(OidcSession::whereKey($oidcSession->id)->exists())->toBeFalse();
    expect(File::exists(storage_path('framework/sessions/'.$sessionId)))->toBeFalse();
});

test('a logout token signed by an untrusted key is rejected', function (): void {
    $keyPair = generateTestRsaKeyPair();
    fakeOidcDiscovery($keyPair);

    $untrustedKeyPair = generateTestRsaKeyPair();

    $user = User::factory()->create(['sub' => 'oidc-subject']);
    $sessionId = 'test-session-'.uniqid();
    File::put(storage_path('framework/sessions/'.$sessionId), 'dummy-session-data');

    $oidcSession = OidcSession::create([
        'user_id' => $user->id,
        'sub' => 'oidc-subject',
        'sid' => null,
        'laravel_session_id' => $sessionId,
    ]);

    $token = signLogoutToken($untrustedKeyPair);

    $this->post(route('oidc.backchannel-logout'), ['logout_token' => $token])
        ->assertStatus(400);

    expect(OidcSession::whereKey($oidcSession->id)->exists())->toBeTrue();
    expect(File::exists(storage_path('framework/sessions/'.$sessionId)))->toBeTrue();

    File::delete(storage_path('framework/sessions/'.$sessionId));
});

test('a logout token missing the backchannel-logout event is rejected', function (): void {
    $keyPair = generateTestRsaKeyPair();
    fakeOidcDiscovery($keyPair);

    $token = signLogoutToken($keyPair, ['events' => new stdClass]);

    $this->post(route('oidc.backchannel-logout'), ['logout_token' => $token])
        ->assertStatus(400);
});
