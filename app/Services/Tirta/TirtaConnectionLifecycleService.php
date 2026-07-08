<?php

declare(strict_types=1);

namespace App\Services\Tirta;

use App\Models\Tirta\BillingInvoice;
use App\Models\Tirta\BillingPeriod;
use App\Models\Tirta\ServiceConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\BaseFeature\Models\TenantSetting;

class TirtaConnectionLifecycleService
{
    public function auditAndDisconnectOverdueConnections(TenantSetting $setting, ?Carbon $asOf = null): array
    {
        $asOfDate = ($asOf ?? now())->copy()->startOfDay();
        $thresholdMonths = max((int) ($setting->getAttribute('billing_disconnect_after_months') ?? 3), 1);

        $overdueSummaryByConnection = BillingInvoice::query()
            ->selectRaw('service_connection_id, COUNT(*) AS overdue_count, MIN(due_date) AS oldest_due_date')
            ->where('status', 'issued')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $asOfDate->toDateString())
            ->groupBy('service_connection_id')
            ->havingRaw('COUNT(*) >= ?', [$thresholdMonths])
            ->get()
            ->mapWithKeys(function ($row): array {
                $oldestDue = $row->oldest_due_date ? Carbon::parse($row->oldest_due_date) : null;

                return [
                    (string) $row->service_connection_id => [
                        'overdue_count' => (int) ($row->overdue_count ?? 0),
                        'oldest_due_date' => $oldestDue,
                    ],
                ];
            });

        if ($overdueSummaryByConnection->isEmpty()) {
            return [
                'threshold_months' => $thresholdMonths,
                'scanned' => 0,
                'disconnected' => 0,
                'skipped' => 0,
            ];
        }

        $connections = ServiceConnection::query()
            ->whereIn('id', $overdueSummaryByConnection->keys())
            ->get();

        $disconnected = 0;
        $skipped = 0;

        /** @var ServiceConnection $connection */
        foreach ($connections as $connection) {
            if ($connection->status !== 'active') {
                $skipped++;
                continue;
            }

            $summary = $overdueSummaryByConnection->get((string) $connection->id);
            $oldestDue = $summary['oldest_due_date'] ?? null;
            $overdueCount = (int) ($summary['overdue_count'] ?? 0);

            if (! $oldestDue instanceof Carbon) {
                $skipped++;
                continue;
            }

            if ($oldestDue->copy()->startOfDay()->gte($asOfDate)) {
                $skipped++;
                continue;
            }

            $connection->forceFill([
                'status' => 'disconnected',
                'disconnected_at' => $asOfDate->copy(),
                'disconnected_reason' => sprintf(
                    'Tunggakan %d bulan (minimal %d). Jatuh tempo tertua: %s',
                    $overdueCount,
                    $thresholdMonths,
                    $oldestDue->toDateString()
                ),
            ])->save();

            $disconnected++;
        }

