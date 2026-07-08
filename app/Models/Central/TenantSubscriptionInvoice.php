<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscriptionInvoice extends CentralModel
{
    protected $table = 'tenant_subscription_invoices';

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'period_key',
        'period_label',
        'package_id',
        'package_code_snapshot',
        'status',
        'currency',
        'setup_fee_total',
        'monthly_total',
        'invoice_total',
        'issued_at',
        'due_at',
        'paid_at',
        'payment_meta',
        'usage_snapshot',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'payment_meta' => 'array',
            'usage_snapshot' => 'array',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TenantSubscriptionInvoiceLine::class, 'invoice_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
