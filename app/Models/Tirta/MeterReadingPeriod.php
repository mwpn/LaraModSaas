<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterReadingPeriod extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'name',
        'period_start',
        'period_end',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class)->orderByDesc('recorded_at')->orderBy('created_at');
    }

    public function billingPeriod(): HasOne
    {
        return $this->hasOne(BillingPeriod::class);
    }
}
