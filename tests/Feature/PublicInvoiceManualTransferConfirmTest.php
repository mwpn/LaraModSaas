<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CentralSetting;
use App\Models\Tenant;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\ManualTransferInboxService;
use App\Services\Central\ManualTransferService;
use App\Services\Central\TenantSubscriptionInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicInvoiceManualTransferConfirmTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
        });

        CentralSetting::setPaymentMethodSettings([
            'manual_transfer' => [
                'enabled' => true,
                'bank_name' => 'BCA',
                'account_name' => 'AirCloud',
                'account_number' => '1234567890',
                'notes' => 'Transfer exact sesuai nominal unik.',
                'bca_email_fetcher' => [
                    'enabled' => true,
                ],
            ],
        ]);
    }

    public function test_tenant_confirm_manual_transfer_fetches_inbox_and_marks_invoice_paid(): void
    {
        $tenantId = 'tenant-confirm-' . bin2hex(random_bytes(4));

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Confirm',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-CONFIRM-001',
                    'period_label' => 'Juli 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 125000,
                    'issued_at' => now()->toIso8601String(),
                    'due_at' => now()->addDays(7)->toIso8601String(),
                    'paid_at' => null,
                    'payment' => [
                        'method' => '',
                        'status' => '',
                        'manual_transfer' => [
                            'bank_name' => 'BCA',
                            'account_name' => 'AirCloud',
                            'account_number' => '1234567890',
                            'base_amount' => 125000,
                            'unique_code' => 321,
                            'expected_amount' => 125321,
                        ],
                    ],
                ],
            ],
        ]);

        app()->instance(ManualTransferInboxService::class, new FakeManualTransferInboxService('matched_auto'));

        $response = $this->from('http://aircloud.biz.id/pay/' . $tenantId . '/INV-CONFIRM-001')
            ->post('http://aircloud.biz.id/pay/' . $tenantId . '/INV-CONFIRM-001/manual-transfer/confirm');

        $response->assertRedirect('http://aircloud.biz.id/pay/' . $tenantId . '/INV-CONFIRM-001');
        $response->assertSessionHas('status', 'Transfer berhasil ditemukan di email BCA dan invoice otomatis ditandai paid.');

        $tenant = Tenant::query()->findOrFail($tenantId);
        $invoice = collect($tenant->billingInvoices())->firstWhere('invoice_number', 'INV-CONFIRM-001');

        self::assertSame('paid', data_get($invoice, 'status'));
        self::assertSame('paid', data_get($invoice, 'payment.status'));
        self::assertSame('imap-msg-001', data_get($invoice, 'payment.manual_transfer.evidence.message_id'));
        self::assertSame('bca_email_imap', data_get($invoice, 'payment.manual_transfer.source_adapter'));
    }

    public function test_tenant_confirm_manual_transfer_shows_error_when_inbox_finds_nothing(): void
    {
        $tenantId = 'tenant-confirm-' . bin2hex(random_bytes(4));

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Confirm',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-CONFIRM-404',
                    'period_label' => 'Juli 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 88000,
                    'issued_at' => now()->toIso8601String(),
                    'due_at' => now()->addDays(7)->toIso8601String(),
                    'paid_at' => null,
                    'payment' => [
                        'manual_transfer' => [
                            'base_amount' => 88000,
                            'unique_code' => 222,
                            'expected_amount' => 88222,
                        ],
                    ],
                ],
            ],
        ]);

        app()->instance(ManualTransferInboxService::class, new FakeManualTransferInboxService('no_match'));

        $response = $this->from('http://aircloud.biz.id/pay/' . $tenantId . '/INV-CONFIRM-404')
            ->post('http://aircloud.biz.id/pay/' . $tenantId . '/INV-CONFIRM-404/manual-transfer/confirm');

        $response->assertRedirect('http://aircloud.biz.id/pay/' . $tenantId . '/INV-CONFIRM-404');
        $response->assertSessionHasErrors([
            'invoice' => 'Belum ditemukan email transfer BCA dengan nominal exact.',
        ]);
    }
}

class FakeManualTransferInboxService extends ManualTransferInboxService
{
    public function __construct(protected string $resultStatus)
    {
        $auditLogger = new CentralAuditLogger();

        parent::__construct($auditLogger, new ManualTransferService($auditLogger, new TenantSubscriptionInvoiceService()));
    }

    public function fetchAndReconcileInvoice(Tenant $tenant, array $invoice): array
    {
        if ($this->resultStatus !== 'matched_auto') {
            return [
                'status' => $this->resultStatus,
                'message' => 'Belum ditemukan email transfer BCA dengan nominal exact.',
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            ];
        }

        $matchedAt = CarbonImmutable::now()->toIso8601String();
        $billingInvoices = collect($tenant->billingInvoices())
            ->map(function (array $candidate) use ($invoice, $matchedAt): array {
                if (($candidate['invoice_number'] ?? null) !== ($invoice['invoice_number'] ?? null)) {
                    return $candidate;
                }

                $candidate['status'] = 'paid';
                $candidate['paid_at'] = $matchedAt;
                $candidate['payment'] = array_merge((array) ($candidate['payment'] ?? []), [
                    'method' => 'manual_transfer',
                    'status' => 'paid',
                    'manual_transfer' => array_merge((array) data_get($candidate, 'payment.manual_transfer', []), [
                        'matched_by' => 'bca_email_unique_code',
                        'matched_at' => $matchedAt,
                        'source_adapter' => 'bca_email_imap',
                        'evidence' => [
                            'message_id' => 'imap-msg-001',
                            'credit_amount' => (int) data_get($candidate, 'payment.manual_transfer.expected_amount', 0),
                        ],
                    ]),
                ]);

                return $candidate;
            })
            ->values()
            ->all();

        $tenant->forceFill([
            'billing_invoices' => $billingInvoices,
            'last_invoice_status_updated_at' => $matchedAt,
        ])->save();

        $tenant = $tenant->fresh();
        $updatedInvoice = collect($tenant->billingInvoices())
            ->firstWhere('invoice_number', (string) ($invoice['invoice_number'] ?? ''));

        return [
            'status' => 'matched_auto',
            'message' => 'ok',
            'message_id' => 'imap-msg-001',
            'tenant' => $tenant,
            'invoice' => $updatedInvoice,
        ];
    }
}
