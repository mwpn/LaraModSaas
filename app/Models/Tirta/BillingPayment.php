<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'billing_invoice_id',
        'payment_method',
        'amount',
        'paid_at',
        'reference_number',
        'received_by',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class, 'billing_invoice_id');
    }
}
