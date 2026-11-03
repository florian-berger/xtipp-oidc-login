<?php
namespace FlorianBerger\Oidc\Services;

use RuntimeException;
use FlorianBerger\Oidc\Models\OidcProvider;

/**
 * Verifies the signature and the standard claims of an id_token
 *
 * The libraries firebase/php-jwt and league/oauth2-client are not available for a plugin, so the signature is checked
 * with OpenSSL. Only asymmetric algorithms (RS256/384/512, ES256/384/512) are accepted, never "none" or "HS*": The
 * keys always come from the JWKS of the provider.
 */
class IdTokenVerifier {
    /**
     * Allowed algorithms => [OpenSSL algorithm, key type]
     *
     * @var array<string, array{0: int, 1: string}>
     */
    private const ALGORITHMS = [
        'RS256' => [OPENSSL_ALGO_SHA256, 'RSA'],
        'RS384' => [OPENSSL_ALGO_SHA384, 'RSA'],
        'RS512' => [OPENSSL_ALGO_SHA512, 'RSA'],
        'ES256' => [OPENSSL_ALGO_SHA256, 'EC'],
        'ES384' => [OPENSSL_ALGO_SHA384, 'EC'],
        'ES512' => [OPENSSL_ALGO_SHA512, 'EC'],
    ];

    /**
     * Curve => [DER encoded OID, size of a coordinate in bytes]
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const CURVES = [
        'P-256' => ["\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07", 32],
        'P-384' => ["\x06\x05\x2b\x81\x04\x00\x22", 48],
        'P-521' => ["\x06\x05\x2b\x81\x04\x00\x23", 66],
    ];

    /**
     * Tolerated difference between the clocks in seconds
     *
     * @var int
     */
    private const LEEWAY = 60;

    /**
     * @param OidcDiscoveryService $discovery
     */
    public function __construct(private readonly OidcDiscoveryService $discovery) {
    }

    /**
     * Verify an id_token and return its claims
     *
     * @param OidcProvider $provider
     * @param string       $idToken
     * @param string       $expectedNonce
     *
     * @return array
     *
     * @throws RuntimeException if the signature or a claim is invalid
     */
    public function verify(OidcProvider $provider, string $idToken, string $expectedNonce): array {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new RuntimeException('The id_token is malformed.');
        }

        $header = $this->decodeJson($parts[0]);
        $claims = $this->decodeJson($parts[1]);
        $alg    = $header['alg'] ?? null;

        if (!is_string($alg) || !isset(self::ALGORITHMS[$alg])) {
            throw new RuntimeException('The id_token uses an unsupported algorithm.');
        }

        $signature = $this->base64UrlDecode($parts[2]);
        $data      = $parts[0] . '.' . $parts[1];

        if (!$this->signatureIsValid($provider, $alg, $header['kid'] ?? null, $data, $signature)) {
            throw new RuntimeException('The signature of the id_token is invalid.');
        }

        $this->checkClaims($provider, $claims, $expectedNonce);

