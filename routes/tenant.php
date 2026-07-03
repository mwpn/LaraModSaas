<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\BaseFeature\Http\Controllers\BaseFeatureController;

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
    Route::get('/login', function () {
        return response()->json([
            'message' => 'Tenant login entry point.',
            'tenant_id' => tenant('id'),
        ]);
    })->name('login');

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
