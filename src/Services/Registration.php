<?php
namespace FlorianBerger\Oidc\Services;

use App\Models\User;
use App\Plugin\PluginManager;

/**
 * Decides whether new accounts may be created via OIDC
 */
class Registration {
    /**
     * New accounts need the general registration of xTIPP and the setting of the plugin
     *
     * @return bool
     */
    public static function allowed(): bool {
        if (config('xtipp.registration-enable') === 0) {
            return false;
        }

        return filter_var(app(PluginManager::class)->setting('oidc', 'oidc_allow_registration', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Is the address already used by an account? Also deleted accounts count, they still own the address in the
     * database, and the comparison ignores the case.
     *
     * @param string $email
     *
     * @return bool
     */
    public static function emailInUse(string $email): bool {
        return User::withTrashed()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->exists();
    }
}
