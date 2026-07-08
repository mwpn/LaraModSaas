<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\TenantAuthController as TenantAccessController;
use App\Http\Controllers\Tenant\TenantDashboardController;
use App\Http\Controllers\Tenant\TirtaBillingController;
use App\Http\Controllers\Tenant\TirtaMasterDataController;
use App\Http\Controllers\Tenant\TirtaMeterReadingController;
use App\Http\Controllers\Tenant\TenantProfileController;
use App\Http\Controllers\Tenant\TirtaWarehouseController;
use App\Http\Controllers\Tenant\TirtaWorkspaceController;
use App\Http\Controllers\Tenant\TenantUserController;
use App\Http\Controllers\Tenant\TenantSettingsController;
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
            ->middleware('throttle:tenant-login')
            ->name('tenant.login.store');
    });

    Route::post('/logout', [TenantAccessController::class, 'destroy'])
        ->middleware('auth:tenant')
        ->name('tenant.logout');

    Route::get('/', [BaseFeatureController::class, 'landing'])
        ->name('tenant.home');

    Route::middleware('auth:tenant')->group(function (): void {
        Route::get('/dashboard', [TenantDashboardController::class, 'index'])
            ->name('tenant.dashboard');

        Route::get('/dashboard/profile', [TenantProfileController::class, 'edit'])
            ->name('tenant.profile.edit');

        Route::patch('/dashboard/profile', [TenantProfileController::class, 'update'])
            ->name('tenant.profile.update');

        Route::patch('/dashboard/profile/password', [TenantProfileController::class, 'updatePassword'])
            ->name('tenant.profile.password.update');

        Route::get('/dashboard/tirta', [TirtaWorkspaceController::class, 'index'])
            ->name('tenant.tirta.workspace');

        Route::get('/dashboard/tirta/master-data', [TirtaMasterDataController::class, 'index'])
            ->name('tenant.tirta.master-data');

        Route::get('/dashboard/tirta/meter-readings', [TirtaMeterReadingController::class, 'index'])
            ->name('tenant.tirta.meter-readings');
        Route::get('/dashboard/tirta/meter-verification', [TirtaMeterReadingController::class, 'verifierDashboard'])
            ->name('tenant.tirta.meter-verification');

        Route::get('/dashboard/tirta/billing', [TirtaBillingController::class, 'index'])
            ->name('tenant.tirta.billing');

        Route::get('/dashboard/tirta/warehouse', [TirtaWarehouseController::class, 'index'])
            ->name('tenant.tirta.warehouse');

        Route::post('/dashboard/tirta/meter-reading-periods', [TirtaMeterReadingController::class, 'storePeriod'])
            ->name('tenant.tirta.meter-reading-periods.store');
        Route::patch('/dashboard/tirta/meter-reading-periods/{id}', [TirtaMeterReadingController::class, 'updatePeriod'])
            ->name('tenant.tirta.meter-reading-periods.update');
        Route::post('/dashboard/tirta/meter-settings/cycle', [TirtaMeterReadingController::class, 'updateCycleSettings'])
            ->name('tenant.tirta.meter-settings.cycle');
        Route::post('/dashboard/tirta/meter-reader-assignments', [TirtaMeterReadingController::class, 'storeAssignment'])
            ->name('tenant.tirta.meter-reader-assignments.store');
        Route::patch('/dashboard/tirta/meter-reader-assignments/{id}', [TirtaMeterReadingController::class, 'updateAssignment'])
            ->name('tenant.tirta.meter-reader-assignments.update');

        Route::post('/dashboard/tirta/meter-readings', [TirtaMeterReadingController::class, 'storeReading'])
            ->name('tenant.tirta.meter-readings.store');
        Route::patch('/dashboard/tirta/meter-readings/{id}', [TirtaMeterReadingController::class, 'updateReading'])
            ->name('tenant.tirta.meter-readings.update');
        Route::post('/dashboard/tirta/meter-readings/{id}/review', [TirtaMeterReadingController::class, 'reviewReading'])
            ->name('tenant.tirta.meter-readings.review');

        Route::post('/dashboard/tirta/billing-periods', [TirtaBillingController::class, 'storePeriod'])
            ->name('tenant.tirta.billing-periods.store');
        Route::patch('/dashboard/tirta/billing-periods/{id}', [TirtaBillingController::class, 'updatePeriod'])
            ->name('tenant.tirta.billing-periods.update');
        Route::post('/dashboard/tirta/billing-periods/{id}/generate', [TirtaBillingController::class, 'generateInvoices'])
            ->name('tenant.tirta.billing-periods.generate');
        Route::post('/dashboard/tirta/billing-periods/{id}/post-penalties', [TirtaBillingController::class, 'postPeriodPenalties'])
            ->name('tenant.tirta.billing-periods.penalties.post');
        Route::post('/dashboard/tirta/billing/settings/penalty', [TirtaBillingController::class, 'updatePenaltySettings'])
            ->name('tenant.tirta.billing.settings.penalty');
        Route::post('/dashboard/tirta/billing/settings/lifecycle', [TirtaBillingController::class, 'updateLifecycleSettings'])
            ->name('tenant.tirta.billing.settings.lifecycle');
        Route::post('/dashboard/tirta/billing/settings/installation', [TirtaBillingController::class, 'updateInstallationSettings'])
            ->name('tenant.tirta.billing.settings.installation');
        Route::patch('/dashboard/tirta/billing-invoices/{id}', [TirtaBillingController::class, 'updateInvoice'])
            ->name('tenant.tirta.billing-invoices.update');
        Route::post('/dashboard/tirta/billing-invoices/{id}/post-penalty', [TirtaBillingController::class, 'postPenalty'])
            ->name('tenant.tirta.billing-invoices.penalty.post');
        Route::post('/dashboard/tirta/billing-invoices/{id}/payments', [TirtaBillingController::class, 'storePayment'])
            ->name('tenant.tirta.billing-invoices.payments.store');
        Route::post('/dashboard/tirta/service-connections/{id}/reactivate', [TirtaBillingController::class, 'requestReactivation'])
            ->name('tenant.tirta.service-connections.reactivate');
        Route::post('/dashboard/tirta/service-connections/{id}/installation', [TirtaMasterDataController::class, 'requestInstallation'])
            ->name('tenant.tirta.service-connections.installation');

        Route::post('/dashboard/tirta/service-areas', [TirtaMasterDataController::class, 'storeServiceArea'])
            ->name('tenant.tirta.service-areas.store');
        Route::patch('/dashboard/tirta/service-areas/{id}', [TirtaMasterDataController::class, 'updateServiceArea'])
            ->name('tenant.tirta.service-areas.update');

        Route::post('/dashboard/tirta/service-categories', [TirtaMasterDataController::class, 'storeServiceCategory'])
            ->name('tenant.tirta.service-categories.store');
        Route::patch('/dashboard/tirta/service-categories/{id}', [TirtaMasterDataController::class, 'updateServiceCategory'])
            ->name('tenant.tirta.service-categories.update');

        Route::post('/dashboard/tirta/customers', [TirtaMasterDataController::class, 'storeCustomer'])
            ->name('tenant.tirta.customers.store');
        Route::patch('/dashboard/tirta/customers/{id}', [TirtaMasterDataController::class, 'updateCustomer'])
            ->name('tenant.tirta.customers.update');

        Route::post('/dashboard/tirta/connections', [TirtaMasterDataController::class, 'storeConnection'])
            ->name('tenant.tirta.connections.store');
        Route::patch('/dashboard/tirta/connections/{id}', [TirtaMasterDataController::class, 'updateConnection'])
            ->name('tenant.tirta.connections.update');

        Route::post('/dashboard/tirta/tariffs', [TirtaMasterDataController::class, 'storeTariffScheme'])
            ->name('tenant.tirta.tariffs.store');
        Route::patch('/dashboard/tirta/tariffs/{id}', [TirtaMasterDataController::class, 'updateTariffScheme'])
            ->name('tenant.tirta.tariffs.update');

        Route::post('/dashboard/tirta/warehouse/locations', [TirtaWarehouseController::class, 'storeLocation'])
            ->name('tenant.tirta.warehouse.locations.store');
        Route::post('/dashboard/tirta/warehouse/items', [TirtaWarehouseController::class, 'storeItem'])
            ->name('tenant.tirta.warehouse.items.store');
        Route::post('/dashboard/tirta/warehouse/movements', [TirtaWarehouseController::class, 'storeMovement'])
            ->name('tenant.tirta.warehouse.movements.store');
        Route::post('/dashboard/tirta/warehouse/requests', [TirtaWarehouseController::class, 'storeRequest'])
            ->name('tenant.tirta.warehouse.requests.store');
        Route::post('/dashboard/tirta/warehouse/requests/{id}/approve', [TirtaWarehouseController::class, 'approveRequest'])
            ->name('tenant.tirta.warehouse.requests.approve');
        Route::post('/dashboard/tirta/warehouse/requests/{id}/complete', [TirtaWarehouseController::class, 'completeRequest'])
            ->name('tenant.tirta.warehouse.requests.complete');
        Route::post('/dashboard/tirta/warehouse/suppliers', [TirtaWarehouseController::class, 'storeSupplier'])
            ->name('tenant.tirta.warehouse.suppliers.store');

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

        Route::get('/dashboard/settings', [TenantSettingsController::class, 'edit'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings');

        Route::post('/dashboard/settings', [TenantSettingsController::class, 'update'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings.update');

        Route::post('/dashboard/settings/job-titles', [TenantSettingsController::class, 'storeJobTitle'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings.job-titles.store');

        Route::patch('/dashboard/settings/job-titles/{id}', [TenantSettingsController::class, 'updateJobTitle'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings.job-titles.update');

        Route::delete('/dashboard/settings/job-titles/{id}', [TenantSettingsController::class, 'destroyJobTitle'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings.job-titles.destroy');

        Route::post('/dashboard/settings/roles', [TenantSettingsController::class, 'storeRole'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings.roles.store');

        Route::patch('/dashboard/settings/roles/{id}', [TenantSettingsController::class, 'updateRole'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings.roles.update');

        Route::delete('/dashboard/settings/roles/{id}', [TenantSettingsController::class, 'destroyRole'])
            ->middleware('tenant.not_meter_reader')
            ->name('tenant.settings.roles.destroy');
    });
});
