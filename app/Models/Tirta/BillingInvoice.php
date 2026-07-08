<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingInvoice extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'billing_period_id',
        'meter_reading_id',
        'customer_id',
        'service_connection_id',
        'tariff_scheme_id',
        'invoice_number',
        'status',
        'usage_volume',
        'water_charge_total',
        'minimum_charge_applied',
        'admin_fee_total',
        'penalty_total',
        'invoice_total',
        'due_date',
        'issued_at',
        'paid_at',
        'calculation_snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'usage_volume' => 'integer',
            'water_charge_total' => 'integer',
            'minimum_charge_applied' => 'integer',
            'admin_fee_total' => 'integer',
            'penalty_total' => 'integer',
            'invoice_total' => 'integer',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'calculation_snapshot' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class, 'billing_period_id');
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ServiceConnection::class, 'service_connection_id');
    }

    public function tariffScheme(): BelongsTo
    {
        return $this->belongsTo(TariffScheme::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingInvoiceLine::class)->orderBy('sort_order')->orderBy('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPayment::class)->orderByDesc('paid_at')->orderByDesc('created_at');
    }
}
