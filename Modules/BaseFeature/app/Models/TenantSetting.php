<?php

declare(strict_types=1);

namespace Modules\BaseFeature\Models;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'brand_name',
        'description',
        'theme_color',
    ];
}
