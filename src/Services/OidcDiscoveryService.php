<?php
namespace FlorianBerger\Oidc\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use FlorianBerger\Oidc\Models\OidcProvider;

/**
 * Resolves and caches the discovery document and the JWKS of a provider
 */
class OidcDiscoveryService {
    /**
     * Documents already written to the debug log in this request, the document is read several times per login
     *
     * @var array<string, bool>
     */
    private static array $debugLogged = [];

    /**
     * Get the (cached) discovery document
     *
     * @param OidcProvider $provider
     *
     * @return array
     */
    public function getDocument(OidcProvider $provider): array {
        $url       = rtrim($provider->issuer, '/') . '/.well-known/openid-configuration';
        $fromCache = true;

        $document = Cache::remember($this->key('discovery', $provider), now()->addHours(24), function () use ($provider, $url, &$fromCache) {
            $fromCache = false;

            try {
                return Http::timeout(10)->get($url)->throw()->json();
            } catch (Throwable $exception) {
                DebugLog::log('the discovery document could not be loaded.', $provider, ['url' => $url] + DebugLog::httpError($exception));

                throw $exception;
            }
        });

        $this->debugOnce('discovery', $provider, fn () => DebugLog::log('discovery document', $provider, [
            'url'                                   => $url,
            'from_cache'                            => $fromCache,
            'issuer'                                => $document['issuer'] ?? null,
            'authorization_endpoint'                => $document['authorization_endpoint'] ?? null,
            'token_endpoint'                        => $document['token_endpoint'] ?? null,
            'jwks_uri'                              => $document['jwks_uri'] ?? null,
            'scopes_supported'                      => $document['scopes_supported'] ?? null,
            'claims_supported'                      => $document['claims_supported'] ?? null,
            'id_token_signing_alg_values_supported' => $document['id_token_signing_alg_values_supported'] ?? null,
        ]));

        return $document;
    }

    /**
     * Get the (cached) JSON Web Key Set
     *
     * @param OidcProvider $provider
     *
     * @return array
     */
    public function getJwks(OidcProvider $provider): array {
        $fromCache = true;

        $jwks = Cache::remember($this->key('jwks', $provider), now()->addHours(24), function () use ($provider, &$fromCache) {
            $fromCache = false;
            $url       = $this->getDocument($provider)['jwks_uri'];

            try {
                return Http::timeout(10)->get($url)->throw()->json();
            } catch (Throwable $exception) {
                DebugLog::log('the JWKS could not be loaded.', $provider, ['url' => $url] + DebugLog::httpError($exception));

                throw $exception;
            }
        });

        // Logged again after a reload, so a rotated key can be seen
        $this->debugOnce('jwks:' . ($fromCache ? 'cache' : 'fresh'), $provider, fn () => DebugLog::log('JWKS', $provider, [
            'from_cache' => $fromCache,
            'keys'       => array_map(fn ($jwk) => [
                'kid' => $jwk['kid'] ?? null,
                'kty' => $jwk['kty'] ?? null,
                'alg' => $jwk['alg'] ?? null,
                'use' => $jwk['use'] ?? null,
            ], array_values(array_filter($jwks['keys'] ?? [], 'is_array'))),
        ]));

        return $jwks;
    }

    /**
     * Forget only the cached JWKS, e.g. after a key rotation of the provider
     *
     * @param OidcProvider $provider
     *
     * @return void
     */
    public function forgetJwks(OidcProvider $provider): void {
        Cache::forget($this->key('jwks', $provider));
    }

    /**
     * Forget the cached discovery document and JWKS
     *
     * @param OidcProvider $provider
     *
     * @return void
     */
    public function forget(OidcProvider $provider): void {
        Cache::forget($this->key('discovery', $provider));
        $this->forgetJwks($provider);
    }

    /**
     * @param string       $type
     * @param OidcProvider $provider
     *
     * @return string
     */
    private function key(string $type, OidcProvider $provider): string {
        return 'plugin-oidc:' . $type . ':' . $provider->id;
    }

    /**
     * Run a debug log callback only once per request, type and provider
     *
     * @param string       $type
     * @param OidcProvider $provider
     * @param callable     $callback
     *
     * @return void
     */
    private function debugOnce(string $type, OidcProvider $provider, callable $callback): void {
        $key = $type . ':' . $provider->id;
        if (isset(self::$debugLogged[$key])) {
            return;
        }

        self::$debugLogged[$key] = true;
        $callback();
    }
}
