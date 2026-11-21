<?php
namespace FlorianBerger\Oidc;

use App\Plugin\PluginExtensions;
use FlorianBerger\Oidc\Models\OidcProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
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

        $this->registerExtensions($this->app->make(PluginExtensions::class));
    }

    /**
     * Menu entries and the login buttons, through the hook points of xTIPP (since 1.0.36)
     *
     * @param PluginExtensions $extensions
     *
     * @return void
     */
    private function registerExtensions(PluginExtensions $extensions): void {
        // Admin center → Konfiguration, before "Erweiterungen"
        $extensions->menu('admin.config', [
            'label'   => fn () => trans('oidc::ui.menu.admin'),
            'route'   => 'plugin-oidc.admin.index',
            'visible' => 'admin',
        ]);

        // Profile menu, above "Abmelden"
        $extensions->menu('profile', [
            'label'   => fn () => trans('oidc::ui.menu.connections'),
            'route'   => 'plugin-oidc.connections',
            'visible' => 'auth',
        ]);

        // xTIPP has no slot in the login form, so the buttons go below the content of the login page only. A closure,
        // so the providers are only read when the login page is shown.
        $extensions->slot('layout.content.bottom', function (): ?View {
            if (!request()->routeIs('login') || Auth::check()) {
                return null;
            }

            $oidcProviders = OidcProvider::active()->orderBy('name')->get();
            if ($oidcProviders->isEmpty()) {
                return null;
            }

            return view('oidc::partials.login-slot', ['oidcProviders' => $oidcProviders]);
        });
    }
}
