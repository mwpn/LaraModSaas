<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRequestLine extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'inventory_request_id',
        'inventory_item_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_completed',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_approved' => 'integer',
            'quantity_completed' => 'integer',
            'meta' => 'array',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(InventoryRequest::class, 'inventory_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

