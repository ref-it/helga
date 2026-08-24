<?php

namespace App\Support;

use SocialiteProviders\OIDC\Provider as BaseOidcProvider;

/**
 * Captures the raw ID token from the token exchange, which the base provider
 * decodes and then discards. We need it later as the id_token_hint on
 * RP-initiated logout - without it, most providers won't trust our
 * post_logout_redirect_uri and show their own logout/login page instead of
 * sending the visitor back to the app.
 */
class OidcProvider extends BaseOidcProvider
{
    private ?string $idToken = null;

    public function getAccessTokenResponse($code)
    {
        $response = parent::getAccessTokenResponse($code);

        $this->idToken = $response['id_token'] ?? null;

        return $response;
    }

    public function getIdToken(): ?string
    {
        return $this->idToken;
    }
}
