<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tirta\MeterReading;
use App\Models\Tirta\TariffScheme;
use App\Models\Tirta\TariffSchemeTier;
use App\Services\Tirta\TirtaBillingCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TirtaBillingCalculatorTest extends TestCase
{
    public function test_it_applies_minimum_charge_and_admin_fee_for_flat_tariff(): void
    {
        $calculator = new TirtaBillingCalculator();
        $reading = new MeterReading([
            'usage_volume' => 2,
        ]);

        $scheme = new TariffScheme([
            'id' => 'scheme-flat',
            'name' => 'Flat Sosial',
            'calculation_mode' => 'flat',
            'base_price_per_m3' => 1000,
            'minimum_charge' => 5000,
            'admin_fee' => 1500,
        ]);
        $scheme->setRelation('tiers', new Collection());

        $result = $calculator->calculate($reading, $scheme);

        self::assertSame(2, $result['usage_volume']);
        self::assertSame(5000, $result['water_charge_total']);
        self::assertSame(2000, $result['raw_water_charge_total']);
        self::assertSame(3000, $result['minimum_charge_applied']);
        self::assertSame(1500, $result['admin_fee_total']);
        self::assertSame(6500, $result['invoice_total']);
        self::assertSame(
            ['water_usage', 'minimum_adjustment', 'admin_fee'],
            array_column($result['lines'], 'line_type')
        );
    }

    public function test_it_supports_mixed_tiered_blocks_and_progressive_usage(): void
    {
        $calculator = new TirtaBillingCalculator();
        $reading = new MeterReading([
            'usage_volume' => 7,
        ]);

        $scheme = new TariffScheme([
            'id' => 'scheme-tiered',
            'name' => 'Bertingkat Kombinasi',
            'calculation_mode' => 'tiered',
            'minimum_charge' => 0,
            'admin_fee' => 500,
        ]);
        $scheme->setRelation('tiers', new Collection([
            new TariffSchemeTier([
                'start_usage' => 1,
                'end_usage' => 5,
                'charge_type' => 'flat_block',
                'price' => 4000,
                'sort_order' => 1,
            ]),
            new TariffSchemeTier([
                'start_usage' => 6,
                'end_usage' => null,
                'charge_type' => 'per_unit',
                'price' => 1500,
                'sort_order' => 2,
            ]),
        ]));

        $result = $calculator->calculate($reading, $scheme);

        self::assertSame(7, $result['usage_volume']);
        self::assertSame(7000, $result['water_charge_total']);
        self::assertSame(7000, $result['raw_water_charge_total']);
        self::assertSame(0, $result['minimum_charge_applied']);
        self::assertSame(500, $result['admin_fee_total']);
        self::assertSame(7500, $result['invoice_total']);
        self::assertCount(3, $result['lines']);
        self::assertSame('flat_block', $result['lines'][0]['meta']['charge_type']);
        self::assertSame(1, $result['lines'][0]['quantity']);
        self::assertSame(2, $result['lines'][1]['quantity']);
        self::assertSame(3000, $result['lines'][1]['line_total']);
        self::assertSame('admin_fee', $result['lines'][2]['line_type']);
    }
}
