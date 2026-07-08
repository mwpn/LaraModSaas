<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends CentralModel
{
    protected $table = 'subscription_packages';

    protected $fillable = [
        'package_code',
        'platform_type',
        'label',
        'description',
        'billing_cycle',
        'base_price',
        'currency',
        'is_enabled',
        'is_highlighted',
        'is_default',
        'sort_order',
        'grace_days',
        'billing_components',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_highlighted' => 'boolean',
            'is_default' => 'boolean',
            'billing_components' => 'array',
        ];
    }

    public function modules(): HasMany
    {
        return $this->hasMany(SubscriptionPackageModule::class, 'package_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionPackageFeature::class, 'package_id');
    }

    public function limits(): HasMany
    {
        return $this->hasMany(SubscriptionPackageLimit::class, 'package_id');
    }
}
