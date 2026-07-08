<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TariffScheme extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'service_category_id',
        'name',
        'calculation_mode',
        'base_price_per_m3',
        'minimum_charge',
        'admin_fee',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'base_price_per_m3' => 'decimal:2',
            'minimum_charge' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(TariffSchemeTier::class)->orderBy('sort_order')->orderBy('start_usage');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(ServiceConnection::class);
    }
}
