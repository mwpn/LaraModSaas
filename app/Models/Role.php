<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'capability_slug'])]
class Role extends Model
{
    use HasTenantUuid {
        HasTenantUuid::initializeHasTenantUuid as private initializeTenantUuid;
    }

    public function initializeHasTenantUuid(): void
    {
        $this->initializeTenantUuid();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
