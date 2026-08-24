<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\OidcSession;
use App\Models\Plan;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OidcController extends Controller
{
    /**
     * Redirect the user to the OIDC provider for authentication.
     *
     * Remembers the page the visitor came from (if any) as Laravel's
     * "intended" URL, so callback() can send them back there afterwards -
     * unless the auth middleware already set one (e.g. it redirected them
     * here itself after hitting a protected route), in which case that
     * takes precedence.
     */
    public function redirect(Request $request)
    {
        if (! $request->session()->has('url.intended')) {
            $previousPath = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);

            if (is_string($previousPath) && $previousPath !== '' && $previousPath !== '/login') {
                $request->session()->put('url.intended', url($previousPath));
            }
        }

        return Socialite::driver('oidc')->redirect();
    }

    /**
     * Handle the OIDC provider callback, log the user in and record the
     * session so it can later be torn down by a back-channel logout.
     */
    public function callback(Request $request)
    {
        $driver = Socialite::driver('oidc');
        $oidcUser = $driver->user();

        $groupsClaim = config('services.oidc.groups_claim');
        $groups = $oidcUser->user[$groupsClaim] ?? [];

        $user = User::updateOrCreate(
            ['sub' => $oidcUser->id],
            [
                'name' => $oidcUser->name,
                'given_name' => $oidcUser->given_name,
                'family_name' => $oidcUser->family_name,
                'email' => $oidcUser->email,
                // standard OIDC claim, only present if the "phone" scope was
                // granted and the provider has a (verified) number on file
                'phone' => $oidcUser->user['phone_number'] ?? null,
                'avatar' => $oidcUser->user['picture'] ?? null,
                'groups' => $groups,
            ]
        );

        // build up a central registry of every group ever seen at the IdP,
        // across all users - so plans can be shared with any known group,
        // not just the ones the current user happens to belong to
        foreach ($groups as $group) {
            Group::firstOrCreate(['name' => $group]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        OidcSession::create([
            'user_id' => $user->id,
            'sub' => $user->sub,
            'sid' => $oidcUser->user['sid'] ?? null,
            'laravel_session_id' => $request->session()->getId(),
            'id_token' => $driver->getIdToken(),
        ]);

        return redirect()->intended(route('plan.mine'));
    }

    /**
     * Log the current user out locally, then send them on to the IdP's own
     * end-session endpoint (RP-initiated logout) so their SSO session ends
     * too - otherwise logging in again would silently reauthenticate them
     * without a login prompt.
     */
    public function logout(Request $request)
    {
        // read before the session is torn down below - it doesn't depend on
        // it, but doing this first keeps the "where are we going" and
        // "actually log out" concerns separate
        $redirectUrl = $this->safeLogoutRedirectUrl($request);

        $idToken = OidcSession::where('laravel_session_id', $request->session()->getId())->value('id_token');

        OidcSession::where('laravel_session_id', $request->session()->getId())->delete();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $endSessionEndpoint = $this->fetchEndSessionEndpoint();

        if ($endSessionEndpoint) {
            return redirect()->away($endSessionEndpoint.'?'.http_build_query(array_filter([
                'client_id' => config('services.oidc.client_id'),
                'post_logout_redirect_uri' => $redirectUrl,
                // most providers won't trust post_logout_redirect_uri without
                // this - without it they show their own logout/login page
                // instead of sending the visitor back here
                'id_token_hint' => $idToken,
            ])));
        }

        return redirect($redirectUrl);
    }

    /**
     * The page to return to after logout: the one the visitor came from, if
     * it'll actually still be visible to them once logged out (an active
     * plan or another always-public page) - otherwise the home page, since
     * anything else (an admin/manage page) would just 403 as a guest.
     */
    private function safeLogoutRedirectUrl(Request $request): string
    {
        $path = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);

        if (! is_string($path) || ! $this->isPublicPath($path)) {
            return route('home');
        }

        return url($path);
    }

    /**
     * Whether the given path is safe to show to a logged-out visitor -
     * either a static informational page, or a plan's public pages, but
     * only for a plan that's actually active (reachable without an
     * account).
     */
    private function isPublicPath(string $path): bool
    {
        if (in_array($path, [
            '/', '/accessibility', '/contributors', '/documentation',
            '/imprint', '/privacy', '/source-code', '/translate',
        ], true)) {
            return true;
        }

        if (preg_match('#^/s/([^/]+)(?:/|$)#', $path, $matches)) {
            return Plan::where('view_id', $matches[1])->value('active') ?? false;
        }

        return false;
    }

    /**
     * Handle an IdP-initiated OpenID Connect Back-Channel Logout request.
     * https://openid.net/specs/openid-connect-backchannel-1_0.html
     *
     * This is a server-to-server POST with no session/cookie, so it must
     * stay excluded from CSRF verification (see VerifyCsrfToken::$except).
     */
    public function backChannelLogout(Request $request): ResponseFactory|Response
    {
        $logoutToken = $request->input('logout_token');

        if (! $logoutToken) {
            return response('', 400);
        }

        try {
            $payload = JWT::decode($logoutToken, JWK::parseKeySet($this->fetchJwks()));
        } catch (Throwable $e) {
            Log::warning('Rejected OIDC back-channel logout token', ['error' => $e->getMessage()]);

            return response('', 400);
        }

        if (! $this->isValidLogoutToken($payload)) {
            return response('', 400);
        }

        // jti replay protection: only ever accept a given token once
        if (isset($payload->jti) && ! Cache::add('oidc_logout_jti:'.$payload->jti, true, now()->addMinutes(5))) {
            return response('', 400);
        }

        $query = OidcSession::query();
        $query->where(function ($query) use ($payload): void {
            if (isset($payload->sub)) {
                $query->orWhere('sub', $payload->sub);
            }
            if (isset($payload->sid)) {
                $query->orWhere('sid', $payload->sid);
            }
        });

        foreach ($query->get() as $session) {
            // this app uses the "file" session driver; adjust this if
            // SESSION_DRIVER is ever changed to something else
            File::delete(storage_path('framework/sessions/'.$session->laravel_session_id));
            $session->delete();
        }

        return response('', 200);
    }

    /**
     * Validate the claims of a decoded back-channel logout token per spec.
     */
    private function isValidLogoutToken(\stdClass $payload): bool
    {
        $issuer = rtrim((string) config('services.oidc.base_url'), '/');
        $clientId = config('services.oidc.client_id');

        if (($payload->iss ?? null) !== $issuer) {
            return false;
        }

        $audience = $payload->aud ?? null;
        $audienceMatches = is_array($audience)
            ? in_array($clientId, $audience, true)
            : $audience === $clientId;

        if (! $audienceMatches) {
            return false;
        }

        if (isset($payload->nonce)) {
            return false;
        }

        if (! isset($payload->sub) && ! isset($payload->sid)) {
            return false;
        }

        return isset($payload->events)
            && property_exists($payload->events, 'http://schemas.openid.net/event/backchannel-logout');
    }

    /**
     * Fetch (and cache) the OIDC provider's JSON Web Key Set.
     */
    private function fetchJwks(): array
    {
        return Cache::remember('oidc_jwks', now()->addHour(), fn () => Http::get($this->discoveryDocument()['jwks_uri'])->throw()->json());
    }

    /**
     * Fetch (and cache) the OIDC provider's end-session endpoint, or null if
     * discovery fails or the provider doesn't advertise one - logout then
     * just falls back to ending the local session only.
     */
    private function fetchEndSessionEndpoint(): ?string
    {
        try {
            return $this->discoveryDocument()['end_session_endpoint'] ?? null;
        } catch (Throwable $e) {
            Log::warning('Could not reach the OIDC provider for RP-initiated logout', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fetch (and cache) the OIDC provider's discovery document.
     */
    private function discoveryDocument(): array
    {
        return Cache::remember('oidc_discovery_document', now()->addHour(), function () {
            $baseUrl = rtrim((string) config('services.oidc.base_url'), '/');

            return Http::get($baseUrl.'/.well-known/openid-configuration')->throw()->json();
        });
    }
}
