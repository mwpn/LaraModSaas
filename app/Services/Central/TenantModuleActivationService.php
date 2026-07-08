<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\PlatformModule;
use App\Models\Central\TenantModuleState;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TenantModuleActivationService
{
    public function __construct(
        protected TenantSubscriptionService $tenantSubscriptionService,
    ) {
    }

    public function tablesExist(): bool
    {
        return Schema::connection($this->connectionName())->hasTable('tenant_module_states');
    }

    public function syncForTenant(Tenant $tenant): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $subscription = $this->tenantSubscriptionService->findForTenant($tenant)
            ?? $this->tenantSubscriptionService->ensureForTenant($tenant);
        $platformType = $this->normalizedTenantSaasType($tenant);

        $catalog = PlatformModule::query()
            ->whereIn('platform_type', $platformType === 'universal' ? ['universal'] : [$platformType, 'universal'])
            ->orderBy('sort_order')
            ->get()
            ->keyBy('module_name');

        $packageLinks = collect($subscription?->package?->modules ?? [])
            ->filter(fn ($link) => $link->module?->module_name)
            ->keyBy(fn ($link) => $link->module->module_name);
        $allowedByPackage = $packageLinks->keys()
            ->prepend('BaseFeature')
            ->unique()
            ->values()
            ->all();
        $existingStates = TenantModuleState::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->get()
            ->keyBy('module_id');

        foreach ($catalog as $moduleName => $module) {
            $existing = $existingStates->get($module->getKey());
            $packageLink = $packageLinks->get($moduleName);
            $allowed = $module->is_required || in_array($moduleName, $allowedByPackage, true);
            $now = CarbonImmutable::now();
            $defaultEnabled = $allowed && (
                $module->is_required
                || $moduleName === 'BaseFeature'
                || (bool) ($packageLink?->is_enabled_by_default ?? $module->is_default_enabled)
            );

            $enabled = $defaultEnabled;
            $enabledSource = $module->is_required || $moduleName === 'BaseFeature'
                ? 'required'
                : ($defaultEnabled ? 'package_default' : null);

            if ($allowed && $existing instanceof TenantModuleState && $existing->enabled_source === 'tenant_toggle') {
                $enabled = $existing->status === 'enabled';
                $enabledSource = 'tenant_toggle';
            }

            TenantModuleState::query()->updateOrCreate(
                [
                    'tenant_id' => (string) $tenant->getKey(),
                    'module_id' => $module->getKey(),
                ],
                [
                    'status' => $enabled ? 'enabled' : ($allowed ? 'disabled' : 'blocked'),
                    'enabled_source' => $allowed ? $enabledSource : null,
                    'reason_code' => $allowed ? null : 'package_not_allowed',
                    'is_allowed' => $allowed,
                    'enabled_at' => $enabled
                        ? ($existing?->enabled_at ?? $now)
                        : null,
                    'disabled_at' => $enabled
                        ? null
                        : ($existing?->disabled_at ?? $now),
                    'meta' => [
                        'module_name' => $moduleName,
                        'platform_type' => $module->platform_type,
                        'toggleable' => $allowed && ! $module->is_required && $moduleName !== 'BaseFeature',
                    ],
                ]
            );
        }
    }

    public function syncAllFromLegacy(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant): void {
            $this->syncForTenant($tenant);
        });
    }

    public function statesForTenant(Tenant $tenant): Collection
    {
        if (! $this->tablesExist()) {
            return collect();
        }

        $states = TenantModuleState::query()
            ->with('module')
            ->where('tenant_id', (string) $tenant->getKey())
            ->get();

        if ($states->isEmpty()) {
            $this->syncForTenant($tenant);

            $states = TenantModuleState::query()
                ->with('module')
                ->where('tenant_id', (string) $tenant->getKey())
                ->get();
        }

        return $states->sortBy(
            fn (TenantModuleState $state): string => sprintf(
                '%05d-%s',
                (int) ($state->module?->sort_order ?? 9999),
                (string) ($state->module?->module_name ?? 'zzz')
            )
        )->values();
    }

    public function summaryForTenant(Tenant $tenant): array
    {
        $states = $this->statesForTenant($tenant);

        $items = $states->map(function (TenantModuleState $state): array {
            $module = $state->module;
            $moduleName = (string) ($module?->module_name ?? data_get($state->meta, 'module_name', 'UnknownModule'));
            $status = (string) $state->status;
            $allowed = (bool) $state->is_allowed;
            $toggleable = (bool) data_get($state->meta, 'toggleable', false);

            return [
                'module_name' => $moduleName,
                'module_code' => (string) ($module?->module_code ?? ''),
                'label' => (string) ($module?->label ?? $moduleName),
                'description' => (string) ($module?->description ?? ''),
                'platform_type' => (string) ($module?->platform_type ?? ''),
                'status' => $status,
                'status_label' => match ($status) {
                    'enabled' => 'Enabled',
                    'blocked' => 'Blocked',
                    default => 'Disabled',
                },
                'is_allowed' => $allowed,
                'is_required' => (bool) ($module?->is_required ?? false),
                'toggleable' => $toggleable,
                'enabled_source' => (string) ($state->enabled_source ?? ''),
                'reason_code' => (string) ($state->reason_code ?? ''),
            ];
        })->values();

        return [
            'items' => $items->all(),
            'enabled' => $items->where('status', 'enabled')->values()->all(),
            'disabled' => $items->where('status', 'disabled')->values()->all(),
            'blocked' => $items->where('status', 'blocked')->values()->all(),
            'allowed' => $items->where('is_allowed', true)->values()->all(),
            'counts' => [
                'total' => $items->count(),
                'enabled' => $items->where('status', 'enabled')->count(),
                'disabled' => $items->where('status', 'disabled')->count(),
                'blocked' => $items->where('status', 'blocked')->count(),
                'toggleable' => $items->where('toggleable', true)->count(),
            ],
        ];
    }

    public function setTenantToggle(Tenant $tenant, string $moduleName, bool $enabled): ?TenantModuleState
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $this->syncForTenant($tenant);

        $state = TenantModuleState::query()
            ->with('module')
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereHas('module', fn ($query) => $query->where('module_name', $moduleName))
            ->first();

        if (! $state || ! $state->is_allowed || (bool) ($state->module?->is_required ?? false) || $moduleName === 'BaseFeature') {
            return $state;
        }

        $now = CarbonImmutable::now();

        $state->forceFill([
            'status' => $enabled ? 'enabled' : 'disabled',
            'enabled_source' => 'tenant_toggle',
            'reason_code' => null,
            'enabled_at' => $enabled ? $now : null,
            'disabled_at' => $enabled ? null : $now,
            'meta' => array_merge((array) ($state->meta ?? []), [
                'toggleable' => true,
                'manual_override' => true,
            ]),
        ])->save();

        return $state->fresh(['module']);
    }

    protected function normalizedTenantSaasType(Tenant $tenant): string
    {
        $saasType = strtolower((string) data_get($tenant, 'saas_type', 'universal'));

        return in_array($saasType, \App\Models\CentralSetting::availablePlatformTypes(), true)
            ? $saasType
            : 'universal';
    }

    protected function connectionName(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
}
