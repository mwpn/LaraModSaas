<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\TenantAuthController as TenantAccessController;
use App\Http\Controllers\Tenant\TenantUserController;
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
    Route::middleware('guest:tenant')->group(function (): void {
        Route::get('/login', [TenantAccessController::class, 'create'])
            ->name('login');

        Route::post('/login', [TenantAccessController::class, 'store'])
            ->name('tenant.login.store');
    });

    Route::post('/logout', [TenantAccessController::class, 'destroy'])
        ->middleware('auth:tenant')
        ->name('tenant.logout');

    Route::get('/', [BaseFeatureController::class, 'landing'])
        ->name('tenant.home');

    Route::middleware('auth:tenant')->group(function (): void {
        Route::get('/dashboard', [BaseFeatureController::class, 'dashboard'])
            ->name('tenant.dashboard');

        Route::get('/dashboard/users', [TenantUserController::class, 'index'])
            ->name('tenant.users.index');

        Route::post('/dashboard/users', [TenantUserController::class, 'store'])
            ->name('tenant.users.store');

        Route::patch('/dashboard/users/{id}', [TenantUserController::class, 'update'])
            ->name('tenant.users.update');

        Route::post('/dashboard/users/{id}/toggle-active', [TenantUserController::class, 'toggleActive'])
            ->name('tenant.users.toggle-active');

        Route::post('/dashboard/users/{id}/reset-password', [TenantUserController::class, 'resetPassword'])
            ->name('tenant.users.reset-password');

        Route::get('/dashboard/settings', [BaseFeatureController::class, 'settings'])
            ->name('tenant.settings');

        Route::post('/dashboard/settings', [BaseFeatureController::class, 'updateSettings'])
            ->name('tenant.settings.update');
    });
});
