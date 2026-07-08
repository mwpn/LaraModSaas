<?php

use App\Http\Controllers\Central\CentralAuthController;
use App\Http\Controllers\Central\CentralOperationsController;
use App\Http\Controllers\Central\PackageSettingsController;
use App\Http\Controllers\Central\PlatformSettingsController;
use App\Http\Controllers\Central\PublicInvoiceController;
use App\Http\Controllers\Central\RegistrationController;
use App\Http\Controllers\Central\SuperAdminLeadController;
use App\Http\Controllers\Central\SuperAdminProfileController;
use App\Http\Controllers\Central\SuperAdminTenantController;
use App\Http\Controllers\Central\SuperAdminUserController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains', []) as $domain) {
    Route::domain($domain)->middleware('web')->group(function (): void {
        Route::get('/', [PlatformSettingsController::class, 'landing'])
            ->name('central.home');

        Route::middleware('guest:central')->group(function (): void {
            Route::get('/login', [CentralAuthController::class, 'create'])
                ->name('central.login');

            Route::post('/login', [CentralAuthController::class, 'store'])
                ->middleware('throttle:central-login')
                ->name('central.login.store');

            Route::get('/register', [RegistrationController::class, 'create'])
                ->name('central.register.create');
        });

        Route::post('/logout', [CentralAuthController::class, 'destroy'])
            ->middleware('auth:central')
            ->name('central.logout');

        Route::post('/register', [RegistrationController::class, 'store'])
            ->name('central.register');

        Route::get('/pay/{tenant}/{invoice}', [PublicInvoiceController::class, 'show'])
            ->name('central.public-invoice.show');
        Route::post('/pay/{tenant}/{invoice}/qris', [PublicInvoiceController::class, 'createQris'])
            ->name('central.public-invoice.create-qris');
        Route::post('/pay/{tenant}/{invoice}/qris/check', [PublicInvoiceController::class, 'checkQrisStatus'])
            ->name('central.public-invoice.check-qris-status');
        Route::post('/pay/{tenant}/{invoice}/manual-transfer/confirm', [PublicInvoiceController::class, 'confirmManualTransfer'])
            ->middleware('throttle:manual-transfer-check')
            ->name('central.public-invoice.confirm-manual-transfer');
        Route::post('/payments/manual-transfer/evidence/bca', [PublicInvoiceController::class, 'receiveBcaEvidence'])
            ->middleware('throttle:manual-transfer-evidence')
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->name('central.public-invoice.receive-bca-evidence');

        Route::middleware('auth:central')->group(function (): void {
            Route::redirect('/admin/settings', '/super-admin/settings')
                ->name('central.admin.settings.redirect');
        });

        Route::middleware('auth:central')->prefix('super-admin')->name('central.super-admin.')->group(function (): void {
            Route::get('/profile', [SuperAdminProfileController::class, 'edit'])
                ->name('profile.edit');

            Route::patch('/profile', [SuperAdminProfileController::class, 'update'])
                ->name('profile.update');

            Route::patch('/profile/password', [SuperAdminProfileController::class, 'updatePassword'])
                ->name('profile.password.update');

            Route::middleware('central.permission:users.view')->group(function (): void {
                Route::get('/users', [SuperAdminUserController::class, 'index'])
                    ->name('users.index');
            });

            Route::middleware('central.permission:leads.view')->group(function (): void {
                Route::get('/demo-requests', [SuperAdminLeadController::class, 'index'])
                    ->name('leads.index');
            });

            Route::middleware('central.permission:users.manage')->group(function (): void {
                Route::post('/users', [SuperAdminUserController::class, 'store'])
                    ->name('users.store');

                Route::patch('/users/{id}', [SuperAdminUserController::class, 'update'])
                    ->name('users.update');

                Route::post('/users/{id}/toggle-active', [SuperAdminUserController::class, 'toggleActive'])
                    ->name('users.toggle-active');

                Route::post('/users/{id}/reset-password', [SuperAdminUserController::class, 'resetPassword'])
                    ->name('users.reset-password');
            });

            Route::middleware('central.permission:leads.manage')->group(function (): void {
                Route::post('/demo-requests/{id}/status', [SuperAdminLeadController::class, 'updateStatus'])
                    ->name('leads.update-status');

                Route::post('/demo-requests/{id}/convert', [SuperAdminLeadController::class, 'convertToTenant'])
                    ->name('leads.convert-to-tenant');
            });

            Route::middleware('central.permission:settings.view')->group(function (): void {
                Route::get('/settings', [PlatformSettingsController::class, 'edit'])
                    ->name('settings.edit');

                Route::get('/ops/health', [CentralOperationsController::class, 'health'])
                    ->name('ops.health');

                Route::get('/ops/logs', [CentralOperationsController::class, 'logs'])
                    ->name('ops.logs');

                Route::get('/ops/backup-sop', [CentralOperationsController::class, 'backupSop'])
                    ->name('ops.backup-sop');
            });

            Route::middleware('central.permission:settings.manage')->group(function (): void {
                Route::post('/settings', [PlatformSettingsController::class, 'update'])
                    ->name('settings.update');

                Route::post('/settings/test-qris', [PlatformSettingsController::class, 'testQrisConnection'])
                    ->name('settings.test-qris');

                Route::post('/settings/test-telegram', [PlatformSettingsController::class, 'testTelegramConnection'])
                    ->name('settings.test-telegram');

                Route::post('/settings/test-whatsapp', [PlatformSettingsController::class, 'testWhatsAppConnection'])
                    ->name('settings.test-whatsapp');

                Route::post('/settings/test-manual-transfer-fetcher', [PlatformSettingsController::class, 'testManualTransferFetcher'])
                    ->name('settings.test-manual-transfer-fetcher');
            });

            Route::middleware('central.permission:packages.view')->group(function (): void {
                Route::get('/packages', [PackageSettingsController::class, 'index'])
                    ->name('packages.index');
            });

            Route::middleware('central.permission:packages.manage')->group(function (): void {
                Route::get('/packages/create', [PackageSettingsController::class, 'create'])
                    ->name('packages.create');

                Route::post('/packages', [PackageSettingsController::class, 'store'])
                    ->name('packages.store');

                Route::get('/packages/{packageCode}/edit', [PackageSettingsController::class, 'edit'])
                    ->name('packages.edit');

                Route::post('/packages/{packageCode}', [PackageSettingsController::class, 'update'])
                    ->name('packages.update');

                Route::post('/packages/{packageCode}/set-default', [PackageSettingsController::class, 'setDefault'])
                    ->name('packages.set-default');

                Route::post('/packages/{packageCode}/delete', [PackageSettingsController::class, 'destroy'])
                    ->name('packages.destroy');
            });

            Route::middleware('central.permission:tenants.view')->group(function (): void {
                Route::get('/tenants', [SuperAdminTenantController::class, 'index'])
                    ->name('tenants.index');

                Route::get('/tenants/{id}', [SuperAdminTenantController::class, 'show'])
                    ->name('tenants.show');
            });

            Route::middleware('central.permission:billing.view')->group(function (): void {
                Route::get('/billing', [SuperAdminTenantController::class, 'billingDashboard'])
                    ->name('billing.index');
            });

            Route::middleware('central.permission:billing.manage')->group(function (): void {
                Route::post('/billing/generate-due-invoices', [SuperAdminTenantController::class, 'generateDueInvoices'])
                    ->name('billing.generate-due-invoices');

                Route::post('/tenants/{id}/billing', [SuperAdminTenantController::class, 'updateBilling'])
                    ->name('tenants.update-billing');

                Route::post('/tenants/{id}/billing/generate-invoice', [SuperAdminTenantController::class, 'generateInvoice'])
                    ->name('tenants.generate-invoice');

                Route::post('/tenants/{id}/billing/repair-invoices', [SuperAdminTenantController::class, 'repairInvoices'])
                    ->name('tenants.repair-invoices');

                Route::post('/tenants/billing/repair-bulk', [SuperAdminTenantController::class, 'bulkRepairInvoices'])
                    ->name('tenants.repair-bulk');

                Route::post('/tenants/{id}/billing/create-qris', [SuperAdminTenantController::class, 'createInvoiceQris'])
                    ->name('tenants.create-qris');

                Route::post('/tenants/{id}/billing/check-qris-status', [SuperAdminTenantController::class, 'checkInvoiceQrisStatus'])
                    ->name('tenants.check-qris-status');

                Route::post('/tenants/{id}/billing/mark-transfer-paid', [SuperAdminTenantController::class, 'markInvoiceManualTransferPaid'])
                    ->name('tenants.mark-transfer-paid');

                Route::post('/tenants/{id}/billing/update-invoice-status', [SuperAdminTenantController::class, 'updateInvoiceStatus'])
                    ->name('tenants.update-invoice-status');
            });

            Route::middleware('central.permission:tenants.manage')->group(function (): void {
                Route::post('/tenants/sync-platform', [SuperAdminTenantController::class, 'syncPlatform'])
                    ->name('tenants.sync-platform');

                Route::post('/tenants/{id}/users', [SuperAdminTenantController::class, 'storeTenantUser'])
                    ->name('tenants.users.store');

                Route::patch('/tenants/{id}/users/{userId}', [SuperAdminTenantController::class, 'updateTenantUser'])
                    ->name('tenants.users.update');

                Route::post('/tenants/{id}/users/{userId}/toggle-active', [SuperAdminTenantController::class, 'toggleTenantUserActive'])
                    ->name('tenants.users.toggle-active');

                Route::post('/tenants/{id}/users/{userId}/reset-password', [SuperAdminTenantController::class, 'resetTenantUserPassword'])
                    ->name('tenants.users.reset-password');

                Route::post('/tenants/{id}/suspend', [SuperAdminTenantController::class, 'suspend'])
                    ->name('tenants.suspend');

                Route::post('/tenants/{id}/activate', [SuperAdminTenantController::class, 'activate'])
                    ->name('tenants.activate');

                Route::post('/tenants/{id}/assign-package', [SuperAdminTenantController::class, 'assignPackage'])
                    ->name('tenants.assign-package');

                Route::post('/tenants/{id}/switch-saas', [SuperAdminTenantController::class, 'switchSaas'])
                    ->name('tenants.switch-saas');

                Route::post('/tenants/{id}/modules/{moduleName}/toggle', [SuperAdminTenantController::class, 'toggleModule'])
                    ->name('tenants.toggle-module');

                Route::post('/tenants/{id}/delete', [SuperAdminTenantController::class, 'destroy'])
                    ->name('tenants.destroy');
            });
        });
    });
}
