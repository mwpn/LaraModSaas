<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TenantBillingGuardTest extends TestCase
{
    public function test_it_picks_oldest_collectible_invoice_only_from_issued_or_overdue(): void
    {
        $tenant = new Tenant([
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-PAID',
                    'status' => 'paid',
                    'due_at' => CarbonImmutable::now()->subDays(10)->toIso8601String(),
                    'paid_at' => CarbonImmutable::now()->subDays(9)->toIso8601String(),
                ],
                [
                    'invoice_number' => 'INV-NEWER',
                    'status' => 'issued',
                    'due_at' => CarbonImmutable::now()->subDays(2)->toIso8601String(),
                    'paid_at' => null,
                ],
                [
                    'invoice_number' => 'INV-OLDER',
                    'status' => 'overdue',
                    'due_at' => CarbonImmutable::now()->subDays(6)->toIso8601String(),
                    'paid_at' => null,
                ],
            ],
        ]);

        $invoice = $tenant->oldestCollectibleInvoice();

        self::assertIsArray($invoice);
        self::assertSame('INV-OLDER', $invoice['invoice_number']);
    }

    public function test_it_blocks_access_when_billing_grace_has_passed(): void
    {
        $tenant = new Tenant([
            'billing_grace_days' => 3,
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-OVERDUE',
                    'status' => 'overdue',
                    'due_at' => CarbonImmutable::now()->subDays(5)->toIso8601String(),
                    'paid_at' => null,
                ],
            ],
        ]);

        self::assertTrue($tenant->hasInvoiceOverdueBlock());
        self::assertSame('invoice_overdue', $tenant->accessBlockReason());
    }
}
