<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\PlatformModule;
use App\Models\Central\SubscriptionPackage;
use App\Models\Central\SubscriptionPackageFeature;
use App\Models\Central\SubscriptionPackageLimit;
use App\Models\Central\SubscriptionPackageModule;
use App\Models\CentralSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionPackageCatalogService
{
    public function __construct(
        protected PlatformModuleCatalogService $moduleCatalogService,
    ) {
    }

    public function tablesExist(): bool
    {
        $schema = Schema::connection($this->connectionName());

        return $schema->hasTable('subscription_packages')
            && $schema->hasTable('subscription_package_modules')
            && $schema->hasTable('subscription_package_features')
            && $schema->hasTable('subscription_package_limits');
    }

    public function hasRelationalCatalog(?string $platformType = null): bool
    {
        if (! $this->tablesExist()) {
            return false;
        }

        $query = SubscriptionPackage::query();

        if (is_string($platformType) && $platformType !== '') {
            $query->where('platform_type', $platformType);
        }

        return $query->exists();
    }

    public function catalogAsLegacy(?string $platformType = null): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $platformType ??= CentralSetting::platformSaasType();

        return SubscriptionPackage::query()
            ->with(['modules.module', 'features', 'limits'])
            ->where('platform_type', $platformType)
            ->orderBy('sort_order')
            ->orderBy('package_code')
            ->get()
            ->mapWithKeys(function (SubscriptionPackage $package): array {
                $features = collect(CentralSetting::packageFeatureCatalog())
                    ->mapWithKeys(fn (array $_feature, string $featureCode): array => [
                        $featureCode => (bool) optional(
                            $package->features->firstWhere('feature_code', $featureCode)
                        )->is_enabled,
                    ])
                    ->all();

                $limits = collect(['max_admin_users', 'max_staff_users', 'max_customers', 'max_monthly_transactions'])
                    ->mapWithKeys(fn (string $limitCode): array => [
                        $limitCode => optional(
                            $package->limits->firstWhere('limit_code', $limitCode)
                        )->limit_value,
                    ])
                    ->all();

                return [
                    $package->package_code => [
                        'code' => $package->package_code,
                        'label' => $package->label,
                        'description' => (string) $package->description,
                        'price_monthly' => (int) $package->base_price,
                        'billing_cycle' => $package->billing_cycle,
                        'enabled' => (bool) $package->is_enabled,
                        'highlight' => (bool) $package->is_highlighted,
                        'sort_order' => (int) $package->sort_order,
                        'limits' => $limits,
                        'features' => $features,
                        'modules' => $package->modules
                            ->map(fn (SubscriptionPackageModule $link): ?string => $link->module?->module_name)
                            ->filter()
                            ->prepend('BaseFeature')
                            ->unique()
                            ->values()
                            ->all(),
                        'billing_components' => is_array($package->billing_components)
                            ? $package->billing_components
                            : [],
                    ],
                ];
            })
            ->all();
    }

    public function replaceCatalogFromLegacy(array $packages, string $platformType): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        DB::connection($this->connectionName())->transaction(function () use ($packages, $platformType): void {
            $moduleMap = PlatformModule::query()
                ->whereIn('platform_type', $platformType === 'universal' ? ['universal'] : [$platformType, 'universal'])
                ->pluck('id', 'module_name');

            $keptIds = [];

            foreach ($packages as $packageCode => $definition) {
                if (! is_array($definition) || ! is_string($packageCode) || trim($packageCode) === '') {
                    continue;
                }

                $package = SubscriptionPackage::query()->updateOrCreate(
                    ['package_code' => $packageCode],
                    [
                        'platform_type' => $platformType,
                        'label' => trim((string) ($definition['label'] ?? $packageCode)),
                        'description' => trim((string) ($definition['description'] ?? '')),
                        'billing_cycle' => in_array(($definition['billing_cycle'] ?? 'monthly'), ['monthly', 'quarterly', 'yearly'], true)
                            ? (string) $definition['billing_cycle']
                            : 'monthly',
                        'base_price' => max((int) ($definition['price_monthly'] ?? 0), 0),
                        'currency' => 'IDR',
                        'is_enabled' => (bool) ($definition['enabled'] ?? true),
                        'is_highlighted' => (bool) ($definition['highlight'] ?? false),
                        'sort_order' => max((int) ($definition['sort_order'] ?? 1), 1),
                        'billing_components' => (array) ($definition['billing_components'] ?? []),
                    ]
                );

                $keptIds[] = $package->getKey();

                $this->syncFeatures($package, (array) ($definition['features'] ?? []));
                $this->syncLimits($package, (array) ($definition['limits'] ?? []));
                $this->syncModules($package, (array) ($definition['modules'] ?? []), $moduleMap->all());
            }

            SubscriptionPackage::query()
                ->where('platform_type', $platformType)
                ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
                ->delete();
        });
    }

    public function defaultPackageCode(?string $platformType = null): ?string
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $platformType ??= CentralSetting::platformSaasType();

        $default = SubscriptionPackage::query()
            ->where('platform_type', $platformType)
            ->where('is_default', true)
            ->where('is_enabled', true)
            ->value('package_code');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        $fallback = SubscriptionPackage::query()
            ->where('platform_type', $platformType)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->value('package_code');

        return is_string($fallback) && $fallback !== ''
            ? $fallback
            : null;
    }

    public function setDefaultPackageCode(string $packageCode, string $platformType): ?string
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $package = SubscriptionPackage::query()
            ->where('platform_type', $platformType)
            ->where('package_code', $packageCode)
            ->where('is_enabled', true)
            ->first();

        if (! $package) {
            return $this->defaultPackageCode($platformType);
        }

        SubscriptionPackage::query()
            ->where('platform_type', $platformType)
            ->update(['is_default' => false]);

        $package->forceFill(['is_default' => true])->save();

        return $package->package_code;
    }

    public function syncFromLegacySettings(?string $platformType = null): void
    {
        $platformTypes = $platformType
            ? [$platformType]
            : CentralSetting::availablePlatformTypes();

        foreach ($platformTypes as $type) {
            $this->replaceCatalogFromLegacy(CentralSetting::legacyPackageCatalog($type), $type);
            $defaultPackageCode = CentralSetting::legacyDefaultPackageCode($type);

            if (is_string($defaultPackageCode) && $defaultPackageCode !== '') {
                $this->setDefaultPackageCode($defaultPackageCode, $type);
            }
        }
    }

    protected function syncFeatures(SubscriptionPackage $package, array $features): void
    {
        SubscriptionPackageFeature::query()
            ->where('package_id', $package->getKey())
            ->delete();

        foreach (CentralSetting::packageFeatureCatalog() as $featureCode => $_feature) {
            SubscriptionPackageFeature::query()->create([
                'package_id' => $package->getKey(),
                'feature_code' => $featureCode,
                'is_enabled' => (bool) ($features[$featureCode] ?? false),
                'config' => null,
            ]);
        }
    }

    protected function syncLimits(SubscriptionPackage $package, array $limits): void
    {
        SubscriptionPackageLimit::query()
            ->where('package_id', $package->getKey())
            ->delete();

        foreach (['max_admin_users', 'max_staff_users', 'max_customers', 'max_monthly_transactions'] as $limitCode) {
            SubscriptionPackageLimit::query()->create([
                'package_id' => $package->getKey(),
                'limit_code' => $limitCode,
                'limit_value' => $this->nullableLimitValue($limits[$limitCode] ?? null),
            ]);
        }
    }

    protected function syncModules(SubscriptionPackage $package, array $moduleNames, array $moduleMap): void
    {
        SubscriptionPackageModule::query()
            ->where('package_id', $package->getKey())
            ->delete();

        $normalizedNames = collect($moduleNames)
            ->filter(fn ($moduleName): bool => is_string($moduleName) && $moduleName !== '')
            ->prepend('BaseFeature')
            ->unique()
            ->values();

        foreach ($normalizedNames as $moduleName) {
            $moduleId = $moduleMap[$moduleName] ?? null;

            if (! $moduleId) {
                continue;
            }

            SubscriptionPackageModule::query()->create([
                'package_id' => $package->getKey(),
                'module_id' => $moduleId,
                'access_mode' => 'included',
                'is_enabled_by_default' => true,
                'notes' => null,
            ]);
        }
    }

    protected function nullableLimitValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max((int) $value, 1);
    }

    protected function connectionName(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }
}
