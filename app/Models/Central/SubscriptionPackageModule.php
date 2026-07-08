<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPackageModule extends CentralModel
{
    protected $table = 'subscription_package_modules';

    protected $fillable = [
        'package_id',
        'module_id',
        'access_mode',
        'is_enabled_by_default',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled_by_default' => 'boolean',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(PlatformModule::class, 'module_id');
    }
}
