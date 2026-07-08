<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Models\User;
use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryRequest extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'request_number',
        'request_type',
        'status',
        'service_area_id',
        'service_connection_id',
        'source_location_id',
        'destination_location_id',
        'supplier_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'completed_by_user_id',
        'title',
        'reference_number',
        'notes',
        'approval_notes',
        'completion_notes',
        'requested_at',
        'approved_at',
        'completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryRequestLine::class)->orderBy('created_at');
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function serviceConnection(): BelongsTo
    {
        return $this->belongsTo(ServiceConnection::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
