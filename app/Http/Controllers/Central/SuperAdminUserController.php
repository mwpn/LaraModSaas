<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SuperAdminUserController extends Controller
{
    public function index(): View
    {
        $manager = $this->manager();
        $platformType = CentralSetting::platformSaasType();
        $schemaReady = $this->schemaReady();

        if ($schemaReady) {
            $this->ensureRoleRecords();
        }

        $roles = $schemaReady ? $this->roles() : new EloquentCollection();
        $users = $schemaReady
            ? User::query()->with('role')->orderByDesc('is_active')->orderBy('name')->get()
            : new EloquentCollection();

        return view('central.users', [
            'platformType' => $platformType,
            'centralAccent' => CentralSetting::platformBlueprint($platformType)['theme_color'],
            'manager' => $manager,
            'roles' => $roles,
            'users' => $users,
            'schemaReady' => $schemaReady,
            'generatedPassword' => session('generated_password'),
            'stats' => [
                'total' => $users->count(),
                'active' => $users->where('is_active', true)->count(),
                'inactive' => $users->where('is_active', false)->count(),
                'owners' => $users->filter(fn (User $user): bool => $user->roleSlug() === 'owner')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = $this->manager();
        $this->ensureSchemaReadyForWrite();

        $roles = $this->manageableRoles($manager);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => ['required', Rule::in($roles->pluck('id')->all())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = $roles->firstWhere('id', $validated['role_id']);
        $password = Str::password(12);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => $password,
            'role_id' => $validated['role_id'],
            'is_active' => (bool) $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('central.super-admin.users.index')
            ->with('status', 'Pengguna superadmin berhasil ditambahkan.')
            ->with('generated_password', [
                'action' => 'created',
                'user_name' => $user->name,
                'user_email' => $user->email,
                'role_name' => $role?->name ?? 'User',
                'password' => $password,
            ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $manager = $this->manager();
        $this->ensureSchemaReadyForWrite();

        $user = $this->findUser($id);
        $roles = $this->manageableRoles($manager, $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey(), $user->getKeyName())],
            'role_id' => ['required', Rule::in($roles->pluck('id')->all())],
            'is_active' => ['required', 'boolean'],
        ]);

        $role = $roles->firstWhere('id', $validated['role_id']);
        $nextRoleSlug = $role?->slug;
        $nextIsActive = (bool) $validated['is_active'];

        $this->guardCriticalUserChange($user, $nextRoleSlug, $nextIsActive, $manager);

        $user->fill([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'role_id' => $validated['role_id'],
            'is_active' => $nextIsActive,
        ])->save();

        return redirect()
            ->route('central.super-admin.users.index')
            ->with('status', 'Data pengguna superadmin berhasil diperbarui.');
    }

    public function toggleActive(int $id): RedirectResponse
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
            ->route('central.super-admin.users.index')
            ->with('status', $nextIsActive ? 'Akun superadmin berhasil diaktifkan.' : 'Akun superadmin berhasil dinonaktifkan.');
    }

    public function resetPassword(int $id): RedirectResponse
    {
        $manager = $this->manager();
        $user = $this->findUser($id);

        $this->ensureManageableTarget($user, $manager);

        $password = Str::password(12);

        $user->forceFill([
            'password' => $password,
        ])->save();

        return redirect()
            ->route('central.super-admin.users.index')
            ->with('status', 'Password superadmin berhasil direset.')
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
        $user = Auth::guard('central')->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->canAccessCentral('users.manage'), 403, 'Role ini tidak boleh mengelola user superadmin.');

        return $user->loadMissing('role');
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    protected function roles(): EloquentCollection
    {
        return Role::query()
            ->whereIn('slug', ['owner', 'admin', 'staff'])
            ->orderByRaw("case slug when 'owner' then 1 when 'admin' then 2 else 3 end")
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

        $targetRoleSlug = $target?->roleSlug();

        if ($targetRoleSlug === 'owner') {
            abort(403, 'Admin tidak bisa mengubah akun owner pusat.');
        }

        return $roles->filter(fn (Role $role): bool => $role->slug !== 'owner')->values();
    }

    protected function findUser(int $id): User
    {
        return User::query()->with('role')->findOrFail($id);
    }

    protected function ensureManageableTarget(User $user, User $manager): void
    {
        if ($manager->roleSlug() !== 'owner' && $user->roleSlug() === 'owner') {
            abort(403, 'Admin tidak bisa mengelola akun owner pusat.');
        }
    }

    protected function guardCriticalUserChange(User $user, ?string $nextRoleSlug, bool $nextIsActive, User $currentUser): void
    {
        $currentRoleSlug = $user->roleSlug();

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
            ->where('slug', 'owner')
            ->pluck('id');

        $activeOwnersCount = User::query()
            ->whereIn('role_id', $activeOwnerIds)
            ->where('is_active', true)
            ->count();

        if ($activeOwnersCount <= 1) {
            throw ValidationException::withMessages([
                'role_id' => 'Central panel harus punya minimal satu owner aktif.',
            ]);
        }
    }

    protected function ensureRoleRecords(): void
    {
        foreach ([
            ['name' => 'Owner', 'slug' => 'owner'],
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Staff', 'slug' => 'staff'],
        ] as $role) {
            Role::query()->firstOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']]
            );
        }
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
            'users' => 'Fondasi role/status user central belum siap. Jalankan migrasi central terbaru dulu.',
        ]);
    }
}
