<?php

declare(strict_types=1);

namespace App\Services\Tirta;

use App\Models\Tirta\BillingInvoice;
use Carbon\CarbonInterface;
use Modules\BaseFeature\Models\TenantSetting;

class TirtaPenaltyCalculator
{
    public function calculate(
        BillingInvoice $invoice,
        array $paymentSummary,
        TenantSetting $setting,
        ?CarbonInterface $asOf = null,
    ): array {
        $asOfDate = ($asOf ?? now())->copy()->startOfDay();
        $dueDate = $invoice->due_date?->copy()->startOfDay();
        $issuedDate = $invoice->issued_at?->copy()->startOfDay();
        $postedPenaltyTotal = max((int) ($invoice->penalty_total ?? 0), 0);
        $principalInvoiceTotal = max((int) $invoice->invoice_total - $postedPenaltyTotal, 0);
        $baseOutstanding = max((int) ($paymentSummary['outstanding_total'] ?? $invoice->invoice_total) - $postedPenaltyTotal, 0);
        $penaltyEnabled = (bool) ($setting->getAttribute('billing_penalty_enabled') ?? false);
        $penaltyType = (string) ($setting->getAttribute('billing_penalty_type') ?? 'fixed');
        $penaltyBase = (string) ($setting->getAttribute('billing_penalty_base') ?? 'outstanding_total');
        $penaltyStartBasis = (string) ($setting->getAttribute('billing_penalty_start_basis') ?? 'due_date');
        $penaltyValue = (float) ($setting->getAttribute('billing_penalty_value') ?? 0);
        $graceDays = max((int) ($setting->getAttribute('billing_penalty_grace_days') ?? 0), 0);
        $maxPenaltyAmount = $setting->getAttribute('billing_penalty_max_amount');
        $maxPenalty = $maxPenaltyAmount !== null ? max((int) $maxPenaltyAmount, 0) : null;
        $penaltyAnchor = $penaltyStartBasis === 'issued_at' ? $issuedDate : $dueDate;

        if (! $penaltyEnabled || $invoice->status !== 'issued' || $penaltyAnchor === null || $penaltyValue <= 0) {
            return $this->emptyPayload($penaltyType, $penaltyBase, $penaltyStartBasis, $graceDays, $maxPenalty);
        }

        if ($penaltyAnchor->gte($asOfDate)) {
            return $this->emptyPayload($penaltyType, $penaltyBase, $penaltyStartBasis, $graceDays, $maxPenalty);
        }

        $daysLate = (int) $penaltyAnchor->diffInDays($asOfDate);
        $effectiveLateDays = max($daysLate - $graceDays, 0);

        if ($effectiveLateDays < 1) {
            return $this->emptyPayload($penaltyType, $penaltyBase, $penaltyStartBasis, $graceDays, $maxPenalty);
        }

        $penaltyBaseAmount = $penaltyBase === 'invoice_total'
            ? $principalInvoiceTotal
            : $baseOutstanding;

        if ($penaltyBaseAmount < 1) {
            return $this->emptyPayload($penaltyType, $penaltyBase, $penaltyStartBasis, $graceDays, $maxPenalty);
        }

        $dailyPenaltyAmount = $penaltyType === 'percentage'
            ? (int) round($penaltyBaseAmount * ($penaltyValue / 100))
            : (int) round($penaltyValue);

        $penaltyAmount = $dailyPenaltyAmount * $effectiveLateDays;

        if ($maxPenalty !== null) {
            $penaltyAmount = min($penaltyAmount, $maxPenalty);
        }

        return [
            'enabled' => true,
            'type' => $penaltyType,
            'frequency' => 'daily',
            'base' => $penaltyBase,
            'start_basis' => $penaltyStartBasis,
            'value' => $penaltyValue,
            'grace_days' => $graceDays,
            'late_days' => $daysLate,
            'effective_late_days' => $effectiveLateDays,
            'base_amount' => $penaltyBaseAmount,
            'daily_penalty_amount' => $dailyPenaltyAmount,
            'penalty_amount' => max($penaltyAmount, 0),
            'max_penalty_amount' => $maxPenalty,
            'label' => $this->label($penaltyType, $penaltyValue, $penaltyBase, $penaltyStartBasis, $graceDays),
        ];
    }

    protected function emptyPayload(string $type, string $base, string $startBasis, int $graceDays, ?int $maxPenalty): array
    {
        return [
            'enabled' => false,
            'type' => $type,
            'frequency' => 'daily',
            'base' => $base,
            'start_basis' => $startBasis,
            'value' => 0,
            'grace_days' => $graceDays,
            'late_days' => 0,
            'effective_late_days' => 0,
            'base_amount' => 0,
            'daily_penalty_amount' => 0,
            'penalty_amount' => 0,
            'max_penalty_amount' => $maxPenalty,
            'label' => $this->label($type, 0, $base, $startBasis, $graceDays),
        ];
    }

    protected function label(string $type, float $value, string $base, string $startBasis, int $graceDays): string
    {
        $valueLabel = $type === 'percentage'
            ? rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') . '%'
            : 'Rp ' . number_format((int) round($value), 0, ',', '.');

        $baseLabel = $base === 'invoice_total' ? 'total invoice' : 'sisa tagihan';
        $basisLabel = $startBasis === 'issued_at' ? 'tanggal terbit' : 'jatuh tempo';

        return sprintf('%s per hari dari %s, mulai sesudah %s, grace %d hari', $valueLabel, $baseLabel, $basisLabel, $graceDays);
    }
}
