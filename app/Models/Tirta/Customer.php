<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'service_area_id',
        'name',
        'address',
        'phone',
        'email',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(ServiceConnection::class);
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class)->orderByDesc('issued_at')->orderByDesc('created_at');
    }
}