        return [
            'threshold_months' => $thresholdMonths,
            'scanned' => $connections->count(),
            'disconnected' => $disconnected,
            'skipped' => $skipped,
        ];
    }

    public function requestReactivation(
        ServiceConnection $connection,
        TenantSetting $setting,
        bool $allowInstallment,
        ?Carbon $asOf = null
    ): BillingInvoice {
        $asOfDateTime = ($asOf ?? now())->copy();
        $fee = max((int) ($setting->getAttribute('billing_reactivation_fee_amount') ?? 0), 0);

        if ($connection->status !== 'disconnected') {
            throw new \RuntimeException('Sambungan tidak dalam status cabut.');
        }

        if ($fee < 1) {
            throw new \RuntimeException('Biaya aktivasi belum diatur. Isi dulu nominal biaya aktivasi di pengaturan billing tenant.');
        }

        $existingActivationInvoiceId = (string) ($connection->getAttribute('reactivation_activation_invoice_id') ?? '');
        if ($existingActivationInvoiceId !== '') {
            $existing = BillingInvoice::query()->find($existingActivationInvoiceId);
            if ($existing instanceof BillingInvoice && $existing->status !== 'cancelled') {
                return $existing;
            }
        }

        $period = $this->ensureReactivationPeriod($asOfDateTime);
        $invoiceNumber = $this->makeActivationInvoiceNumber($connection, $asOfDateTime);

        $invoice = BillingInvoice::query()->create([
            'billing_period_id' => $period->id,
            'meter_reading_id' => null,
            'customer_id' => $connection->customer_id,
            'service_connection_id' => $connection->id,
            'tariff_scheme_id' => null,
            'invoice_number' => $invoiceNumber,
            'status' => 'issued',
            'usage_volume' => 0,
            'water_charge_total' => 0,
            'minimum_charge_applied' => 0,
            'admin_fee_total' => 0,
            'penalty_total' => 0,
            'invoice_total' => $fee,
            'due_date' => $period->due_date,
            'issued_at' => $asOfDateTime,
            'paid_at' => null,
            'calculation_snapshot' => [
                'invoice_type' => 'reactivation',
                'reactivation_fee' => $fee,
                'allow_installment' => $allowInstallment,
            ],
            'notes' => sprintf(
                'Invoice aktivasi sambungan %s. Mode cicil tunggakan: %s.',
                $connection->service_number,
                $allowInstallment ? 'boleh' : 'tidak'
            ),
        ]);

        $invoice->lines()->create([
            'line_type' => 'activation_fee',
            'label' => 'Biaya Aktivasi Sambungan',
            'quantity' => 1,
            'unit_price' => $fee,
            'line_total' => $fee,
            'meta' => [
                'invoice_type' => 'reactivation',
                'allow_installment' => $allowInstallment,
            ],
            'sort_order' => 0,
        ]);

        $connection->forceFill([
            'reactivation_requested_at' => $asOfDateTime,
            'reactivation_activation_invoice_id' => $invoice->id,
            'reactivation_allow_installment' => $allowInstallment,
        ])->save();

        return $invoice;
    }

    public function finalizeReactivationIfEligible(ServiceConnection $connection, ?Carbon $asOf = null): bool
    {
        if ($connection->status !== 'disconnected') {
            return false;
        }

        $activationInvoiceId = (string) ($connection->getAttribute('reactivation_activation_invoice_id') ?? '');
        if ($activationInvoiceId === '') {
            return false;
        }

        $activationInvoice = BillingInvoice::query()->with('payments')->find($activationInvoiceId);
        if (! $activationInvoice instanceof BillingInvoice) {
            return false;
        }

        if ($activationInvoice->status !== 'paid') {
            return false;
        }

        $allowInstallment = (bool) ($connection->getAttribute('reactivation_allow_installment') ?? true);

        if (! $allowInstallment) {
            $remainingIssued = BillingInvoice::query()
                ->where('service_connection_id', $connection->id)
                ->where('status', 'issued')
                ->exists();

            if ($remainingIssued) {
                return false;
            }
        }

        $asOfDateTime = ($asOf ?? now())->copy();

        $connection->forceFill([
            'status' => 'active',
            'reactivated_at' => $asOfDateTime,
            'disconnected_reason' => null,
            'reactivation_activation_invoice_id' => null,
        ])->save();

        return true;
    }

    public function requestInstallation(
        ServiceConnection $connection,
        TenantSetting $setting,
        string $paymentScheme,
        ?int $installmentMonths = null,
        ?Carbon $asOf = null
    ): BillingInvoice {
        $asOfDateTime = ($asOf ?? now())->copy();

        $baseFee = max((int) ($setting->getAttribute('billing_installation_fee_amount') ?? 0), 0);
        if ($baseFee < 1) {
            throw new \RuntimeException('Biaya pasang baru belum diatur. Isi dulu nominalnya di pengaturan billing tenant.');
        }

        $paymentScheme = strtolower(trim($paymentScheme));
        if (! in_array($paymentScheme, ['cash', 'installment'], true)) {
            throw new \RuntimeException('Skema pembayaran pasang baru tidak valid.');
        }

        $allowInstallmentSetting = (bool) ($setting->getAttribute('billing_installation_allow_installment') ?? false);
        if ($paymentScheme === 'installment' && ! $allowInstallmentSetting) {
            throw new \RuntimeException('Tenant ini tidak mengaktifkan opsi cicilan pasang baru.');
        }

        $existing = BillingInvoice::query()
            ->where('service_connection_id', $connection->id)
            ->where('status', 'issued')
            ->where('calculation_snapshot->invoice_type', 'installation')
            ->orderByDesc('issued_at')
            ->first();

        if ($existing instanceof BillingInvoice) {
            return $existing;
        }

        $promoDiscount = $this->installationPromoDiscount($setting, $baseFee, $asOfDateTime);
        $finalFee = max($baseFee - $promoDiscount, 0);

        if ($paymentScheme === 'cash') {
            $period = $this->ensureInstallationPeriod($asOfDateTime);

            $invoice = BillingInvoice::query()->create([
                'billing_period_id' => $period->id,
                'meter_reading_id' => null,
                'customer_id' => $connection->customer_id,
                'service_connection_id' => $connection->id,
                'tariff_scheme_id' => null,
                'invoice_number' => $this->makeInstallationInvoiceNumber($connection, $asOfDateTime),
                'status' => $finalFee > 0 ? 'issued' : 'paid',
                'usage_volume' => 0,
                'water_charge_total' => 0,
                'minimum_charge_applied' => 0,
                'admin_fee_total' => 0,
                'penalty_total' => 0,
                'invoice_total' => $finalFee,
                'due_date' => $period->due_date,
                'issued_at' => $asOfDateTime,
                'paid_at' => $finalFee > 0 ? null : $asOfDateTime,
                'calculation_snapshot' => [
                    'invoice_type' => 'installation',
                    'payment_scheme' => 'cash',
                    'installation_fee_original' => $baseFee,
                    'promo_discount' => $promoDiscount,
                    'installation_fee_final' => $finalFee,
                    'installment_index' => 1,
                    'installment_months' => 1,
                ],
                'notes' => sprintf(
                    'Invoice pasang baru sambungan %s. Skema pembayaran: tunai. Promo: Rp %s.',
                    $connection->service_number,
                    number_format($promoDiscount, 0, ',', '.')
                ),
            ]);

            $invoice->lines()->create([
                'line_type' => 'installation_fee',
                'label' => $promoDiscount > 0 ? 'Biaya Pasang Baru (Promo)' : 'Biaya Pasang Baru',
                'quantity' => 1,
                'unit_price' => $finalFee,
                'line_total' => $finalFee,
                'meta' => [
                    'invoice_type' => 'installation',
                    'installation_fee_original' => $baseFee,
                    'promo_discount' => $promoDiscount,
                ],
                'sort_order' => 0,
            ]);

            if ($finalFee < 1 && $connection->status !== 'active') {
                $this->activateInstalledConnection($connection, $asOfDateTime);
            }

            return $invoice;
        }

        $months = (int) ($installmentMonths ?? $setting->getAttribute('billing_installation_default_installment_months') ?? 3);
        $months = min(max($months, 2), 24);

        if ($finalFee > 0 && $months > $finalFee) {
            $months = max($finalFee, 2);
        }

        $basePart = $finalFee > 0 ? intdiv($finalFee, $months) : 0;
        $remainder = $finalFee > 0 ? ($finalFee % $months) : 0;

        $firstInvoice = null;

        for ($i = 1; $i <= $months; $i++) {
            $scheduledAt = $asOfDateTime->copy()->addMonthsNoOverflow($i - 1);
            $period = $this->ensureInstallationPeriod($scheduledAt);
            $amount = $finalFee > 0
                ? ($i === 1 ? ($basePart + $remainder) : $basePart)
                : 0;

            $invoice = BillingInvoice::query()->create([
                'billing_period_id' => $period->id,
                'meter_reading_id' => null,
                'customer_id' => $connection->customer_id,
                'service_connection_id' => $connection->id,
                'tariff_scheme_id' => null,
                'invoice_number' => $this->makeInstallationInvoiceNumber($connection, $scheduledAt),
                'status' => $amount > 0 ? 'issued' : 'paid',
                'usage_volume' => 0,
                'water_charge_total' => 0,
                'minimum_charge_applied' => 0,
                'admin_fee_total' => 0,
                'penalty_total' => 0,
                'invoice_total' => $amount,
                'due_date' => $period->due_date,
                'issued_at' => $scheduledAt,
                'paid_at' => $amount > 0 ? null : $scheduledAt,
                'calculation_snapshot' => [
                    'invoice_type' => 'installation',
                    'payment_scheme' => 'installment',
                    'installation_fee_original' => $baseFee,
                    'promo_discount' => $promoDiscount,
                    'installation_fee_final' => $finalFee,
                    'installment_index' => $i,
                    'installment_months' => $months,
                ],
                'notes' => sprintf(
                    'Cicilan %d/%d pasang baru sambungan %s. Promo: Rp %s.',
                    $i,
                    $months,
                    $connection->service_number,
                    number_format($promoDiscount, 0, ',', '.')
                ),
            ]);

            $invoice->lines()->create([
                'line_type' => 'installation_fee',
                'label' => sprintf('Cicilan %d/%d Biaya Pasang Baru%s', $i, $months, $promoDiscount > 0 ? ' (Promo)' : ''),
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
                'meta' => [
                    'invoice_type' => 'installation',
                    'installment_index' => $i,
                    'installment_months' => $months,
                ],
                'sort_order' => 0,
            ]);

            if ($firstInvoice === null) {
                $firstInvoice = $invoice;
            }

            if ($amount < 1 && $i === 1 && $connection->status !== 'active') {
                $this->activateInstalledConnection($connection, $scheduledAt);
            }
        }

        if (! $firstInvoice instanceof BillingInvoice) {
            throw new \RuntimeException('Gagal membuat invoice cicilan pasang baru.');
        }

        return $firstInvoice;
    }

    public function finalizeInstallationIfEligible(ServiceConnection $connection, BillingInvoice $invoice, ?Carbon $asOf = null): bool
    {
        if ($connection->status === 'active') {
            return false;
        }

        $snapshotType = (string) data_get($invoice->calculation_snapshot, 'invoice_type', '');
        if ($snapshotType !== 'installation') {
            return false;
        }

        if ($invoice->status !== 'paid') {
            return false;
        }

        $scheme = (string) data_get($invoice->calculation_snapshot, 'payment_scheme', 'cash');
        $installmentIndex = (int) data_get($invoice->calculation_snapshot, 'installment_index', 1);

        if ($scheme === 'installment' && $installmentIndex !== 1) {
            return false;
        }

        $asOfDateTime = ($asOf ?? now())->copy();
        $this->activateInstalledConnection($connection, $asOfDateTime);

        return true;
    }

    protected function ensureReactivationPeriod(Carbon $asOf): BillingPeriod
    {
        $start = $asOf->copy()->startOfMonth()->toDateString();
        $end = $asOf->copy()->endOfMonth()->toDateString();
        $name = sprintf('Reaktivasi %s', $asOf->format('F Y'));

        $period = BillingPeriod::query()
            ->whereNull('meter_reading_period_id')
            ->whereDate('period_start', $start)
            ->whereDate('period_end', $end)
            ->where('name', $name)
            ->first();

        if ($period instanceof BillingPeriod) {
            return $period;
        }

        return BillingPeriod::query()->create([
            'meter_reading_period_id' => null,
            'name' => $name,
            'period_start' => $start,
            'period_end' => $end,
            'due_date' => $asOf->copy()->addDays(7)->toDateString(),
            'status' => 'generated',
            'notes' => 'Periode khusus untuk invoice aktivasi sambungan.',
            'generated_at' => $asOf,
        ]);
    }

    protected function makeActivationInvoiceNumber(ServiceConnection $connection, Carbon $asOf): string
    {
        $suffix = strtoupper(substr(Str::uuid()->toString(), 0, 6));

        return sprintf('TRX-%s-%s-%s', $asOf->format('Ymd'), $connection->service_number, $suffix);
    }

    protected function ensureInstallationPeriod(Carbon $asOf): BillingPeriod
    {
        $start = $asOf->copy()->startOfMonth()->toDateString();
        $end = $asOf->copy()->endOfMonth()->toDateString();
        $name = sprintf('Pasang Baru %s', $asOf->format('F Y'));

        $period = BillingPeriod::query()
            ->whereNull('meter_reading_period_id')
            ->whereDate('period_start', $start)
            ->whereDate('period_end', $end)
            ->where('name', $name)
            ->first();

        if ($period instanceof BillingPeriod) {
            return $period;
        }

        return BillingPeriod::query()->create([
            'meter_reading_period_id' => null,
            'name' => $name,
            'period_start' => $start,
            'period_end' => $end,
            'due_date' => $asOf->copy()->startOfDay()->addDays(7)->toDateString(),
            'status' => 'generated',
            'notes' => 'Periode khusus untuk invoice pasang baru (non-air).',
            'generated_at' => $asOf,
        ]);
    }

    protected function makeInstallationInvoiceNumber(ServiceConnection $connection, Carbon $asOf): string
    {
        $suffix = strtoupper(substr(Str::uuid()->toString(), 0, 6));

        return sprintf('INS-%s-%s-%s', $asOf->format('Ymd'), $connection->service_number, $suffix);
    }

    protected function installationPromoDiscount(TenantSetting $setting, int $baseFee, Carbon $asOf): int
    {
        $enabled = (bool) ($setting->getAttribute('billing_installation_promo_enabled') ?? false);
        if (! $enabled) {
            return 0;
        }

        $discount = max((int) ($setting->getAttribute('billing_installation_promo_discount_amount') ?? 0), 0);
        if ($discount < 1) {
            return 0;
        }

        $startDate = $setting->getAttribute('billing_installation_promo_start_date');
        $endDate = $setting->getAttribute('billing_installation_promo_end_date');

        $asOfDate = $asOf->copy()->startOfDay()->toDateString();

        if ($startDate !== null && (string) $startDate !== '' && $asOfDate < (string) $startDate) {
            return 0;
        }

        if ($endDate !== null && (string) $endDate !== '' && $asOfDate > (string) $endDate) {
            return 0;
        }

        return min($discount, $baseFee);
    }

    protected function activateInstalledConnection(ServiceConnection $connection, Carbon $asOf): void
    {
        $installedAt = $connection->installed_at;

        $connection->forceFill([
            'status' => 'active',
            'installation_workflow_status' => 'active',
            'installed_at' => $installedAt ? $installedAt : $asOf->copy()->toDateString(),
        ])->save();
    }
}
