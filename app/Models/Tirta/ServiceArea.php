<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceArea extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'name',
        'code',
        'area_type',
        'parent_id',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(ServiceConnection::class);
    }

    public function meterReaderAssignment(): HasOne
    {
        return $this->hasOne(MeterReaderAssignment::class);
    }

    public function areaTypeLabel(): string
    {
        return match ((string) ($this->area_type ?? 'rayon')) {
            'branch' => 'Cabang',
            'unit' => 'Unit',
            'rayon' => 'Rayon',
            default => 'Area',
        };
    }
}
