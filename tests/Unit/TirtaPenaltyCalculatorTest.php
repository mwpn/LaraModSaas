<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tirta\BillingInvoice;
use App\Services\Tirta\TirtaPenaltyCalculator;
use Carbon\CarbonImmutable;
use Modules\BaseFeature\Models\TenantSetting;
use Tests\TestCase;

class TirtaPenaltyCalculatorTest extends TestCase
{
    public function test_it_calculates_fixed_daily_penalty_after_grace_period(): void
    {
        $calculator = new TirtaPenaltyCalculator();
        $invoice = new BillingInvoice([
            'status' => 'issued',
            'invoice_total' => 100000,
            'due_date' => CarbonImmutable::parse('2026-07-01'),
        ]);
        $setting = (new TenantSetting())->forceFill([
            'billing_penalty_enabled' => true,
            'billing_penalty_type' => 'fixed',
            'billing_penalty_base' => 'outstanding_total',
            'billing_penalty_start_basis' => 'due_date',
            'billing_penalty_value' => 2000,
            'billing_penalty_grace_days' => 2,
            'billing_penalty_max_amount' => null,
        ]);

        $result = $calculator->calculate(
            $invoice,
            ['outstanding_total' => 50000],
            $setting,
            CarbonImmutable::parse('2026-07-06')
        );

        self::assertTrue($result['enabled']);
        self::assertEquals(5, $result['late_days']);
        self::assertEquals(3, $result['effective_late_days']);
        self::assertEquals(50000, $result['base_amount']);
        self::assertEquals(2000, $result['daily_penalty_amount']);
        self::assertEquals(6000, $result['penalty_amount']);
    }

    public function test_it_calculates_percentage_penalty_from_invoice_total_with_cap(): void
    {
        $calculator = new TirtaPenaltyCalculator();
        $invoice = new BillingInvoice([
            'status' => 'issued',
            'invoice_total' => 200000,
            'due_date' => CarbonImmutable::parse('2026-07-01'),
        ]);
        $setting = (new TenantSetting())->forceFill([
            'billing_penalty_enabled' => true,
            'billing_penalty_type' => 'percentage',
            'billing_penalty_base' => 'invoice_total',
            'billing_penalty_start_basis' => 'due_date',
            'billing_penalty_value' => 1.5,
            'billing_penalty_grace_days' => 0,
            'billing_penalty_max_amount' => 10000,
        ]);

        $result = $calculator->calculate(
            $invoice,
            ['outstanding_total' => 120000],
            $setting,
            CarbonImmutable::parse('2026-07-05')
        );

        self::assertTrue($result['enabled']);
        self::assertEquals(200000, $result['base_amount']);
        self::assertEquals(3000, $result['daily_penalty_amount']);
        self::assertEquals(10000, $result['penalty_amount']);
    }

    public function test_it_returns_zero_when_penalty_is_disabled_or_not_yet_late(): void
    {
        $calculator = new TirtaPenaltyCalculator();
        $invoice = new BillingInvoice([
            'status' => 'issued',
            'invoice_total' => 80000,
            'due_date' => CarbonImmutable::parse('2026-07-10'),
        ]);
        $setting = (new TenantSetting())->forceFill([
            'billing_penalty_enabled' => true,
            'billing_penalty_type' => 'fixed',
            'billing_penalty_base' => 'outstanding_total',
            'billing_penalty_start_basis' => 'due_date',
            'billing_penalty_value' => 1000,
            'billing_penalty_grace_days' => 0,
        ]);

        $result = $calculator->calculate(
            $invoice,
            ['outstanding_total' => 80000],
            $setting,
            CarbonImmutable::parse('2026-07-10')
        );

        self::assertFalse($result['enabled']);
        self::assertSame(0, $result['penalty_amount']);
        self::assertSame(0, $result['effective_late_days']);
    }

    public function test_it_can_start_penalty_from_invoice_issue_date(): void
    {
        $calculator = new TirtaPenaltyCalculator();
        $invoice = new BillingInvoice([
            'status' => 'issued',
            'invoice_total' => 150000,
            'issued_at' => CarbonImmutable::parse('2026-07-01 08:00:00'),
            'due_date' => CarbonImmutable::parse('2026-07-10'),
        ]);
        $setting = (new TenantSetting())->forceFill([
            'billing_penalty_enabled' => true,
            'billing_penalty_type' => 'fixed',
            'billing_penalty_base' => 'outstanding_total',
            'billing_penalty_start_basis' => 'issued_at',
            'billing_penalty_value' => 2500,
            'billing_penalty_grace_days' => 1,
        ]);

        $result = $calculator->calculate(
            $invoice,
            ['outstanding_total' => 100000],
            $setting,
            CarbonImmutable::parse('2026-07-04')
        );

        self::assertTrue($result['enabled']);
        self::assertSame('issued_at', $result['start_basis']);
        self::assertSame(3, $result['late_days']);
        self::assertSame(2, $result['effective_late_days']);
        self::assertSame(5000, $result['penalty_amount']);
    }

    public function test_it_does_not_calculate_penalty_on_posted_penalty_total(): void
    {
        $calculator = new TirtaPenaltyCalculator();
        $invoice = new BillingInvoice([
            'status' => 'issued',
            'invoice_total' => 120000,
            'penalty_total' => 20000,
            'due_date' => CarbonImmutable::parse('2026-07-01'),
        ]);
        $setting = (new TenantSetting())->forceFill([
            'billing_penalty_enabled' => true,
            'billing_penalty_type' => 'percentage',
            'billing_penalty_base' => 'invoice_total',
            'billing_penalty_start_basis' => 'due_date',
            'billing_penalty_value' => 1,
            'billing_penalty_grace_days' => 0,
        ]);

        $result = $calculator->calculate(
            $invoice,
            ['outstanding_total' => 120000],
            $setting,
            CarbonImmutable::parse('2026-07-03')
        );

        self::assertTrue($result['enabled']);
        self::assertSame(100000, $result['base_amount']);
        self::assertSame(1000, $result['daily_penalty_amount']);
        self::assertSame(2000, $result['penalty_amount']);
    }
}
