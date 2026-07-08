<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\PlatformModule;
use App\Models\CentralSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlatformModuleCatalogService
{
    public function tablesExist(): bool
    {
        return Schema::connection($this->connectionName())
            ->hasTable('platform_module_catalog');
    }

    public function hasRelationalCatalog(?string $platformType = null): bool
    {
        if (! $this->tablesExist()) {
            return false;
        }

        $query = PlatformModule::query();

        if (is_string($platformType) && $platformType !== '') {
            $query->whereIn('platform_type', $this->platformScope($platformType));
        }

        return $query->exists();
    }

    public function catalogAsLegacy(?string $platformType = null): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $query = PlatformModule::query()->orderBy('sort_order')->orderBy('module_name');

        if (is_string($platformType) && $platformType !== '') {
            $query->whereIn('platform_type', $this->platformScope($platformType));
        }

        return $query->get()
            ->groupBy('module_name')
            ->mapWithKeys(function (Collection $group, string $moduleName): array {
                /** @var PlatformModule $first */
                $first = $group->first();
                $platforms = $group->pluck('platform_type')
                    ->flatMap(fn (string $type): array => $type === 'universal'
                        ? CentralSetting::availablePlatformTypes()
                        : [$type]
                    )
                    ->unique()
                    ->values()
                    ->all();

                return [
                    $moduleName => [
                        'label' => $first->label,
                        'description' => (string) $first->description,
                        'required' => $group->contains(fn (PlatformModule $module): bool => $module->is_required),
                        'platforms' => $platforms,
                    ],
                ];
            })
            ->all();
    }

    public function activeModuleNames(string $platformType): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        return PlatformModule::query()
            ->whereIn('platform_type', $this->platformScope($platformType))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('module_name')
            ->prepend('BaseFeature')
            ->filter(fn ($name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function setActiveModulesForPlatform(array $moduleNames, string $platformType): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $selected = collect($moduleNames)
            ->filter(fn ($moduleName): bool => is_string($moduleName) && $moduleName !== '')
            ->prepend('BaseFeature')
            ->unique()
            ->values()
            ->all();

        PlatformModule::query()
            ->where('platform_type', $platformType)
            ->update(['is_active' => false]);

        if ($selected === []) {
            return;
        }

        PlatformModule::query()
            ->where('platform_type', $platformType)
            ->whereIn('module_name', $selected)
            ->update(['is_active' => true]);
    }

    public function syncFromLegacyCatalog(array $legacyCatalog): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $rows = collect($legacyCatalog)
            ->filter(fn ($definition, $moduleName): bool => is_string($moduleName) && is_array($definition))
            ->map(function (array $definition, string $moduleName): array {
                $platforms = array_values(array_filter(
                    (array) ($definition['platforms'] ?? []),
                    fn ($platform): bool => is_string($platform) && $platform !== ''
                ));

                $platformType = collect(CentralSetting::availablePlatformTypes())->diff($platforms)->isEmpty()
                    ? 'universal'
                    : (string) ($platforms[0] ?? 'universal');

                return [
                    'module_code' => Str::snake($moduleName),
                    'module_name' => $moduleName,
                    'platform_type' => $platformType,
                    'domain_group' => $this->domainGroupForModule($moduleName),
                    'label' => (string) ($definition['label'] ?? $moduleName),
                    'description' => (string) ($definition['description'] ?? ''),
                    'is_required' => (bool) ($definition['required'] ?? false),
                    'is_default_enabled' => (bool) ($definition['required'] ?? false),
                    'is_addon' => false,
                    'subscription_visible' => true,
                    'depends_on' => [],
                    'sort_order' => $this->sortOrderForModule($moduleName),
                    'is_active' => true,
                ];
            })
            ->values();

        DB::connection($this->connectionName())->transaction(function () use ($rows): void {
            $moduleNames = $rows->pluck('module_name')->all();

            PlatformModule::query()
                ->whereNotIn('module_name', $moduleNames)
                ->delete();

            foreach ($rows as $row) {
                PlatformModule::query()->updateOrCreate(
                    ['module_name' => $row['module_name']],
                    $row
                );
            }
        });
    }

    protected function connectionName(): string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    protected function platformScope(string $platformType): array
    {
        return $platformType === 'universal'
            ? ['universal']
            : array_values(array_unique([$platformType, 'universal']));
    }

    protected function domainGroupForModule(string $moduleName): string
    {
        return match ($moduleName) {
            'BaseFeature' => 'core',
            'RestoPOS', 'HospitalityHub', 'TirtaBilling', 'NetBilling' => 'operations',
            default => 'general',
        };
    }

    protected function sortOrderForModule(string $moduleName): int
    {
        return match ($moduleName) {
            'BaseFeature' => 1,
            'RestoPOS', 'HospitalityHub', 'TirtaBilling', 'NetBilling' => 10,
            default => 100,
        };
    }
}
