<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'sku',
        'name',
        'category',
        'unit',
        'minimum_stock',
        'is_serialized',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock' => 'integer',
            'is_serialized' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function requestLines(): HasMany
    {
        return $this->hasMany(InventoryRequestLine::class, 'inventory_item_id');
    }
}
