<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'email',
    'phone_number',
    'platform_type',
    'status',
    'last_contacted_at',
    'converted_at',
    'converted_tenant_id',
    'ip_address',
    'user_agent',
])]
class DemoRequest extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_CONVERTED = 'converted';

    protected function casts(): array
    {
        return [
            'last_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public static function availableStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_CONTACTED,
            self::STATUS_QUALIFIED,
            self::STATUS_CONVERTED,
        ];
    }

    public function normalizedStatus(): string
    {
        $status = Str::lower(trim((string) $this->status));

        return in_array($status, self::availableStatuses(), true)
            ? $status
            : self::STATUS_NEW;
    }

    public function statusLabel(): string
    {
        return match ($this->normalizedStatus()) {
            self::STATUS_CONTACTED => 'Contacted',
            self::STATUS_QUALIFIED => 'Qualified',
            self::STATUS_CONVERTED => 'Converted',
            default => 'New',
        };
    }

    public function whatsappUrl(): string
    {
        $phone = preg_replace('/\D+/', '', (string) $this->phone_number) ?? '';
        $appName = app()->bound('config')
            ? (string) config('app.name', 'AirCloud')
            : 'AirCloud';

        if ($phone !== '' && str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $message = rawurlencode(sprintf(
            'Halo %s, kami dari tim %s ingin follow up request demo Anda untuk platform %s.',
            $this->name,
            $appName,
            ucfirst((string) $this->platform_type)
        ));

        return 'https://wa.me/' . $phone . '?text=' . $message;
    }

    public function isConverted(): bool
    {
        return $this->normalizedStatus() === self::STATUS_CONVERTED
            && filled($this->converted_tenant_id);
    }
}
