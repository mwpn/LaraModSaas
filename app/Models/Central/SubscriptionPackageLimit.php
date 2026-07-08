<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPackageLimit extends CentralModel
{
    protected $table = 'subscription_package_limits';

    protected $fillable = [
        'package_id',
        'limit_code',
        'limit_value',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }
}
