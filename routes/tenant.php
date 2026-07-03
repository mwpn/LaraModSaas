<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\BaseFeature\Http\Controllers\BaseFeatureController;
use Modules\BaseFeature\Http\Controllers\TenantAuthController;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware('tenant_api_web')->group(function () {
    Route::middleware('guest:tenant')->group(function (): void {
        Route::get('/login', [TenantAuthController::class, 'create'])
            ->name('login');

        Route::post('/login', [TenantAuthController::class, 'store'])
            ->name('tenant.login.store');
    });

    Route::post('/logout', [TenantAuthController::class, 'destroy'])
        ->middleware('auth:tenant')
        ->name('tenant.logout');

    Route::get('/', [BaseFeatureController::class, 'landing'])
        ->name('tenant.home');

    Route::middleware('auth:tenant')->group(function (): void {
        Route::get('/dashboard', [BaseFeatureController::class, 'dashboard'])
            ->name('tenant.dashboard');

        Route::get('/dashboard/settings', [BaseFeatureController::class, 'settings'])
            ->name('tenant.settings');

        Route::post('/dashboard/settings', [BaseFeatureController::class, 'updateSettings'])
            ->name('tenant.settings.update');
    });
});