        return $claims;
    }

    /**
     * Check the signature with the keys of the provider, a rotated key is loaded once more
     *
     * @param OidcProvider $provider
     * @param string       $alg
     * @param mixed        $kid
     * @param string       $data
     * @param string       $signature
     *
     * @return bool
     */
    private function signatureIsValid(OidcProvider $provider, string $alg, mixed $kid, string $data, string $signature): bool {
        [$opensslAlgorithm, $keyType] = self::ALGORITHMS[$alg];

        foreach ([false, true] as $reload) {
            if ($reload) {
                $this->discovery->forgetJwks($provider);
            }

            foreach ($this->candidateKeys($this->discovery->getJwks($provider), $keyType, $kid) as $jwk) {
                $pem = $keyType === 'RSA' ? $this->rsaKeyToPem($jwk) : $this->ecKeyToPem($jwk, $alg);
                if ($pem === null) {
                    continue;
                }

                $rawSignature = $keyType === 'EC' ? $this->ecSignatureToDer($signature, $alg) : $signature;
                if ($rawSignature !== null && openssl_verify($data, $rawSignature, $pem, $opensslAlgorithm) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The keys of the right type, with the given "kid" when the token names one
     *
     * @param array  $jwks
     * @param string $keyType
     * @param mixed  $kid
     *
     * @return array[]
     */
    private function candidateKeys(array $jwks, string $keyType, mixed $kid): array {
        return array_values(array_filter($jwks['keys'] ?? [], function ($jwk) use ($keyType, $kid) {
            return is_array($jwk)
                && ($jwk['kty'] ?? null) === $keyType
                && (($jwk['use'] ?? 'sig') === 'sig')
                && ($kid === null || ($jwk['kid'] ?? null) === $kid);
        }));
    }

    /**
     * Check issuer, audience, nonce and times
     *
     * @param OidcProvider $provider
     * @param array        $claims
     * @param string       $expectedNonce
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function checkClaims(OidcProvider $provider, array $claims, string $expectedNonce): void {
        $issuer = $this->discovery->getDocument($provider)['issuer'] ?? $provider->issuer;
        if (!isset($claims['iss']) || !is_string($claims['iss']) || rtrim($claims['iss'], '/') !== rtrim($issuer, '/')) {
            throw new RuntimeException('The id_token has an unexpected issuer.');
        }

        if (!in_array($provider->client_id, (array) ($claims['aud'] ?? []), true)) {
            throw new RuntimeException('The id_token has an unexpected audience.');
        }

        if (!isset($claims['nonce']) || !is_string($claims['nonce']) || !hash_equals($expectedNonce, $claims['nonce'])) {
            throw new RuntimeException('The id_token has an unexpected nonce.');
        }

        if (!isset($claims['sub']) || !is_string($claims['sub']) || $claims['sub'] === '') {
            throw new RuntimeException('The id_token has no subject.');
        }

        $now = time();
        if (!isset($claims['exp']) || !is_numeric($claims['exp']) || $claims['exp'] + self::LEEWAY < $now) {
            throw new RuntimeException('The id_token is expired.');
        }

        if (isset($claims['nbf']) && is_numeric($claims['nbf']) && $claims['nbf'] - self::LEEWAY > $now) {
            throw new RuntimeException('The id_token is not valid yet.');
        }
    }

    /**
     * @param string $value
     *
     * @return array
     *
     * @throws RuntimeException
     */
    private function decodeJson(string $value): array {
        $decoded = json_decode($this->base64UrlDecode($value), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('The id_token is malformed.');
        }

        return $decoded;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function base64UrlDecode(string $value): string {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('The id_token is malformed.');
        }

        return $decoded;
    }

    /**
     * PEM of an RSA key of a JWKS
     *
     * @param array $jwk
     *
     * @return string|null
     */
    private function rsaKeyToPem(array $jwk): ?string {
        if (!isset($jwk['n'], $jwk['e'])) {
            return null;
        }

        $publicKey = $this->asn1(0x30, $this->asn1Integer($this->base64UrlDecode($jwk['n'])) . $this->asn1Integer($this->base64UrlDecode($jwk['e'])));
        $algorithm = $this->asn1(0x30, "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00");

        return $this->toPem($this->asn1(0x30, $algorithm . $this->asn1(0x03, "\x00" . $publicKey)));
    }

    /**
     * PEM of an EC key of a JWKS, the curve has to fit to the algorithm
     *
     * @param array  $jwk
     * @param string $alg
     *
     * @return string|null
     */
    private function ecKeyToPem(array $jwk, string $alg): ?string {
        $curve = ['ES256' => 'P-256', 'ES384' => 'P-384', 'ES512' => 'P-521'][$alg];
        if (($jwk['crv'] ?? null) !== $curve || !isset($jwk['x'], $jwk['y'])) {
            return null;
        }

        $size = self::CURVES[$curve][1];
        $x    = str_pad($this->base64UrlDecode($jwk['x']), $size, "\x00", STR_PAD_LEFT);
        $y    = str_pad($this->base64UrlDecode($jwk['y']), $size, "\x00", STR_PAD_LEFT);
        if (strlen($x) !== $size || strlen($y) !== $size) {
            return null;
        }

        $algorithm = $this->asn1(0x30, "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" . self::CURVES[$curve][0]);

        return $this->toPem($this->asn1(0x30, $algorithm . $this->asn1(0x03, "\x00\x04" . $x . $y)));
    }

    /**
     * An ES* signature of a JWT is R || S, OpenSSL needs DER
     *
     * @param string $signature
     * @param string $alg
     *
     * @return string|null
     */
    private function ecSignatureToDer(string $signature, string $alg): ?string {
        $size = self::CURVES[['ES256' => 'P-256', 'ES384' => 'P-384', 'ES512' => 'P-521'][$alg]][1];
        if (strlen($signature) !== 2 * $size) {
            return null;
        }

        return $this->asn1(0x30, $this->asn1Integer(substr($signature, 0, $size)) . $this->asn1Integer(substr($signature, $size)));
    }

    /**
     * @param int    $tag
     * @param string $content
     *
     * @return string
     */
    private function asn1(int $tag, string $content): string {
        $length = strlen($content);
        if ($length < 0x80) {
            $encodedLength = chr($length);
        } else {
            $bytes         = ltrim(pack('N', $length), "\x00");
            $encodedLength = chr(0x80 | strlen($bytes)) . $bytes;
        }

        return chr($tag) . $encodedLength . $content;
    }

    /**
     * Positive INTEGER from big-endian bytes
     *
     * @param string $bytes
     *
     * @return string
     */
    private function asn1Integer(string $bytes): string {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || ord($bytes[0]) > 0x7f) {
            $bytes = "\x00" . $bytes;
        }

        return $this->asn1(0x02, $bytes);
    }

    /**
     * @param string $der
     *
     * @return string
     */
    private function toPem(string $der): string {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
