<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    use HasTenantUuid;

    protected $table = 'job_titles';

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}

