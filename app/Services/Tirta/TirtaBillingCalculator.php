<?php

declare(strict_types=1);

namespace App\Services\Tirta;

use App\Models\Tirta\MeterReading;
use App\Models\Tirta\TariffScheme;
use App\Models\Tirta\TariffSchemeTier;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TirtaBillingCalculator
{
    public function calculate(MeterReading $reading, TariffScheme $scheme): array
    {
        $usage = max((int) $reading->usage_volume, 0);
        $minimumCharge = $this->toMoney((float) $scheme->minimum_charge);
        $adminFee = $this->toMoney((float) $scheme->admin_fee);

        $usageCalculation = $scheme->calculation_mode === 'tiered'
            ? $this->calculateTiered($usage, $scheme->tiers)
            : $this->calculateFlat($usage, $scheme);

        $rawWaterCharge = (int) $usageCalculation['water_charge_total'];
        $waterCharge = max($rawWaterCharge, $minimumCharge);
        $minimumAdjustment = max($minimumCharge - $rawWaterCharge, 0);
        $invoiceTotal = $waterCharge + $adminFee;

        $lines = collect($usageCalculation['lines'] ?? []);

        if ($minimumAdjustment > 0) {
            $lines->push([
                'line_type' => 'minimum_adjustment',
                'label' => 'Beban Minimum',
                'quantity' => 1,
                'unit_price' => $minimumAdjustment,
                'line_total' => $minimumAdjustment,
                'meta' => [
                    'minimum_charge' => $minimumCharge,
                ],
            ]);
        }

        if ($adminFee > 0) {
            $lines->push([
                'line_type' => 'admin_fee',
                'label' => 'Beban Tetap',
                'quantity' => 1,
                'unit_price' => $adminFee,
                'line_total' => $adminFee,
                'meta' => [],
            ]);
        }

        return [
            'usage_volume' => $usage,
            'water_charge_total' => $waterCharge,
            'raw_water_charge_total' => $rawWaterCharge,
            'minimum_charge_applied' => $minimumAdjustment,
            'admin_fee_total' => $adminFee,
            'invoice_total' => $invoiceTotal,
            'lines' => $lines->values()->all(),
            'snapshot' => [
                'tariff_scheme' => [
                    'id' => $scheme->id,
                    'name' => $scheme->name,
                    'calculation_mode' => $scheme->calculation_mode,
                ],
                'usage_volume' => $usage,
                'raw_water_charge_total' => $rawWaterCharge,
                'minimum_charge' => $minimumCharge,
                'minimum_adjustment' => $minimumAdjustment,
                'admin_fee' => $adminFee,
                'invoice_total' => $invoiceTotal,
                'lines' => $lines->values()->all(),
            ],
        ];
    }

    protected function calculateFlat(int $usage, TariffScheme $scheme): array
    {
        $basePrice = $scheme->base_price_per_m3 !== null ? $this->toMoney((float) $scheme->base_price_per_m3) : null;

        if ($basePrice === null) {
            throw ValidationException::withMessages([
                'tariff_scheme' => sprintf('Skema tarif %s belum punya harga flat per m3.', $scheme->name),
            ]);
        }

        $lineTotal = $usage * $basePrice;

        return [
            'water_charge_total' => $lineTotal,
            'lines' => [[
                'line_type' => 'water_usage',
                'label' => sprintf('Pemakaian Air %d m3', $usage),
                'quantity' => $usage,
                'unit_price' => $basePrice,
                'line_total' => $lineTotal,
                'meta' => [
                    'calculation_mode' => 'flat',
                ],
            ]],
        ];
    }

    protected function calculateTiered(int $usage, Collection $tiers): array
    {
        if ($tiers->isEmpty()) {
            throw ValidationException::withMessages([
                'tariff_scheme' => 'Skema tarif bertingkat wajib punya tier aktif sebelum generate billing.',
            ]);
        }

        $waterChargeTotal = 0;
        $lines = [];

        /** @var TariffSchemeTier $tier */
        foreach ($tiers as $index => $tier) {
            if ($usage < $tier->start_usage) {
                continue;
            }

            $effectiveEnd = $tier->end_usage !== null ? min($usage, (int) $tier->end_usage) : $usage;
            $coveredUnits = max(($effectiveEnd - (int) $tier->start_usage) + 1, 0);

            if ($coveredUnits < 1) {
                continue;
            }

            $unitPrice = $this->toMoney((float) $tier->price);
            $lineTotal = $tier->charge_type === 'flat_block'
                ? $unitPrice
                : ($coveredUnits * $unitPrice);

            $waterChargeTotal += $lineTotal;
            $lines[] = [
                'line_type' => 'water_tier',
                'label' => sprintf(
                    'Tier %d (%d-%s m3)',
                    $index + 1,
                    $tier->start_usage,
                    $tier->end_usage ?? 'seterusnya'
                ),
                'quantity' => $tier->charge_type === 'flat_block' ? 1 : $coveredUnits,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'meta' => [
                    'charge_type' => $tier->charge_type,
                    'start_usage' => $tier->start_usage,
                    'end_usage' => $tier->end_usage,
                    'covered_units' => $coveredUnits,
                ],
            ];
        }

        return [
            'water_charge_total' => $waterChargeTotal,
            'lines' => $lines,
        ];
    }

    protected function toMoney(float $value): int
    {
        return (int) round($value);
    }
}
