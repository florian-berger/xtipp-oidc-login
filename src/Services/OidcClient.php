<?php
namespace FlorianBerger\Oidc\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use FlorianBerger\Oidc\Models\OidcProvider;

/**
 * Authorization-code flow against a provider, only with the HTTP client of Laravel
 */
class OidcClient {
    /**
     * @param OidcDiscoveryService $discovery
     */
    public function __construct(private readonly OidcDiscoveryService $discovery) {
    }

    /**
     * The redirect URL, which has to be registered at the provider
     *
     * @param OidcProvider $provider
     *
     * @return string
     */
    public function redirectUri(OidcProvider $provider): string {
        return route('plugin-oidc.callback', ['provider' => $provider->slug]);
    }

    /**
     * Build the authorization URL a user is redirected to
     *
     * @param OidcProvider $provider
     * @param string       $state
     * @param string       $nonce
     *
     * @return string
     */
    public function getAuthorizationUrl(OidcProvider $provider, string $state, string $nonce): string {
        $document = $this->discovery->getDocument($provider);

        // Extra params come first, so they can never override the required ones
        $query = array_merge($provider->extra_authorize_params ?? [], [
            'response_type' => 'code',
            'client_id'     => $provider->client_id,
            'redirect_uri'  => $this->redirectUri($provider),
            'scope'         => implode(' ', $provider->scopesArray()),
            'state'         => $state,
            'nonce'         => $nonce,
        ]);

        $url = $document['authorization_endpoint'];

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for the id_token
     *
     * @param OidcProvider $provider
     * @param string       $code
     *
     * @return string
     *
     * @throws RuntimeException if the provider returns no id_token
     */
    public function fetchIdToken(OidcProvider $provider, string $code): string {
        $document = $this->discovery->getDocument($provider);

        $response = Http::timeout(10)->asForm()->acceptJson()->post($document['token_endpoint'], [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri($provider),
            'client_id'     => $provider->client_id,
            'client_secret' => $provider->client_secret,
        ])->throw();

        $idToken = $response->json('id_token');
        if (!is_string($idToken) || $idToken === '') {
            throw new RuntimeException('The provider returned no id_token.');
        }

        return $idToken;
    }
}
