<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 * @phpstan-require-extends Model
 * @psalm-require-extends Model
 * @property bool $incrementing
 * @property string $keyType
 * @method static void creating(callable(Model): void $callback)
 */
trait HasTenantUuid
{
    protected static function bootHasTenantUuid(): void
    {
        static::creating(function (Model $model): void {
            $keyName = $model->getKeyName();

            if (! $model->getAttribute($keyName)) {
                $model->setAttribute($keyName, Str::uuid()->toString());
            }
        });
    }

    public function initializeHasTenantUuid(): void
    {
        /** @var Model $this */
        $this->incrementing = false;

        /** @var Model $this */
        $this->keyType = 'string';
    }
}
