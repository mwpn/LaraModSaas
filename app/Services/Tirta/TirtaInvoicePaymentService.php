<?php

declare(strict_types=1);

namespace App\Services\Tirta;

use App\Models\Tirta\BillingInvoice;
use App\Models\Tirta\BillingPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TirtaInvoicePaymentService
{
    public function recordPayment(BillingInvoice $invoice, array $payload): BillingPayment
    {
        $payment = $invoice->payments()->create([
            'payment_method' => $payload['payment_method'],
            'amount' => (int) $payload['amount'],
            'paid_at' => $payload['paid_at'] ?? now(),
            'reference_number' => $payload['reference_number'] ?? null,
            'received_by' => $payload['received_by'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'meta' => $payload['meta'] ?? [],
        ]);

        $invoice->unsetRelation('payments');
        $invoice->load('payments');

        $this->syncInvoiceState($invoice);

        return $payment;
    }

    public function summary(BillingInvoice $invoice): array
    {
        $payments = $this->payments($invoice);
        $paidTotal = (int) $payments->sum('amount');
        $outstandingTotal = max((int) $invoice->invoice_total - $paidTotal, 0);
        $lastPaidAt = $payments
            ->pluck('paid_at')
            ->filter(fn ($value) => $value instanceof CarbonInterface)
            ->sortBy(fn (CarbonInterface $value) => $value->getTimestamp())
            ->last();

        return [
            'payments_count' => $payments->count(),
            'paid_total' => $paidTotal,
            'outstanding_total' => $outstandingTotal,
            'is_paid' => $outstandingTotal === 0 && $paidTotal > 0,
            'last_paid_at' => $lastPaidAt,
        ];
    }

    public function syncInvoiceState(BillingInvoice $invoice): BillingInvoice
    {
        $invoice->forceFill($this->statePayload($invoice))->save();

        return $invoice;
    }

    public function statePayload(BillingInvoice $invoice): array
    {
        if ($invoice->status === 'cancelled') {
            return [
                'status' => 'cancelled',
                'paid_at' => $invoice->paid_at,
            ];
        }

        $summary = $this->summary($invoice);
        $isPaid = (bool) $summary['is_paid'];

        return [
            'status' => $isPaid ? 'paid' : 'issued',
            'paid_at' => $isPaid ? $summary['last_paid_at'] : null,
        ];
    }

    protected function payments(BillingInvoice $invoice): Collection
    {
        if ($invoice->relationLoaded('payments')) {
            return $invoice->payments;
        }

        $invoice->load('payments');

        return $invoice->payments;
    }
}
