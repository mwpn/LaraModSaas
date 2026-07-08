<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\SubscriptionPackage;
use App\Models\Central\TenantSubscription;
use App\Models\CentralSetting;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class TenantSubscriptionService
{
    public function __construct(
        protected SubscriptionPackageCatalogService $packageCatalogService,
    ) {
    }

    public function tablesExist(): bool
    {
        return Schema::connection($this->connectionName())->hasTable('tenant_subscriptions');
    }

    public function findForTenant(Tenant|string $tenant): ?TenantSubscription
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : (string) $tenant;

        if ($tenantId === '') {
            return null;
        }

        return TenantSubscription::query()
            ->with('package')
            ->where('tenant_id', $tenantId)
            ->first();
    }

    public function ensureForTenant(Tenant $tenant): ?TenantSubscription
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $platformType = $this->normalizedTenantSaasType($tenant);
        $package = $this->findPackageForTenant($tenant, $platformType);
        $startsAt = $this->parseTenantTimestamp(data_get($tenant, 'subscription_starts_at')) ?? CarbonImmutable::now()->startOfDay();
        $expiresAt = $this->parseTenantTimestamp(data_get($tenant, 'subscription_expires_at'));
        $graceUntil = $this->parseTenantTimestamp(data_get($tenant, 'subscription_grace_until'));

        return TenantSubscription::query()->updateOrCreate(
            ['tenant_id' => (string) $tenant->getKey()],
            [
                'platform_type' => $platformType,
                'package_id' => $package?->getKey(),
                'package_code_snapshot' => $package?->package_code ?? $tenant->packageCode() ?? null,
                'status' => $tenant->isSuspended() ? 'suspended' : $this->normalizedSubscriptionStatus((string) data_get($tenant, 'subscription_status', 'active')),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
                'assigned_at' => $this->parseTenantTimestamp(data_get($tenant, 'package_assigned_at')),
                'billing_usage_snapshot' => $tenant->billingUsageSnapshot(),
                'billing_grace_days' => $tenant->billingGraceDays(),
                'meta' => [
                    'synced_from_legacy' => true,
                ],
            ]
        );
    }

    public function assignPackage(Tenant $tenant, string $packageCode, ?CarbonImmutable $startsAt = null): ?TenantSubscription
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $platformType = $this->normalizedTenantSaasType($tenant);
        $package = SubscriptionPackage::query()
            ->where('platform_type', $platformType)
            ->where('package_code', $packageCode)
            ->where('is_enabled', true)
            ->first();

        if (! $package) {
            return $this->findForTenant($tenant);
        }

        $startsAt ??= CarbonImmutable::now()->startOfDay();
        $expiresAt = $this->expiryForPackage($package, $startsAt);

        return TenantSubscription::query()->updateOrCreate(
            ['tenant_id' => (string) $tenant->getKey()],
            [
                'platform_type' => $platformType,
                'package_id' => $package->getKey(),
                'package_code_snapshot' => $package->package_code,
                'status' => $this->normalizedSubscriptionStatus((string) data_get($tenant, 'subscription_status', 'active')),
                'starts_at' => $this->parseTenantTimestamp(data_get($tenant, 'subscription_starts_at')) ?? $startsAt,
                'expires_at' => $this->parseTenantTimestamp(data_get($tenant, 'subscription_expires_at')) ?? $expiresAt,
                'grace_until' => $this->parseTenantTimestamp(data_get($tenant, 'subscription_grace_until')),
                'assigned_at' => CarbonImmutable::now(),
                'billing_usage_snapshot' => $tenant->billingUsageSnapshot(),
                'billing_grace_days' => $tenant->billingGraceDays(),
                'meta' => [
                    'synced_from_legacy' => true,
                ],
            ]
        );
    }

    public function updateLifecycle(Tenant $tenant, array $payload): ?TenantSubscription
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $current = $this->findForTenant($tenant) ?? $this->ensureForTenant($tenant);
        $platformType = $this->normalizedTenantSaasType($tenant);
        $package = $current?->package ?? $this->findPackageForTenant($tenant, $platformType);

        return TenantSubscription::query()->updateOrCreate(
            ['tenant_id' => (string) $tenant->getKey()],
            [
                'platform_type' => $platformType,
                'package_id' => $package?->getKey(),
                'package_code_snapshot' => $package?->package_code ?? $tenant->packageCode() ?? null,
                'status' => $this->normalizedSubscriptionStatus((string) ($payload['subscription_status'] ?? data_get($tenant, 'subscription_status', 'active'))),
                'starts_at' => $this->parseTenantTimestamp($payload['subscription_starts_at'] ?? data_get($tenant, 'subscription_starts_at')),
                'expires_at' => $this->parseTenantTimestamp($payload['subscription_expires_at'] ?? data_get($tenant, 'subscription_expires_at')),
                'grace_until' => $this->parseTenantTimestamp($payload['subscription_grace_until'] ?? data_get($tenant, 'subscription_grace_until')),
                'assigned_at' => $current?->assigned_at ?? $this->parseTenantTimestamp(data_get($tenant, 'package_assigned_at')),
                'billing_usage_snapshot' => $this->normalizeUsage((array) ($payload['billing_usage'] ?? $tenant->billingUsageSnapshot())),
                'billing_grace_days' => max((int) ($payload['billing_grace_days'] ?? $tenant->billingGraceDays()), 0),
                'meta' => array_merge((array) ($current?->meta ?? []), ['synced_from_legacy' => true]),
            ]
        );
    }

    public function syncAllFromLegacy(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        Tenant::query()->orderBy('id')->each(function (Tenant $tenant): void {
            $this->ensureForTenant($tenant);
        });
    }

    public function expiryForPackage(SubscriptionPackage|array|null $package, ?CarbonImmutable $startsAt = null): ?CarbonImmutable
    {
        if ($package === null) {
            return null;
        }

        $startsAt ??= CarbonImmutable::now()->startOfDay();
        $cycle = $package instanceof SubscriptionPackage
            ? (string) $package->billing_cycle
            : (string) data_get($package, 'billing_cycle', 'monthly');

        $months = match ($cycle) {
            'yearly' => 12,
            'quarterly' => 3,
            default => 1,
        };

        return $startsAt->addMonthsNoOverflow($months)->subSecond();
    }

    protected function findPackageForTenant(Tenant $tenant, string $platformType): ?SubscriptionPackage
    {
        $packageCode = $tenant->packageCode();

        if (! is_string($packageCode) || $packageCode === '') {
            $packageCode = $this->packageCatalogService->defaultPackageCode($platformType);
        }

        if (! is_string($packageCode) || $packageCode === '') {
            return null;
        }

        $package = SubscriptionPackage::query()
            ->where('platform_type', $platformType)
            ->where('package_code', $packageCode)
            ->first();

        if ($package) {
            return $package;
        }

        $fallbackCode = $this->packageCatalogService->defaultPackageCode($platformType);

        if (! is_string($fallbackCode) || $fallbackCode === '' || $fallbackCode === $packageCode) {
            return null;
        }

        return SubscriptionPackage::query()
            ->where('platform_type', $platformType)
            ->where('package_code', $fallbackCode)
            ->first();
    }

    protected function normalizeUsage(array $usage): array
    {
        return [
            'customers' => max((int) ($usage['customers'] ?? 0), 0),
            'successful_transactions' => max((int) ($usage['successful_transactions'] ?? 0), 0),
            'checkouts' => max((int) ($usage['checkouts'] ?? 0), 0),
            'transaction_amount' => max((int) ($usage['transaction_amount'] ?? 0), 0),
        ];
    }

    protected function normalizedTenantSaasType(Tenant $tenant): string
    {
        $saasType = strtolower((string) data_get($tenant, 'saas_type', 'universal'));

        return in_array($saasType, CentralSetting::availablePlatformTypes(), true)
            ? $saasType
            : 'universal';
    }

    protected function normalizedSubscriptionStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['trial', 'active', 'grace', 'expired', 'suspended'], true)
            ? $status
            : 'active';
    }

    protected function parseTenantTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function connectionName(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
}
