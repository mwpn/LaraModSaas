<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscriptionInvoiceLine extends CentralModel
{
    protected $table = 'tenant_subscription_invoice_lines';

    protected $fillable = [
        'invoice_id',
        'line_code',
        'label',
        'kind',
        'quantity',
        'amount',
        'rate',
        'line_total',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantSubscriptionInvoice::class, 'invoice_id');
    }
}
