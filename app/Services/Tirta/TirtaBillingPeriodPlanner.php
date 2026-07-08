<?php

declare(strict_types=1);

namespace App\Services\Tirta;

use App\Models\Tirta\BillingPeriod;
use App\Models\Tirta\MeterReadingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\BaseFeature\Models\TenantSetting;

class TirtaBillingPeriodPlanner
{
    public function syncDraftPeriods(TenantSetting $setting): Collection
    {
        return MeterReadingPeriod::query()
            ->with('billingPeriod')
            ->where('status', 'closed')
            ->orderBy('period_start')
            ->get()
            ->filter(fn (MeterReadingPeriod $period): bool => ! $period->billingPeriod instanceof BillingPeriod)
            ->map(function (MeterReadingPeriod $period) use ($setting): BillingPeriod {
                return $this->createDraftPeriod($period, $setting);
            })
            ->values();
    }

    public function ensureDraftPeriod(MeterReadingPeriod $period, TenantSetting $setting): BillingPeriod
    {
        $period->loadMissing('billingPeriod');

        if ($period->billingPeriod instanceof BillingPeriod) {
            return $period->billingPeriod;
        }

        return $this->createDraftPeriod($period, $setting);
    }

    public function draftPayload(MeterReadingPeriod $period, TenantSetting $setting): array
    {
        $publishDate = $this->publishDate($period, $setting);
        $dueDate = $publishDate->copy()->addDays(
            max((int) ($setting->getAttribute('billing_due_offset_days') ?? 10), 1)
        );

        return [
            'meter_reading_period_id' => $period->id,
            'name' => $this->nameForPeriod($period),
            'period_start' => $period->period_start,
            'period_end' => $period->period_end,
            'due_date' => $dueDate->toDateString(),
            'status' => 'draft',
            'notes' => sprintf(
                'Draft otomatis dari periode baca meter %s. Terbit disarankan %s.',
                $period->name,
                $publishDate->format('d M Y')
            ),
            'generated_at' => null,
        ];
    }

    public function publishDate(MeterReadingPeriod $period, TenantSetting $setting): Carbon
    {
        $periodEnd = $period->period_end instanceof Carbon
            ? $period->period_end->copy()
            : Carbon::parse($period->period_end);

        return $periodEnd->startOfDay()->addDays(
            max((int) ($setting->getAttribute('billing_publish_offset_days') ?? 1), 0)
        );
    }

    protected function createDraftPeriod(MeterReadingPeriod $period, TenantSetting $setting): BillingPeriod
    {
        return BillingPeriod::query()->create($this->draftPayload($period, $setting));
    }

    protected function nameForPeriod(MeterReadingPeriod $period): string
    {
        $baseDate = $period->period_end ?? $period->period_start ?? now();

        return sprintf('Tagihan Air %s', $baseDate->format('F Y'));
    }
}
