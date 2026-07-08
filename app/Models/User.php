<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Tirta\ServiceArea;
use App\Traits\HasTenantUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Fillable(['name', 'job_title', 'email', 'password', 'role_id', 'service_area_id', 'is_active', 'phone_number', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    private const CENTRAL_PERMISSION_MATRIX = [
        'owner' => ['*'],
        'admin' => [
            'leads.manage',
            'tenants.manage',
            'billing.manage',
            'packages.manage',
            'users.manage',
            'settings.view',
        ],
        'staff' => [
            'leads.view',
            'tenants.view',
            'billing.view',
            'packages.view',
        ],
    ];

    private const TIRTA_ROLE_LABELS = [
        'owner' => 'Owner Tenant',
        'admin' => 'Admin Tenant',
        'staff' => 'Staff Operasional',
        'meter_reader' => 'Petugas Catat Meter',
        'keuangan' => 'Keuangan',
        'kasir' => 'Kasir Loket',
        'kolektor' => 'Kolektor',
        'gudang' => 'Gudang',
        'logistik' => 'Logistik',
    ];

    use HasTenantUuid {
        HasTenantUuid::initializeHasTenantUuid as private initializeTenantUuid;
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function bootHasTenantUuid(): void
    {
        static::creating(function (self $user): void {
            if (! tenancy()->initialized) {
                return;
            }

            $keyName = $user->getKeyName();

            if (! $user->getAttribute($keyName)) {
                $user->setAttribute($keyName, Str::uuid()->toString());
            }
        });
    }

    public function initializeHasTenantUuid(): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $this->initializeTenantUuid();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function roleSlug(): ?string
    {
        $slug = $this->relationLoaded('role')
            ? $this->role?->slug
            : $this->role()->value('slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function roleCapabilitySlug(): ?string
    {
        $capability = null;

        if ($this->relationLoaded('role')) {
            $capability = $this->role?->capability_slug ?? null;
        } else {
            $schema = tenancy()->initialized
                ? Schema::connection('tenant')
                : Schema::connection(config('database.default'));

            if ($schema->hasTable('roles') && $schema->hasColumn('roles', 'capability_slug')) {
                $capability = $this->role()->value('capability_slug');
            }
        }

        $capability = is_string($capability) && $capability !== '' ? $capability : null;

        return $capability ?? $this->roleSlug();
    }

    public function isActiveUser(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function canManageUsers(): bool
    {
        return $this->isActiveUser() && in_array($this->roleCapabilitySlug(), ['owner', 'admin'], true);
    }

    public function isMeterReader(): bool
    {
        return $this->isActiveUser() && $this->roleCapabilitySlug() === 'meter_reader';
    }

    public function tirtaRoleLabel(): string
    {
        $roleSlug = $this->roleSlug();

        if (is_string($roleSlug) && isset(self::TIRTA_ROLE_LABELS[$roleSlug])) {
            return self::TIRTA_ROLE_LABELS[$roleSlug];
        }

        return (string) ($this->role?->name ?? 'Pengguna Tenant');
    }

    public function canAccessTirtaBackoffice(): bool
    {
        return $this->canAccessTirtaMasterData()
            || $this->canAccessTirtaMeterReadingWorkspace()
            || $this->canAccessTirtaBilling()
            || $this->canAccessTirtaWarehouse();
    }

    public function canAccessTirtaMasterData(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff']);
    }

    public function canAccessTirtaMeterReadingWorkspace(): bool
    {
        return $this->canAccessTirtaMasterData() || $this->isMeterReader();
    }

    public function canManageTirtaMeterReadingConfig(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff']);
    }

    public function canBeAssignedTirtaMeterReader(): bool
    {
        return $this->isMeterReader()
            || $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff']);
    }

    public function canAccessTirtaBilling(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff', 'keuangan', 'kasir', 'kolektor']);
    }

    public function canManageTirtaBilling(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['keuangan']);
    }

    public function canRecordTirtaBillingPayment(): bool
    {
        return $this->canManageTirtaBilling()
            || $this->hasTirtaRoleSlug(['staff', 'kasir', 'kolektor']);
    }

    public function canAccessTirtaWarehouse(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff', 'gudang', 'logistik', 'keuangan']);
    }

    public function canCreateTirtaWarehouseRequest(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff', 'gudang', 'logistik']);
    }

    public function canManageTirtaWarehouseStock(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff', 'gudang', 'logistik']);
    }

    public function canManageTirtaWarehouseMaster(): bool
    {
        return $this->canManageTirtaWarehouseStock();
    }

    public function canManageTirtaWarehouseSuppliers(): bool
    {
        return $this->isTirtaOperatorCapability(['owner', 'admin'])
            || $this->hasTirtaRoleSlug(['staff', 'gudang', 'logistik']);
    }

    public function canApproveTirtaWarehouseRequest(?string $requestType = null): bool
    {
        if (! $this->isActiveUser()) {
            return false;
        }

        if ($this->roleCapabilitySlug() === 'owner') {
            return true;
        }

        if ($requestType === 'procurement') {
            return $this->roleSlug() === 'keuangan';
        }

        return $this->roleCapabilitySlug() === 'admin'
            || $this->hasTirtaRoleSlug(['staff', 'gudang', 'logistik']);
    }

    public function canCompleteTirtaWarehouseRequest(): bool
    {
        return $this->canManageTirtaWarehouseStock();
    }

    public function canApproveTirtaProcurementRequest(): bool
    {
        return $this->canApproveTirtaWarehouseRequest('procurement');
    }

    public function canAccessCentral(string $ability): bool
    {
        if (! $this->isActiveUser()) {
            return false;
        }

        $roleSlug = $this->roleSlug() ?? 'staff';
        $grants = self::CENTRAL_PERMISSION_MATRIX[$roleSlug] ?? [];

        if (in_array('*', $grants, true) || in_array($ability, $grants, true)) {
            return true;
        }

        if (str_ends_with($ability, '.view')) {
            $manageAbility = preg_replace('/\.view$/', '.manage', $ability);

            return is_string($manageAbility) && in_array($manageAbility, $grants, true);
        }

        return false;
    }

    public function profileInitials(): string
    {
        $source = trim((string) ($this->name ?: $this->email ?: 'Central Admin'));

        if ($source === '') {
            return 'CA';
        }

        $parts = preg_split('/[\s@._-]+/', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            return Str::upper(Str::substr($parts[0], 0, 1) . Str::substr($parts[1], 0, 1));
        }

        return Str::upper(Str::substr($source, 0, 2));
    }

    public function avatarUrl(): ?string
    {
        $path = trim((string) ($this->avatar_path ?? ''));

        if ($path === '') {
            return null;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    /**
     * @param  array<int, string>  $capabilities
     */
    protected function isTirtaOperatorCapability(array $capabilities): bool
    {
        return $this->isActiveUser() && in_array($this->roleCapabilitySlug(), $capabilities, true);
    }

    /**
     * @param  array<int, string>  $roleSlugs
     */
    protected function hasTirtaRoleSlug(array $roleSlugs): bool
    {
        return $this->isActiveUser() && in_array($this->roleSlug(), $roleSlugs, true);
    }
}
