<?php
namespace FlorianBerger\Oidc\Services;

use App\Plugin\PluginManager;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;
use FlorianBerger\Oidc\Models\OidcProvider;

/**
 * Detailed log entries of the flow, only written while the setting "oidc_debug" is on
 *
 * Never pass the client secret, the code, the state, the nonce or any token (id_token, access_token, refresh_token)
 * to this class: The entries end up in the log of Laravel and stay there until the log rotation removes them.
 */
class DebugLog {
    /**
     * Prefix of every entry, to find them in the log
     */
    private const PREFIX = 'OIDC debug: ';

    /**
     * Is the debug mode on?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return filter_var(app(PluginManager::class)->setting('oidc', 'oidc_debug', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Write an entry, if the debug mode is on
     *
     * @param string                   $message
     * @param OidcProvider|string|null $provider The provider or its slug
     * @param array                    $context
     *
     * @return void
     */
    public static function log(string $message, OidcProvider|string|null $provider, array $context = []): void {
        if (!self::enabled()) {
            return;
        }

        $slug = $provider instanceof OidcProvider ? $provider->slug : $provider;

        Log::info(self::PREFIX . $message, ['provider' => $slug] + $context);
    }

    /**
     * Details of a failed HTTP request: The status and the body, shortened
     *
     * Only for requests whose answer contains no secrets (discovery document, JWKS). The token endpoint is handled in
     * OidcClient, which logs only "error" and "error_description".
     *
     * @param Throwable $exception
     *
     * @return array
     */
    public static function httpError(Throwable $exception): array {
        if ($exception instanceof RequestException) {
            return [
                'status' => $exception->response->status(),
                'body'   => mb_substr($exception->response->body(), 0, 1000),
            ];
        }

        return ['exception' => $exception->getMessage()];
    }
}
