<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\TenantModuleState;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

class TenantEntitlementResolver
{
    public function __construct(
        protected TenantModuleActivationService $moduleActivationService,
    ) {
    }

    public function enabledModuleNames(Tenant $tenant): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $states = TenantModuleState::query()
            ->with('module')
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('is_allowed', true)
            ->where('status', 'enabled')
            ->get();

        if ($states->isEmpty()) {
            $this->moduleActivationService->syncForTenant($tenant);

            $states = TenantModuleState::query()
                ->with('module')
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('is_allowed', true)
                ->where('status', 'enabled')
                ->get();
        }

        return $states
            ->map(fn (TenantModuleState $state): ?string => $state->module?->module_name)
            ->filter()
            ->prepend('BaseFeature')
            ->unique()
            ->values()
            ->all();
    }

    public function isEnabled(Tenant $tenant, string $moduleName): bool
    {
        return in_array($moduleName, $this->enabledModuleNames($tenant), true);
    }

    public function summary(Tenant $tenant): array
    {
        return $this->moduleActivationService->summaryForTenant($tenant);
    }

    protected function tablesExist(): bool
    {
        return Schema::connection(
            config('tenancy.database.central_connection', config('database.default'))
        )->hasTable('tenant_module_states');
    }
}
