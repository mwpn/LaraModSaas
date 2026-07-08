<?php

declare(strict_types=1);

namespace App\Models\Tirta;

use App\Traits\HasTenantUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeterReading extends Model
{
    use HasTenantUuid;

    protected $fillable = [
        'meter_reading_period_id',
        'service_connection_id',
        'previous_reading',
        'current_reading',
        'usage_volume',
        'reading_status',
        'visit_status',
        'follow_up_action',
        'review_status',
        'review_flags',
        'reader_name',
        'recorded_at',
        'evidence_photo_path',
        'recorded_latitude',
        'recorded_longitude',
        'recorded_accuracy_meters',
        'customer_notification_status',
        'customer_notification_channels',
        'customer_notification_message',
        'customer_notified_at',
        'anomaly_notes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'integer',
            'current_reading' => 'integer',
            'usage_volume' => 'integer',
            'recorded_at' => 'datetime',
            'recorded_latitude' => 'decimal:7',
            'recorded_longitude' => 'decimal:7',
            'recorded_accuracy_meters' => 'decimal:2',
            'review_flags' => 'array',
            'customer_notification_channels' => 'array',
            'customer_notified_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(MeterReadingPeriod::class, 'meter_reading_period_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ServiceConnection::class, 'service_connection_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(BillingInvoice::class);
    }
}
