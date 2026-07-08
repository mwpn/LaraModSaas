<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Concerns;

use App\Models\Tirta\Customer;
use App\Models\Tirta\ServiceArea;
use App\Models\Tirta\ServiceConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

trait InteractsWithTirtaAreaScope
{
    protected ?array $tirtaAreaScopeContext = null;

    protected function tirtaAreaScopeContext(): array
    {
        if (is_array($this->tirtaAreaScopeContext)) {
            return $this->tirtaAreaScopeContext;
        }

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        $base = [
            'restricted' => false,
            'user' => $user,
            'area' => null,
            'label' => null,
            'allowed_ids' => collect(),
            'areas' => collect(),
        ];

        if (
            ! $user instanceof User
            || $user->roleCapabilitySlug() === 'owner'
            || ! Schema::connection('tenant')->hasTable('service_areas')
            || ! Schema::connection('tenant')->hasTable('users')
            || ! Schema::connection('tenant')->hasColumn('users', 'service_area_id')
            || blank($user->service_area_id ?? null)
        ) {
            return $this->tirtaAreaScopeContext = $base;
        }

        $areas = ServiceArea::query()
            ->select(['id', 'name', 'parent_id', 'area_type', 'is_active'])
            ->get();

        if ($areas->isEmpty()) {
            return $this->tirtaAreaScopeContext = $base;
        }

        $indexed = $areas->keyBy(fn (ServiceArea $serviceArea): string => (string) $serviceArea->getKey());

        if (! $indexed->has((string) $user->service_area_id)) {
            return $this->tirtaAreaScopeContext = $base;
        }

        /** @var ServiceArea $area */
        $area = $indexed->get((string) $user->service_area_id);
        $childrenByParent = $areas->groupBy(fn (ServiceArea $serviceArea): string => (string) ($serviceArea->parent_id ?? '__root__'));
        $allowedIds = $this->tirtaAreaDescendantIds((string) $area->getKey(), $childrenByParent);

        return $this->tirtaAreaScopeContext = [
            'restricted' => true,
            'user' => $user,
            'area' => $area,
            'label' => $this->tirtaAreaHierarchyLabel($area, $indexed),
            'allowed_ids' => $allowedIds,
            'areas' => $areas,
        ];
    }

    protected function tirtaAreaIsRestricted(): bool
    {
        return (bool) ($this->tirtaAreaScopeContext()['restricted'] ?? false);
    }

    protected function tirtaAreaScopeLabel(): ?string
    {
        return $this->tirtaAreaScopeContext()['label'] ?? null;
    }

    protected function tirtaAllowedAreaIds(): Collection
    {
        return $this->tirtaAreaScopeContext()['allowed_ids'] ?? collect();
    }

    protected function applyTirtaAreaScope(Builder $query, string $column = 'service_area_id'): Builder
    {
        if (! $this->tirtaAreaIsRestricted()) {
            return $query;
        }

        return $query->whereIn($column, $this->tirtaAllowedAreaIds()->all());
    }

    protected function applyTirtaConnectionScope(Builder $query): Builder
    {
        if (! $this->tirtaAreaIsRestricted()) {
            return $query;
        }

        $allowedIds = $this->tirtaAllowedAreaIds()->all();
        $model = $query->getModel();
        $table = $model->getTable();

        return $query->where(function (Builder $builder) use ($allowedIds, $table): void {
            $builder->whereIn($table . '.service_area_id', $allowedIds)
                ->orWhere(function (Builder $nested) use ($allowedIds, $table): void {
                    $nested->whereNull($table . '.service_area_id')
                        ->whereHas('customer', fn (Builder $customerQuery) => $customerQuery->whereIn('service_area_id', $allowedIds));
                });
        });
    }

    protected function applyTirtaInvoiceScope(Builder $query): Builder
    {
        if (! $this->tirtaAreaIsRestricted()) {
            return $query;
        }

        return $query->whereHas('connection', fn (Builder $connectionQuery) => $this->applyTirtaConnectionScope($connectionQuery));
    }

    protected function constrainTirtaAreaCollection(Collection $areas): Collection
    {
        if (! $this->tirtaAreaIsRestricted()) {
            return $areas;
        }

        $allowedIds = $this->tirtaAllowedAreaIds();

        return $areas
            ->filter(fn ($area): bool => $area instanceof ServiceArea && $allowedIds->contains((string) $area->getKey()))
            ->values();
    }

    protected function ensureTirtaAreaAccessible(?string $serviceAreaId, string $field = 'service_area_id', ?string $message = null): void
    {
        if (! $this->tirtaAreaIsRestricted()) {
            return;
        }

        if (blank($serviceAreaId) || ! $this->tirtaAllowedAreaIds()->contains((string) $serviceAreaId)) {
            throw ValidationException::withMessages([
                $field => $message ?? sprintf('Akses Anda dibatasi ke area %s dan turunannya.', $this->tirtaAreaScopeLabel() ?? 'tertentu'),
            ]);
        }
    }

    protected function abortIfOutsideTirtaArea(?string $serviceAreaId, string $message = 'Data ini berada di luar area kerja Anda.'): void
    {
        if (! $this->tirtaAreaIsRestricted()) {
            return;
        }

        if (blank($serviceAreaId) || ! $this->tirtaAllowedAreaIds()->contains((string) $serviceAreaId)) {
            abort(403, $message);
        }
    }

    protected function tirtaConnectionAreaId(?ServiceConnection $connection): ?string
    {
        if (! $connection instanceof ServiceConnection) {
            return null;
        }

        return $connection->service_area_id ?: $connection->customer?->service_area_id;
    }

    protected function tirtaCustomerAreaId(?Customer $customer): ?string
    {
        return $customer instanceof Customer ? $customer->service_area_id : null;
    }

    protected function tirtaAreaOptions(Collection $serviceAreas): Collection
    {
        $indexed = $serviceAreas->keyBy(fn (ServiceArea $serviceArea): string => (string) $serviceArea->getKey());

        return $serviceAreas->mapWithKeys(function (ServiceArea $serviceArea) use ($indexed): array {
            return [
                (string) $serviceArea->getKey() => $this->tirtaAreaHierarchyLabel($serviceArea, $indexed),
            ];
        });
    }

    protected function tirtaAreaHierarchyLabel(ServiceArea $serviceArea, Collection $indexed): string
    {
        $segments = [$serviceArea->name];
        $parentId = $serviceArea->parent_id;
        $visited = [(string) $serviceArea->getKey()];

        while ($parentId !== null && $indexed->has((string) $parentId)) {
            /** @var ServiceArea $parent */
            $parent = $indexed->get((string) $parentId);
            $parentKey = (string) $parent->getKey();

            if (in_array($parentKey, $visited, true)) {
                break;
            }

            array_unshift($segments, $parent->name);
            $visited[] = $parentKey;
            $parentId = $parent->parent_id;
        }

        return implode(' / ', $segments);
    }

    protected function tirtaAreaDescendantIds(string $areaId, Collection $childrenByParent): Collection
    {
        $ids = collect([$areaId]);
        /** @var Collection<int, ServiceArea> $children */
        $children = $childrenByParent->get($areaId, collect());

        foreach ($children as $child) {
            $ids = $ids->merge($this->tirtaAreaDescendantIds((string) $child->getKey(), $childrenByParent));
        }

        return $ids->unique()->values();
    }
}
