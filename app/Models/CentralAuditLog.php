<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_id',
    'actor_email',
    'level',
    'event_key',
    'target_type',
    'target_id',
    'summary',
    'meta',
])]
class CentralAuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
