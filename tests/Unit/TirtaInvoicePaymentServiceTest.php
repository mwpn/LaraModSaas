<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tirta\BillingInvoice;
use App\Models\Tirta\BillingPayment;
use App\Services\Tirta\TirtaInvoicePaymentService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TirtaInvoicePaymentServiceTest extends TestCase
{
    public function test_it_builds_partial_payment_summary_and_keeps_invoice_issued(): void
    {
        $service = new TirtaInvoicePaymentService();
        $invoice = new BillingInvoice([
            'status' => 'issued',
            'invoice_total' => 12000,
        ]);
        $invoice->setRelation('payments', new Collection([
            new BillingPayment([
                'amount' => 5000,
                'paid_at' => CarbonImmutable::parse('2026-07-06 09:00:00'),
            ]),
        ]));

        $summary = $service->summary($invoice);
        $state = $service->statePayload($invoice);

        self::assertSame(1, $summary['payments_count']);
        self::assertSame(5000, $summary['paid_total']);
        self::assertSame(7000, $summary['outstanding_total']);
        self::assertFalse($summary['is_paid']);
        self::assertSame('issued', $state['status']);
        self::assertNull($state['paid_at']);
    }

    public function test_it_marks_invoice_paid_when_payments_cover_full_total(): void
    {
        $service = new TirtaInvoicePaymentService();
        $invoice = new BillingInvoice([
            'status' => 'issued',
            'invoice_total' => 12000,
        ]);
        $invoice->setRelation('payments', new Collection([
            new BillingPayment([
                'amount' => 4000,
                'paid_at' => CarbonImmutable::parse('2026-07-06 08:00:00'),
            ]),
            new BillingPayment([
                'amount' => 8000,
                'paid_at' => CarbonImmutable::parse('2026-07-06 10:30:00'),
            ]),
        ]));

        $summary = $service->summary($invoice);
        $state = $service->statePayload($invoice);

        self::assertSame(2, $summary['payments_count']);
        self::assertSame(12000, $summary['paid_total']);
        self::assertSame(0, $summary['outstanding_total']);
        self::assertTrue($summary['is_paid']);
        self::assertSame('paid', $state['status']);
        self::assertInstanceOf(CarbonInterface::class, $state['paid_at']);
        self::assertSame('2026-07-06 10:30:00', $state['paid_at']->format('Y-m-d H:i:s'));
    }

    public function test_it_keeps_cancelled_invoice_state_untouched(): void
    {
        $service = new TirtaInvoicePaymentService();
        $paidAt = CarbonImmutable::parse('2026-07-06 12:00:00');
        $invoice = new BillingInvoice([
            'status' => 'cancelled',
            'invoice_total' => 12000,
            'paid_at' => $paidAt,
        ]);
        $invoice->setRelation('payments', new Collection([
            new BillingPayment([
                'amount' => 12000,
                'paid_at' => $paidAt,
            ]),
        ]));

        $state = $service->statePayload($invoice);

        self::assertSame('cancelled', $state['status']);
        self::assertSame('2026-07-06 12:00:00', $state['paid_at']?->format('Y-m-d H:i:s'));
    }
}
