<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tirta\MeterReadingPeriod;
use App\Services\Tirta\TirtaBillingPeriodPlanner;
use Carbon\CarbonImmutable;
use Modules\BaseFeature\Models\TenantSetting;
use Tests\TestCase;

class TirtaBillingPeriodPlannerTest extends TestCase
{
    public function test_it_builds_draft_payload_from_meter_period_and_cycle_settings(): void
    {
        $planner = new TirtaBillingPeriodPlanner();
        $period = (new MeterReadingPeriod())->forceFill([
            'id' => 'period-juli',
            'name' => 'Baca Meter Juli 2026',
            'period_start' => CarbonImmutable::parse('2026-07-25'),
            'period_end' => CarbonImmutable::parse('2026-07-30'),
            'status' => 'closed',
        ]);
        $setting = (new TenantSetting())->forceFill([
            'billing_publish_offset_days' => 2,
            'billing_due_offset_days' => 8,
        ]);

        $payload = $planner->draftPayload($period, $setting);

        self::assertSame('period-juli', $payload['meter_reading_period_id']);
        self::assertSame('Tagihan Air July 2026', $payload['name']);
        self::assertSame('2026-08-09', $payload['due_date']);
        self::assertSame('draft', $payload['status']);
        self::assertStringContainsString('Baca Meter Juli 2026', (string) $payload['notes']);
    }

    public function test_it_calculates_publish_date_from_period_end_and_offset(): void
    {
        $planner = new TirtaBillingPeriodPlanner();
        $period = new MeterReadingPeriod([
            'period_end' => CarbonImmutable::parse('2026-07-30'),
        ]);
        $setting = (new TenantSetting())->forceFill([
            'billing_publish_offset_days' => 1,
        ]);

        $publishDate = $planner->publishDate($period, $setting);

        self::assertSame('2026-07-31', $publishDate->toDateString());
    }
}
