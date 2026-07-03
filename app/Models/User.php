<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasTenantUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
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
            'password' => 'hashed',
        ];
    }
}
