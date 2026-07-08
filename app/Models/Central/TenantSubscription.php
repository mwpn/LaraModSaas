<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends CentralModel
{
    protected $table = 'tenant_subscriptions';

    protected $fillable = [
        'tenant_id',
        'platform_type',
        'package_id',
        'package_code_snapshot',
        'status',
        'starts_at',
        'expires_at',
        'grace_until',
        'assigned_at',
        'assigned_by',
        'billing_usage_snapshot',
        'billing_grace_days',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'grace_until' => 'datetime',
            'assigned_at' => 'datetime',
            'billing_usage_snapshot' => 'array',
            'meta' => 'array',
        ];
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
