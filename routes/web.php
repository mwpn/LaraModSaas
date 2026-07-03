<?php

use App\Http\Controllers\Central\CentralAuthController;
use App\Http\Controllers\Central\RegistrationController;
use App\Http\Controllers\Central\SuperAdminTenantController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->middleware('web')->group(function (): void {
        Route::get('/', function () {
            return view('central.landing');
        })->name('central.home');

        Route::middleware('guest:central')->group(function (): void {
            Route::get('/login', [CentralAuthController::class, 'create'])
                ->name('central.login');

            Route::post('/login', [CentralAuthController::class, 'store'])
                ->name('central.login.store');
        });

        Route::post('/logout', [CentralAuthController::class, 'destroy'])
            ->middleware('auth:central')
            ->name('central.logout');

        Route::post('/register', [RegistrationController::class, 'store'])
            ->name('central.register');

        Route::middleware('auth:central')->prefix('super-admin')->name('central.super-admin.')->group(function (): void {
            Route::get('/tenants', [SuperAdminTenantController::class, 'index'])
                ->name('tenants.index');

            Route::post('/tenants/{id}/switch-saas', [SuperAdminTenantController::class, 'switchSaas'])
                ->name('tenants.switch-saas');
        });
    });
}
