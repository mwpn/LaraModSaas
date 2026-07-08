<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceConnection extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'customer_id',
        'service_area_id',
        'service_category_id',
        'tariff_scheme_id',
        'service_number',
        'service_label',
        'meter_number',
        'service_address',
        'status',
        'installation_workflow_status',
        'installed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'date',
            'disconnected_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'reactivation_requested_at' => 'datetime',
            'reactivation_allow_installment' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function tariffScheme(): BelongsTo
    {
        return $this->belongsTo(TariffScheme::class);
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class)->orderByDesc('recorded_at')->orderByDesc('created_at');
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class)->orderByDesc('issued_at')->orderByDesc('created_at');
    }

    public function inventoryRequests(): HasMany
    {
        return $this->hasMany(InventoryRequest::class)->orderByDesc('created_at');
    }
}
