<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function connections(): HasMany
    {
        return $this->hasMany(ServiceConnection::class);
    }

    public function tariffSchemes(): HasMany
    {
        return $this->hasMany(TariffScheme::class);
    }
}
