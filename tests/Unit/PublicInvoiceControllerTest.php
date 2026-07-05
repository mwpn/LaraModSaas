<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Central\PublicInvoiceController;
use App\Services\Central\BillingNotificationService;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\ManualTransferInboxService;
use App\Services\Central\ManualTransferService;
use App\Services\Central\MessageTemplateRenderer;
use PHPUnit\Framework\TestCase;

class PublicInvoiceControllerTest extends TestCase
{
    public function test_it_normalizes_payment_payload_for_public_invoice_flow(): void
    {
        $auditLogger = new CentralAuditLogger();
        $controller = new PublicInvoiceControllerTestProxy(
            $auditLogger,
            new ManualTransferService($auditLogger),
            new ManualTransferInboxService($auditLogger, new ManualTransferService($auditLogger)),
            new BillingNotificationService($auditLogger, new MessageTemplateRenderer())
        );

        $normalized = $controller->normalize([
            'method' => 'qris',
            'status' => 'pending',
            'reference' => 'QRIS-001',
            'manual_transfer' => [
                'base_amount' => 125000,
                'unique_code' => 321,
                'expected_amount' => 125321,
                'evidence' => [
                    'message_id' => 'msg-001',
                ],
            ],
            'qris' => [
                'invoice_id' => 'INV-123',
                'content' => '0002010102',
                'raw_status' => 'unpaid',
            ],
        ]);

        self::assertSame('qris', $normalized['method']);
        self::assertSame('pending', $normalized['status']);
        self::assertSame('QRIS-001', $normalized['reference']);
        self::assertSame('INV-123', $normalized['qris']['invoice_id']);
        self::assertSame('0002010102', $normalized['qris']['content']);
        self::assertSame('unpaid', $normalized['qris']['raw_status']);
        self::assertSame('', $normalized['manual_transfer']['bank_name']);
        self::assertSame(125000, $normalized['manual_transfer']['base_amount']);
        self::assertSame(321, $normalized['manual_transfer']['unique_code']);
        self::assertSame(125321, $normalized['manual_transfer']['expected_amount']);
        self::assertSame('msg-001', $normalized['manual_transfer']['evidence']['message_id']);
    }
}

class PublicInvoiceControllerTestProxy extends PublicInvoiceController
{
    public function normalize(array $payment): array
    {
        return $this->normalizeInvoicePayment($payment);
    }
}
