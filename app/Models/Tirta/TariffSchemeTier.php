<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TariffSchemeTier extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'tariff_scheme_id',
        'start_usage',
        'end_usage',
        'charge_type',
        'price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'start_usage' => 'integer',
            'end_usage' => 'integer',
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function tariffScheme(): BelongsTo
    {
        return $this->belongsTo(TariffScheme::class);
    }
}
