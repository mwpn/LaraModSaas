<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModuleState extends CentralModel
{
    protected $table = 'tenant_module_states';

    protected $fillable = [
        'tenant_id',
        'module_id',
        'status',
        'enabled_source',
        'reason_code',
        'is_allowed',
        'enabled_at',
        'disabled_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_allowed' => 'boolean',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(PlatformModule::class, 'module_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
