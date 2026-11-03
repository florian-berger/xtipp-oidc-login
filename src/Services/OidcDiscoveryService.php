<?php
namespace FlorianBerger\Oidc\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use FlorianBerger\Oidc\Models\OidcProvider;

/**
 * Resolves and caches the discovery document and the JWKS of a provider
 */
class OidcDiscoveryService {
    /**
     * Get the (cached) discovery document
     *
     * @param OidcProvider $provider
     *
     * @return array
     */
    public function getDocument(OidcProvider $provider): array {
        return Cache::remember($this->key('discovery', $provider), now()->addHours(24), function () use ($provider) {
            $url = rtrim($provider->issuer, '/') . '/.well-known/openid-configuration';

            return Http::timeout(10)->get($url)->throw()->json();
        });
    }

    /**
     * Get the (cached) JSON Web Key Set
     *
     * @param OidcProvider $provider
     *
     * @return array
     */
    public function getJwks(OidcProvider $provider): array {
        return Cache::remember($this->key('jwks', $provider), now()->addHours(24), function () use ($provider) {
            $document = $this->getDocument($provider);

            return Http::timeout(10)->get($document['jwks_uri'])->throw()->json();
        });
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
}
