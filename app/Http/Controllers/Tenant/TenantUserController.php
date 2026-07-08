<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\Concerns\InteractsWithTirtaAreaScope;
use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\Tirta\ServiceArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class TenantUserController extends Controller
{
    use InteractsWithTirtaAreaScope;

    public function index(): View
    {
        $manager = $this->manager();
        $schemaReady = $this->schemaReady();
        $serviceAreaSchemaReady = $this->serviceAreaSchemaReady();
        $jobTitleSchemaReady = $this->jobTitleSchemaReady();
        $jobTitleCatalogEnabled = $this->jobTitleCatalogEnabled();
        $jobTitleCatalogOptions = $jobTitleCatalogEnabled ? $this->jobTitleCatalogOptions() : collect();

        if ($schemaReady) {
            $this->ensureRoleRecords();
        }

        $roles = $schemaReady ? $this->roles() : new EloquentCollection();
        $manageableRoles = $schemaReady ? $this->manageableRoles($manager) : new EloquentCollection();
        $serviceAreas = $serviceAreaSchemaReady ? $this->serviceAreas() : new EloquentCollection();
        $serviceAreaOptions = $serviceAreaSchemaReady ? $this->serviceAreaOptions($serviceAreas) : collect();
        $users = $schemaReady
            ? User::query()
                ->with(['role', 'serviceArea.parent'])
                ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereIn('service_area_id', $this->tirtaAllowedAreaIds()->all()))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
            : new EloquentCollection();

        return view('basefeature::users', [
            'setting' => $this->tenantSetting(),
            'manager' => $manager,
            'roles' => $roles,
            'manageableRoles' => $manageableRoles,
            'serviceAreas' => $serviceAreas,
            'serviceAreaOptions' => $serviceAreaOptions,
            'users' => $users,
            'schemaReady' => $schemaReady,
            'serviceAreaSchemaReady' => $serviceAreaSchemaReady,
            'jobTitleSchemaReady' => $jobTitleSchemaReady,
            'jobTitleCatalogEnabled' => $jobTitleCatalogEnabled,
            'jobTitleCatalogOptions' => $jobTitleCatalogOptions,
            'areaScopeLabel' => $this->tirtaAreaScopeLabel(),
            'generatedPassword' => session('generated_password'),
            'stats' => [
                'total' => $users->count(),
                'active' => $users->where('is_active', true)->count(),
                'inactive' => $users->where('is_active', false)->count(),
                'owners' => $users->filter(fn (User $user): bool => $user->roleCapabilitySlug() === 'owner')->count(),
                'area_assigned' => $users->whereNotNull('service_area_id')->count(),
                'job_filled' => $jobTitleSchemaReady ? $users->filter(fn (User $user): bool => filled($user->job_title))->count() : 0,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = $this->manager();
        $this->ensureSchemaReadyForWrite();

        $roles = $this->manageableRoles($manager);
        $validated = $this->validatedUserPayload($request, $roles);

        $role = $roles->firstWhere('id', $validated['role_id']);
        $password = Str::password(12);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => $password,
            'role_id' => $validated['role_id'],
            'service_area_id' => $validated['service_area_id'],
            'is_active' => (bool) $request->boolean('is_active', true),
        ]);
        if ($this->jobTitleSchemaReady()) {
            $user->forceFill([
                'job_title' => $validated['job_title'],
            ])->save();
        }

        return redirect()
            ->route('tenant.users.index')
            ->with('status', 'Pengguna tenant berhasil ditambahkan.')
            ->with('generated_password', [
                'action' => 'created',
                'user_name' => $user->name,
                'user_email' => $user->email,
                'role_name' => $role?->name ?? 'User',
                'password' => $password,
            ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $manager = $this->manager();
        $this->ensureSchemaReadyForWrite();

        $user = $this->findUser($id);
        $roles = $this->manageableRoles($manager, $user);
        $validated = $this->validatedUserPayload($request, $roles, $user);

        $role = $roles->firstWhere('id', $validated['role_id']);
        $nextRoleSlug = $role?->slug;
        $nextIsActive = (bool) $validated['is_active'];

        $this->guardCriticalUserChange($user, $nextRoleSlug, $nextIsActive, $manager);

        $user->fill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'role_id' => $validated['role_id'],
            'service_area_id' => $validated['service_area_id'],
            'is_active' => $nextIsActive,
        ]);

        if ($this->jobTitleSchemaReady()) {
            $user->job_title = $validated['job_title'];
        }

        $user->save();

        return redirect()
            ->route('tenant.users.index')
            ->with('status', 'Data pengguna berhasil diperbarui.');
    }

    public function toggleActive(string $id): RedirectResponse
    {
        $manager = $this->manager();
        $this->ensureSchemaReadyForWrite();

        $user = $this->findUser($id);
        $this->ensureManageableTarget($user, $manager);

        $nextIsActive = ! $user->isActiveUser();

        $this->guardCriticalUserChange($user, $user->roleSlug(), $nextIsActive, $manager);

        $user->forceFill([
            'is_active' => $nextIsActive,
        ])->save();

        return redirect()
            ->route('tenant.users.index')
            ->with('status', $nextIsActive ? 'Pengguna berhasil diaktifkan.' : 'Pengguna berhasil dinonaktifkan.');
    }

    public function resetPassword(string $id): RedirectResponse
    {
        $manager = $this->manager();
        $user = $this->findUser($id);

        $this->ensureManageableTarget($user, $manager);

        $password = Str::password(12);

        $user->forceFill([
            'password' => $password,
        ])->save();

        return redirect()
            ->route('tenant.users.index')
            ->with('status', 'Password pengguna berhasil direset.')
            ->with('generated_password', [
                'action' => 'reset',
                'user_name' => $user->name,
                'user_email' => $user->email,
                'role_name' => $user->role?->name ?? 'User',
                'password' => $password,
            ]);
    }

    protected function manager(): User
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->canManageUsers(), 403, 'Hanya owner atau admin aktif yang bisa mengelola pengguna.');

        return $user->loadMissing(['role', 'serviceArea.parent']);
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    protected function roles(): EloquentCollection
    {
        return Role::query()
            ->orderByRaw("case slug when 'owner' then 1 when 'admin' then 2 when 'staff' then 3 when 'meter_reader' then 4 else 99 end")
            ->orderBy('name')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    protected function manageableRoles(User $manager, ?User $target = null): EloquentCollection
    {
        $roles = $this->roles();

        if ($manager->roleSlug() === 'owner') {
            return $roles;
        }

        $targetRoleSlug = $target?->roleCapabilitySlug();

        if ($targetRoleSlug === 'owner') {
            abort(403, 'Admin tenant tidak bisa mengubah akun owner.');
        }

        return $roles->filter(function (Role $role): bool {
            $capability = filled($role->capability_slug ?? null)
                ? (string) $role->capability_slug
                : (string) $role->slug;

            return $capability !== 'owner';
        })->values();
    }

    protected function tenantSetting(): TenantSetting
    {
        return TenantSetting::query()->firstOrCreate(
            [],
            [
                'brand_name' => (string) (tenant('name') ?? tenant('id') ?? config('app.name')),
                'description' => 'Landing page tenant belum dikustomisasi.',
                'theme_color' => '#000000',
            ]
        );
    }

    protected function findUser(string $id): User
    {
        return User::query()
            ->with(['role', 'serviceArea.parent'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereIn('service_area_id', $this->tirtaAllowedAreaIds()->all()))
            ->findOrFail($id);
    }

    protected function ensureManageableTarget(User $user, User $manager): void
    {
        if ($manager->roleSlug() !== 'owner' && $user->roleCapabilitySlug() === 'owner') {
            abort(403, 'Admin tenant tidak bisa mengelola akun owner.');
        }
    }

    protected function guardCriticalUserChange(User $user, ?string $nextRoleSlug, bool $nextIsActive, User $currentUser): void
    {
        $currentRoleSlug = $user->roleCapabilitySlug();

        $this->ensureManageableTarget($user, $currentUser);

        if ($user->is($currentUser) && ! $nextIsActive) {
            throw ValidationException::withMessages([
                'status' => 'Akun yang sedang dipakai tidak bisa dinonaktifkan.',
            ]);
        }

        if ($user->is($currentUser) && $currentRoleSlug !== $nextRoleSlug) {
            throw ValidationException::withMessages([
                'role_id' => 'Role akun yang sedang dipakai tidak bisa diubah dari halaman ini.',
            ]);
        }

        if ($currentRoleSlug !== 'owner') {
            return;
        }

        if ($nextRoleSlug === 'owner' && $nextIsActive) {
            return;
        }

        $activeOwnerIds = Role::query()
            ->where(function ($query): void {
                $query->where('slug', 'owner');

                if (Schema::hasColumn('roles', 'capability_slug')) {
                    $query->orWhere('capability_slug', 'owner');
                }
            })
            ->pluck('id');

        $activeOwnersCount = User::query()
            ->whereIn('role_id', $activeOwnerIds)
            ->where('is_active', true)
            ->count();

        if ($activeOwnersCount <= 1) {
            throw ValidationException::withMessages([
                'role_id' => 'Tenant harus punya minimal satu owner aktif.',
            ]);
        }
    }

    protected function ensureRoleRecords(): void
    {
        foreach ([
            ['name' => 'Owner', 'slug' => 'owner'],
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Staff', 'slug' => 'staff'],
            ['name' => 'Petugas Catat Meter', 'slug' => 'meter_reader'],
        ] as $role) {
            $record = Role::query()->firstOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']]
            );

            if (Schema::hasColumn('roles', 'capability_slug') && blank($record->capability_slug ?? null)) {
                $record->forceFill([
                    'capability_slug' => $role['slug'],
                ])->save();
            }
        }
    }

    /**
     * @return EloquentCollection<int, ServiceArea>
     */
    protected function serviceAreas(): EloquentCollection
    {
        return ServiceArea::query()
            ->with('parent')
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereIn('id', $this->tirtaAllowedAreaIds()->all()))
            ->orderByRaw("case area_type when 'branch' then 1 when 'unit' then 2 when 'rayon' then 3 else 4 end")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function validatedUserPayload(Request $request, EloquentCollection $roles, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->getKey(), $user?->getKeyName() ?? 'id')],
            'role_id' => ['required', Rule::in($roles->pluck('id')->all())],
            'is_active' => [$user instanceof User ? 'required' : 'nullable', 'boolean'],
        ];

        if ($this->jobTitleSchemaReady()) {
            $rules['job_title'] = ['nullable', 'string', 'max:120'];

            if ($this->jobTitleCatalogEnabled()) {
                $options = $this->jobTitleCatalogOptions()->all();

                if ($options !== []) {
                    $rules['job_title'][] = Rule::in($options);
                }
            }
        }

        if ($this->serviceAreaSchemaReady()) {
            $rules['service_area_id'] = [
                $this->tirtaAreaIsRestricted() ? 'required' : 'nullable',
                'string',
                Rule::exists('service_areas', 'id'),
            ];
        }

        $validated = $request->validate($rules);
        $validated['job_title'] = $this->jobTitleSchemaReady() && filled($validated['job_title'] ?? null)
            ? trim((string) $validated['job_title'])
            : null;
        $validated['service_area_id'] = $this->serviceAreaSchemaReady() && filled($validated['service_area_id'] ?? null)
            ? (string) $validated['service_area_id']
            : null;

        if ($validated['service_area_id'] !== null) {
            $serviceArea = ServiceArea::query()->findOrFail($validated['service_area_id']);

            if (! (bool) $serviceArea->is_active) {
                throw ValidationException::withMessages([
                    'service_area_id' => 'Area kerja user harus mengarah ke area yang aktif.',
                ]);
            }

            $this->ensureTirtaAreaAccessible(
                (string) $serviceArea->getKey(),
                'service_area_id',
                'User hanya bisa dipasang ke area yang termasuk cakupan kerja Anda.'
            );
        } elseif ($this->tirtaAreaIsRestricted()) {
            throw ValidationException::withMessages([
                'service_area_id' => sprintf('User baru harus dipasang ke area %s atau turunannya.', $this->tirtaAreaScopeLabel() ?? 'kerja Anda'),
            ]);
        }

        return $validated;
    }

    protected function schemaReady(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasTable('roles')
            && Schema::hasColumn('users', 'role_id')
            && Schema::hasColumn('users', 'is_active');
    }

    protected function ensureSchemaReadyForWrite(): void
    {
        if ($this->schemaReady()) {
            return;
        }

        throw ValidationException::withMessages([
            'users' => 'Fondasi role/status user tenant belum siap. Jalankan migrasi tenant terbaru dulu.',
        ]);
    }

    protected function serviceAreaSchemaReady(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasTable('service_areas')
            && Schema::hasColumn('users', 'service_area_id');
    }

    protected function jobTitleSchemaReady(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasColumn('users', 'job_title');
    }

    protected function userMetaSettingsSchemaReady(): bool
    {
        return Schema::hasTable('tenant_settings')
            && Schema::hasColumn('tenant_settings', 'use_job_title_master');
    }

    protected function jobTitleCatalogEnabled(): bool
    {
        if (! $this->userMetaSettingsSchemaReady() || ! Schema::hasTable('job_titles')) {
            return false;
        }

        return (bool) ($this->tenantSetting()->getAttribute('use_job_title_master') ?? false);
    }

    protected function jobTitleCatalogOptions(): Collection
    {
        if (! Schema::hasTable('job_titles')) {
            return collect();
        }

        return JobTitle::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->values();
    }

    protected function serviceAreaOptions(EloquentCollection $serviceAreas): Collection
    {
        $indexed = $serviceAreas->keyBy(fn (ServiceArea $serviceArea): string => (string) $serviceArea->getKey());

        return $serviceAreas->mapWithKeys(function (ServiceArea $serviceArea) use ($indexed): array {
            return [
                (string) $serviceArea->getKey() => $this->serviceAreaHierarchyLabel($serviceArea, $indexed),
            ];
        });
    }

    protected function serviceAreaHierarchyLabel(ServiceArea $serviceArea, Collection $indexed): string
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
}
