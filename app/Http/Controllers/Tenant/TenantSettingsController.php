<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class TenantSettingsController extends Controller
{
    public function edit(): View
    {
        $manager = $this->manager();

        $setting = $this->tenantSetting();
        $userMetaSettingsSchemaReady = $this->userMetaSettingsSchemaReady();
        $jobTitleCatalogSchemaReady = Schema::connection('tenant')->hasTable('job_titles');
        $roleCatalogSchemaReady = Schema::connection('tenant')->hasTable('roles');
        $roleCapabilitySchemaReady = $roleCatalogSchemaReady
            && Schema::connection('tenant')->hasColumn('roles', 'capability_slug');

        $jobTitles = $jobTitleCatalogSchemaReady
            ? JobTitle::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : new EloquentCollection();

        $roles = $roleCatalogSchemaReady
            ? Role::query()
                ->orderByRaw("case slug when 'owner' then 1 when 'admin' then 2 when 'staff' then 3 when 'meter_reader' then 4 else 99 end")
                ->orderBy('name')
                ->get()
            : new EloquentCollection();

        return view('basefeature::settings', [
            'setting' => $setting,
            'userMetaSettingsSchemaReady' => $userMetaSettingsSchemaReady,
            'jobTitleCatalogSchemaReady' => $jobTitleCatalogSchemaReady,
            'useJobTitleMaster' => $userMetaSettingsSchemaReady
                ? (bool) ($setting->getAttribute('use_job_title_master') ?? false)
                : false,
            'jobTitles' => $jobTitles,
            'roles' => $roles,
            'roleCatalogSchemaReady' => $roleCatalogSchemaReady,
            'roleCapabilitySchemaReady' => $roleCapabilitySchemaReady,
            'isOwnerManager' => $manager->roleCapabilitySlug() === 'owner',
            'capabilityOptions' => $this->capabilityOptions(),
            'roleLabelOverrides' => $userMetaSettingsSchemaReady
                ? $this->roleLabelOverrides($setting)
                : [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureCanManageSettings();

        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'theme_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $setting = $this->tenantSetting();
        $setting->fill($validated)->save();

        if ($this->userMetaSettingsSchemaReady()) {
            $request->validate([
                'use_job_title_master' => ['nullable', 'boolean'],
                'role_labels' => ['nullable', 'array'],
                'role_labels.*' => ['nullable', 'string', 'max:60'],
            ]);

            $roleLabelOverrides = $this->filteredRoleLabelOverrides(
                (array) $request->input('role_labels', [])
            );

            $setting->forceFill([
                'use_job_title_master' => (bool) $request->boolean('use_job_title_master', false),
                'role_label_overrides' => $roleLabelOverrides === []
                    ? null
                    : json_encode($roleLabelOverrides, JSON_UNESCAPED_UNICODE),
            ])->save();
        }

        return back()->with('status', 'Pengaturan web berhasil diperbarui.');
    }

    public function storeJobTitle(Request $request): RedirectResponse
    {
        $this->ensureCanManageSettings();

        if (! Schema::connection('tenant')->hasTable('job_titles')) {
            abort(422, 'Master jabatan belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('job_titles', 'name')],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        JobTitle::query()->create([
            'name' => trim((string) $validated['name']),
            'is_active' => (bool) $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        return back()->with('status', 'Jabatan baru berhasil ditambahkan.');
    }

    public function updateJobTitle(Request $request, string $id): RedirectResponse
    {
        $this->ensureCanManageSettings();

        if (! Schema::connection('tenant')->hasTable('job_titles')) {
            abort(422, 'Master jabatan belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        $jobTitle = JobTitle::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('job_titles', 'name')->ignore($jobTitle->getKey(), $jobTitle->getKeyName())],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $jobTitle->forceFill([
            'name' => trim((string) $validated['name']),
            'is_active' => (bool) $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ])->save();

        return back()->with('status', 'Jabatan berhasil diperbarui.');
    }

    public function destroyJobTitle(string $id): RedirectResponse
    {
        $this->ensureCanManageSettings();

        if (! Schema::connection('tenant')->hasTable('job_titles')) {
            abort(422, 'Master jabatan belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        JobTitle::query()->findOrFail($id)->delete();

        return back()->with('status', 'Jabatan berhasil dihapus.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $manager = $this->manager();

        if (! Schema::connection('tenant')->hasTable('roles')) {
            abort(422, 'Master role belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        if (! Schema::connection('tenant')->hasColumn('roles', 'capability_slug')) {
            abort(422, 'Kolom capability role belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]{1,31}$/', Rule::unique('roles', 'slug')],
            'capability_slug' => ['required', Rule::in(array_keys($this->capabilityOptions()))],
        ]);

        $capability = (string) $validated['capability_slug'];

        if (in_array($capability, ['owner', 'admin'], true) && $manager->roleCapabilitySlug() !== 'owner') {
            abort(403, 'Hanya owner yang boleh membuat role setara owner/admin.');
        }

        $slug = Str::lower(trim((string) $validated['slug']));

        if (in_array($slug, ['owner', 'admin', 'staff', 'meter_reader'], true)) {
            throw ValidationException::withMessages([
                'slug' => 'Slug ini adalah role sistem. Gunakan menu label role kalau mau ganti nama tampilan.',
            ]);
        }

        Role::query()->create([
            'name' => trim((string) $validated['name']),
            'slug' => $slug,
            'capability_slug' => $capability,
        ]);

        return back()->with('status', 'Role baru berhasil ditambahkan.');
    }

    public function updateRole(Request $request, string $id): RedirectResponse
    {
        $manager = $this->manager();

        if (! Schema::connection('tenant')->hasTable('roles')) {
            abort(422, 'Master role belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        if (! Schema::connection('tenant')->hasColumn('roles', 'capability_slug')) {
            abort(422, 'Kolom capability role belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        $role = Role::query()->findOrFail($id);

        if (in_array((string) $role->slug, ['owner', 'admin', 'staff', 'meter_reader'], true)) {
            abort(403, 'Role sistem tidak bisa diubah dari menu ini.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'capability_slug' => ['required', Rule::in(array_keys($this->capabilityOptions()))],
        ]);

        $capability = (string) $validated['capability_slug'];

        if (in_array($capability, ['owner', 'admin'], true) && $manager->roleCapabilitySlug() !== 'owner') {
            abort(403, 'Hanya owner yang boleh membuat role setara owner/admin.');
        }

        $role->forceFill([
            'name' => trim((string) $validated['name']),
            'capability_slug' => $capability,
        ])->save();

        return back()->with('status', 'Role berhasil diperbarui.');
    }

    public function destroyRole(string $id): RedirectResponse
    {
        $this->ensureCanManageSettings();

        if (! Schema::connection('tenant')->hasTable('roles')) {
            abort(422, 'Master role belum siap. Jalankan migrasi tenant terbaru dulu.');
        }

        $role = Role::query()->findOrFail($id);

        if (in_array((string) $role->slug, ['owner', 'admin', 'staff', 'meter_reader'], true)) {
            abort(403, 'Role sistem tidak bisa dihapus.');
        }

        if (Schema::connection('tenant')->hasTable('users') && Schema::connection('tenant')->hasColumn('users', 'role_id')) {
            $used = User::query()->where('role_id', $role->getKey())->count();

            if ($used > 0) {
                throw ValidationException::withMessages([
                    'role' => 'Role ini masih dipakai oleh user. Pindahkan dulu user ke role lain sebelum menghapus.',
                ]);
            }
        }

        $role->delete();

        return back()->with('status', 'Role berhasil dihapus.');
    }

    protected function ensureCanManageSettings(): void
    {
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User)) {
            abort(403, 'Anda tidak memiliki akses ke pengaturan tenant.');
        }

        if (! method_exists($user, 'canManageUsers') || ! $user->canManageUsers()) {
            abort(403, 'Anda tidak memiliki akses ke pengaturan tenant.');
        }
    }

    protected function manager(): User
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->canManageUsers(), 403, 'Anda tidak memiliki akses ke pengaturan tenant.');

        return $user->loadMissing('role');
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

    protected function userMetaSettingsSchemaReady(): bool
    {
        return Schema::connection('tenant')->hasTable('tenant_settings')
            && Schema::connection('tenant')->hasColumn('tenant_settings', 'use_job_title_master')
            && Schema::connection('tenant')->hasColumn('tenant_settings', 'role_label_overrides');
    }

    protected function roleLabelOverrides(TenantSetting $setting): array
    {
        $raw = $setting->getAttribute('role_label_overrides');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && filled($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function filteredRoleLabelOverrides(array $payload): array
    {
        $roles = Schema::connection('tenant')->hasTable('roles')
            ? Role::query()->pluck('slug')->map(fn ($slug) => (string) $slug)->all()
            : ['owner', 'admin', 'staff', 'meter_reader'];

        $allowed = array_fill_keys($roles, true);
        $filtered = [];

        foreach ($payload as $slug => $label) {
            $slug = (string) $slug;

            if (! isset($allowed[$slug])) {
                continue;
            }

            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            $filtered[$slug] = $label;
        }

        return $filtered;
    }

    protected function capabilityOptions(): array
    {
        return [
            'staff' => 'Staff Operasional',
            'meter_reader' => 'Petugas Catat Meter',
            'admin' => 'Admin Tenant',
            'owner' => 'Owner Tenant',
        ];
    }
}
