<?php

use Illuminate\Support\Facades\Route;
use FlorianBerger\Oidc\Http\Controllers\OidcCompleteProfileController;
use FlorianBerger\Oidc\Http\Controllers\OidcConnectionsController;
use FlorianBerger\Oidc\Http\Controllers\OidcLoginController;
use FlorianBerger\Oidc\Http\Controllers\OidcProviderManagementController;

// The middleware group "web" (session, cookies, CSRF) must be named, the routes of a plugin are outside of routes/web.php
Route::middleware(['web'])->group(function () {
    /**
     * Public: login page with the providers, redirect/callback of the flow and completion of the profile
     */
    Route::middleware(['throttle:20,1'])->prefix('oidc')->group(function () {
        Route::get('anmelden', [OidcLoginController::class, 'index'])->name('plugin-oidc.login');
        Route::get('profil-vervollstaendigen', [OidcCompleteProfileController::class, 'show'])->name('plugin-oidc.complete_profile.show');
        Route::post('profil-vervollstaendigen', [OidcCompleteProfileController::class, 'store'])->name('plugin-oidc.complete_profile.store');
        Route::get('{provider}/redirect', [OidcLoginController::class, 'redirect'])->name('plugin-oidc.redirect');
        Route::get('{provider}/callback', [OidcLoginController::class, 'callback'])->name('plugin-oidc.callback');
    });

    /**
     * Logged-in users: own connections
     */
    Route::middleware(['auth', 'last_activity'])->prefix('oidc/verbindungen')->group(function () {
        Route::get('/', [OidcConnectionsController::class, 'index'])->name('plugin-oidc.connections');
        Route::post('{identity}/trennen', [OidcConnectionsController::class, 'disconnect'])->whereNumber('identity')->name('plugin-oidc.connections.disconnect');
    });

    /**
     * Administrators only: same middleware as the admin area of xTIPP
     */
    Route::middleware(['auth', 'hasRole:Admin', 'last_activity'])->prefix('admin/oidc-anbieter')->group(function () {
        Route::get('/', [OidcProviderManagementController::class, 'index'])->name('plugin-oidc.admin.index');
        Route::get('erstellen', [OidcProviderManagementController::class, 'newView'])->name('plugin-oidc.admin.new');
        Route::post('erstellen', [OidcProviderManagementController::class, 'create'])->name('plugin-oidc.admin.create');
        Route::get('{id}/bearbeiten', [OidcProviderManagementController::class, 'editView'])->whereNumber('id')->name('plugin-oidc.admin.edit');
        Route::post('bearbeiten', [OidcProviderManagementController::class, 'update'])->name('plugin-oidc.admin.update');
        Route::post('{id}/loeschen', [OidcProviderManagementController::class, 'delete'])->whereNumber('id')->name('plugin-oidc.admin.delete');
    });
});
