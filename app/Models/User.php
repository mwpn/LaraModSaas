<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasTenantUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role_id', 'is_active', 'phone_number', 'avatar_path'])]
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

    public function roleSlug(): ?string
    {
        $slug = $this->relationLoaded('role')
            ? $this->role?->slug
            : $this->role()->value('slug');

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function isActiveUser(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function canManageUsers(): bool
    {
        return $this->isActiveUser() && in_array($this->roleSlug(), ['owner', 'admin'], true);
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
}
