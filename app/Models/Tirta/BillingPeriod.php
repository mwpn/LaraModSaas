<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPeriod extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'meter_reading_period_id',
        'name',
        'period_start',
        'period_end',
        'due_date',
        'status',
        'notes',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function meterReadingPeriod(): BelongsTo
    {
        return $this->belongsTo(MeterReadingPeriod::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class)->orderBy('invoice_number');
    }
}
