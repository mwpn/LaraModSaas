<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CentralSetting;
use App\Models\Tenant;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\ManualTransferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManualTransferEvidenceRouteTest extends TestCase
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

        Schema::create('central_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('level', 16)->default('info');
            $table->string('event_key', 120);
            $table->string('target_type', 80)->nullable();
            $table->string('target_id', 120)->nullable();
            $table->string('summary');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        CentralSetting::setPaymentMethodSettings([
            'manual_transfer' => [
                'enabled' => true,
                'bank_name' => 'BCA',
                'account_name' => 'AirCloud',
                'account_number' => '1234567890',
                'notes' => 'Transfer exact sesuai nominal unik.',
            ],
        ]);

        config()->set('services.billing_payment.manual_transfer.evidence_secret', 'test-evidence-secret');
    }

    public function test_bca_evidence_auto_matches_exact_unique_amount_and_marks_invoice_paid(): void
    {
        $tenantId = 'tenant-transfer-' . bin2hex(random_bytes(4));

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Transfer',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-MANUAL-001',
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

        $response = $this->withHeader('X-AirCloud-Webhook-Secret', 'test-evidence-secret')
            ->postJson('http://aircloud.biz.id/payments/manual-transfer/evidence/bca', [
                'message_id' => 'msg-bca-001',
                'source_adapter' => 'bca_email',
                'parsed_payload' => [
                    'message_id' => 'msg-bca-001',
                    'account_number' => '1234567890',
                    'credit_amount' => 125321,
                    'sender_name' => 'Budi',
                    'transaction_at' => now()->toIso8601String(),
                ],
            ]);
 
        $response->assertOk()
            ->assertJson([
                'status' => 'matched_auto',
                'message_id' => 'msg-bca-001',
                'tenant_id' => $tenantId,
                'invoice_number' => 'INV-MANUAL-001',
            ]);

        $tenant = Tenant::query()->findOrFail($tenantId);
        $invoice = collect($tenant->billingInvoices())->firstWhere('invoice_number', 'INV-MANUAL-001');

        self::assertIsArray($invoice);
        self::assertSame('paid', $invoice['status']);
        self::assertSame('manual_transfer', data_get($invoice, 'payment.method'));
        self::assertSame('paid', data_get($invoice, 'payment.status'));
        self::assertSame('bca_email_unique_code', data_get($invoice, 'payment.manual_transfer.matched_by'));
        self::assertSame('msg-bca-001', data_get($invoice, 'payment.manual_transfer.evidence.message_id'));
        self::assertSame(125321, data_get($invoice, 'payment.manual_transfer.evidence.credit_amount'));
        $this->assertDatabaseHas('central_audit_logs', [
            'event_key' => 'manual_transfer.bca_matched',
            'target_type' => 'tenant',
            'target_id' => $tenantId,
        ]);
    }

    public function test_bca_evidence_requires_valid_webhook_secret(): void
    {
        $response = $this->postJson('http://aircloud.biz.id/payments/manual-transfer/evidence/bca', [
            'message_id' => 'msg-bca-unauthorized',
            'parsed_payload' => [
                'account_number' => '1234567890',
                'credit_amount' => 125321,
            ],
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'status' => 'unauthorized',
            ]);
    }

    public function test_duplicate_bca_message_id_is_ignored_after_first_match(): void
    {
        $tenantId = 'tenant-duplicate-' . bin2hex(random_bytes(4));

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Duplicate',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-MANUAL-002',
                    'period_label' => 'Juli 2026',
                    'status' => 'paid',
                    'currency' => 'IDR',
                    'invoice_total' => 99000,
                    'issued_at' => now()->subDay()->toIso8601String(),
                    'due_at' => now()->addDays(6)->toIso8601String(),
                    'paid_at' => now()->toIso8601String(),
                    'payment' => [
                        'method' => 'manual_transfer',
                        'status' => 'paid',
                        'manual_transfer' => [
                            'bank_name' => 'BCA',
                            'account_name' => 'AirCloud',
                            'account_number' => '1234567890',
                            'base_amount' => 99000,
                            'unique_code' => 222,
                            'expected_amount' => 99222,
                            'matched_by' => 'bca_email_unique_code',
                            'evidence' => [
                                'message_id' => 'msg-bca-dup',
                                'credit_amount' => 99222,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->withHeader('X-AirCloud-Webhook-Secret', 'test-evidence-secret')
            ->postJson('http://aircloud.biz.id/payments/manual-transfer/evidence/bca', [
                'message_id' => 'msg-bca-dup',
                'parsed_payload' => [
                    'account_number' => '1234567890',
                    'credit_amount' => 99222,
                ],
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'duplicate',
                'message_id' => 'msg-bca-dup',
            ]);

        $this->assertDatabaseHas('central_audit_logs', [
            'event_key' => 'manual_transfer.bca_duplicate',
            'target_type' => 'billing',
            'target_id' => 'msg-bca-dup',
        ]);
    }

    public function test_manual_transfer_service_skips_existing_expected_amounts(): void
    {
        $tenantId = 'tenant-existing-' . bin2hex(random_bytes(4));

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Existing',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-EXISTING-001',
                    'status' => 'issued',
                    'invoice_total' => 125000,
                    'issued_at' => now()->toIso8601String(),
                    'due_at' => now()->addDays(7)->toIso8601String(),
                    'payment' => [
                        'manual_transfer' => [
                            'expected_amount' => 125321,
                        ],
                    ],
                ],
            ],
        ]);

        $service = new ManualTransferService(new CentralAuditLogger());
        $allocation = $service->allocateUniqueCode(125000, [125322]);

        self::assertSame(125000, $allocation['base_amount']);
        self::assertNotSame(125321, $allocation['expected_amount']);
        self::assertNotSame(125322, $allocation['expected_amount']);
        self::assertGreaterThanOrEqual(101, $allocation['unique_code']);
        self::assertLessThanOrEqual(999, $allocation['unique_code']);
    }
}
