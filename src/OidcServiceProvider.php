<?php
namespace FlorianBerger\Oidc;

use Illuminate\Support\ServiceProvider;

/**
 * OIDC login for xTIPP, registered completely by the plugin
 */
class OidcServiceProvider extends ServiceProvider {
    /**
     * Boot the plugin
     *
     * @return void
     */
    public function boot(): void {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'oidc');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'oidc');

        // Own tables: The migrations in "database/migrations" run when the plugin is enabled
    }
}
