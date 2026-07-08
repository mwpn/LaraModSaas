<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformModule extends CentralModel
{
    protected $table = 'platform_module_catalog';

    protected $fillable = [
        'module_code',
        'module_name',
        'platform_type',
        'domain_group',
        'label',
        'description',
        'is_required',
        'is_default_enabled',
        'is_addon',
        'subscription_visible',
        'depends_on',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_default_enabled' => 'boolean',
            'is_addon' => 'boolean',
            'subscription_visible' => 'boolean',
            'depends_on' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function packageLinks(): HasMany
    {
        return $this->hasMany(SubscriptionPackageModule::class, 'module_id');
    }
}
