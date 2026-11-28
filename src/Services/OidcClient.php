<?php
namespace FlorianBerger\Oidc\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
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

        DebugLog::log('redirect to the provider', $provider, [
            'authorization_endpoint' => $url,
            'redirect_uri'           => $query['redirect_uri'],
            'scope'                  => $query['scope'],
            'extra_authorize_params' => $provider->extra_authorize_params ?? [],
        ]);

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Claims that make the plugin ask the userinfo endpoint, when the id_token does not contain one of them
     */
    private const USERINFO_TRIGGER_CLAIMS = ['email', 'name', 'given_name', 'family_name'];

    /**
     * Claims that are filled from the userinfo endpoint, when the id_token does not contain them. The nickname claims
     * alone do not trigger a request, they are only a better default for the nickname.
     */
    private const USERINFO_CLAIMS = ['email', 'name', 'given_name', 'family_name', 'nickname', 'preferred_username'];

    /**
     * Exchange an authorization code for the id_token and the access token
     *
     * The access token is only kept in memory, to ask the userinfo endpoint for claims that are missing in the
     * id_token. It is never stored or logged.
     *
     * @param OidcProvider $provider
     * @param string       $code
     *
     * @return array{id_token: string, access_token: ?string}
     *
     * @throws RuntimeException if the provider returns no id_token
     */
    public function fetchTokens(OidcProvider $provider, string $code): array {
        $document = $this->discovery->getDocument($provider);

        $response = Http::timeout(10)->asForm()->acceptJson()->post($document['token_endpoint'], [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri($provider),
            'client_id'     => $provider->client_id,
            'client_secret' => $provider->client_secret,
        ]);

        // Only the names of the fields and the granted scope: The answer contains the tokens
        $body = $response->json();
        DebugLog::log('answer of the token endpoint', $provider, [
            'token_endpoint' => $document['token_endpoint'],
            'status'         => $response->status(),
        ] + ($response->successful() ? [
            'fields' => is_array($body) ? array_keys($body) : null,
            'scope'  => is_array($body) ? ($body['scope'] ?? null) : null,
        ] : [
            'error'             => is_array($body) ? ($body['error'] ?? null) : null,
            'error_description' => is_array($body) ? ($body['error_description'] ?? null) : null,
        ]));

        $response->throw();

        $idToken = $response->json('id_token');
        if (!is_string($idToken) || $idToken === '') {
            throw new RuntimeException('The provider returned no id_token.');
        }

        $accessToken = $response->json('access_token');

        return [
            'id_token'     => $idToken,
            'access_token' => is_string($accessToken) && $accessToken !== '' ? $accessToken : null,
        ];
    }

    /**
     * Add the claims of the userinfo endpoint that are missing in the verified id_token
     *
     * Some providers keep the id_token minimal and return the email only there (e.g. Discord). The endpoint is only
     * asked when one of USERINFO_TRIGGER_CLAIMS is missing. The claims of the signed id_token always win, and the answer is
     * ignored, when its "sub" differs from the one of the id_token (OpenID Connect Core 1.0, 5.3.2). A failure never
     * stops the login: the claims of the id_token are used as they are.
     *
     * @param OidcProvider $provider
     * @param array        $claims      Verified claims of the id_token
     * @param string|null  $accessToken
     *
     * @return array
     */
    public function addUserInfoClaims(OidcProvider $provider, array $claims, ?string $accessToken): array {
        $isMissing = fn (string $claim) => !isset($claims[$claim]) || $claims[$claim] === '';
        if (array_filter(self::USERINFO_TRIGGER_CLAIMS, $isMissing) === []) {
            return $claims;
        }

        $missing = array_values(array_filter(self::USERINFO_CLAIMS, $isMissing));

        try {
            $endpoint = $this->discovery->getDocument($provider)['userinfo_endpoint'] ?? null;
        } catch (Throwable) {
            $endpoint = null;
        }

        if ($accessToken === null || !is_string($endpoint) || $endpoint === '') {
            DebugLog::log('userinfo endpoint not asked', $provider, [
                'missing'          => $missing,
                'has_access_token' => $accessToken !== null,
                'has_endpoint'     => is_string($endpoint) && $endpoint !== '',
            ]);

            return $claims;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->withToken($accessToken)->get($endpoint);
        } catch (Throwable $exception) {
            Log::warning('OIDC userinfo endpoint not reachable, the claims of the id_token are used.', [
                'provider' => $provider->slug, 'exception' => $exception->getMessage(),
            ]);

            return $claims;
        }

        $userInfo = $response->json();

        if (!$response->successful() || !is_array($userInfo)) {
            Log::warning('OIDC userinfo endpoint failed, the claims of the id_token are used.', [
                'provider' => $provider->slug, 'status' => $response->status(),
            ]);
            DebugLog::log('answer of the userinfo endpoint', $provider, [
                'userinfo_endpoint' => $endpoint,
                'status'            => $response->status(),
                'content_type'      => $response->header('Content-Type'),
                'error'             => is_array($userInfo) ? ($userInfo['error'] ?? null) : null,
                'error_description' => is_array($userInfo) ? ($userInfo['error_description'] ?? null) : null,
            ]);

            return $claims;
        }

        // The answer of another user must never be mixed into the claims
        if (!isset($userInfo['sub']) || !is_string($userInfo['sub']) || !hash_equals($claims['sub'], $userInfo['sub'])) {
            Log::warning('OIDC userinfo ignored: its subject differs from the one of the id_token.', [
                'provider' => $provider->slug,
            ]);

            return $claims;
        }

        DebugLog::log('answer of the userinfo endpoint', $provider, [
            'userinfo_endpoint' => $endpoint,
            'status'            => $response->status(),
            'missing'           => $missing,
            'claims'            => $userInfo,
        ]);

        // Only the missing claims, and only as text: Nothing else of the answer is used
        foreach ($missing as $claim) {
            if (isset($userInfo[$claim]) && is_string($userInfo[$claim]) && $userInfo[$claim] !== '') {
                $claims[$claim] = $userInfo[$claim];
            }
        }

        return $claims;
    }
}
