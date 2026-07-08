<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\TenantSubscriptionInvoice;
use App\Models\Tenant;
use App\Services\Central\TenantSubscriptionInvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantSubscriptionInvoiceAuditTest extends TestCase
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

        Schema::create('tenant_subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('invoice_number')->unique();
            $table->string('period_key')->nullable();
            $table->string('period_label')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('package_code_snapshot')->nullable();
            $table->string('status', 20)->default('issued');
            $table->string('currency', 10)->default('IDR');
            $table->unsignedBigInteger('setup_fee_total')->default(0);
            $table->unsignedBigInteger('monthly_total')->default(0);
            $table->unsignedBigInteger('invoice_total')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_meta')->nullable();
            $table->json('usage_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_subscription_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->index();
            $table->string('line_code')->nullable();
            $table->string('label')->nullable();
            $table->string('kind')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('amount')->default(0);
            $table->decimal('rate', 12, 2)->nullable();
            $table->unsignedBigInteger('line_total')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_detects_relational_shadow_and_can_cleanup_legacy_shadow(): void
    {
        $tenantId = 'tenant-audit-' . bin2hex(random_bytes(4));
        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Audit 1',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-AUDIT-001',
                    'period_key' => '2026-07-01-1m',
                    'period_label' => 'Juli 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 125000,
                    'monthly_total' => 125000,
                    'setup_fee' => 0,
                    'issued_at' => CarbonImmutable::parse('2026-07-01 00:00:00')->toIso8601String(),
                    'created_at' => CarbonImmutable::parse('2026-07-01 00:00:00')->toIso8601String(),
                    'payment' => [],
                ],
            ],
            'invoice_sequence' => 1,
            'first_invoice_issued_at' => CarbonImmutable::parse('2026-07-01 00:00:00')->toIso8601String(),
            'last_invoice_generated_at' => CarbonImmutable::parse('2026-07-01 00:00:00')->toIso8601String(),
            'last_invoice_status_updated_at' => CarbonImmutable::parse('2026-07-02 08:00:00')->toIso8601String(),
        ]);

        TenantSubscriptionInvoice::query()->create([
            'tenant_id' => $tenantId,
            'invoice_number' => 'INV-AUDIT-001',
            'period_key' => '2026-07-01-1m',
            'period_label' => 'Juli 2026',
            'status' => 'issued',
            'currency' => 'IDR',
            'invoice_total' => 125000,
            'monthly_total' => 125000,
            'setup_fee_total' => 0,
            'issued_at' => '2026-07-01 00:00:00',
            'created_at' => '2026-07-01 00:00:00',
            'updated_at' => '2026-07-02 08:00:00',
            'payment_meta' => [],
            'usage_snapshot' => [],
        ]);

        $service = app(TenantSubscriptionInvoiceService::class);
        $health = $service->invoiceHealthForTenant($tenant);

        self::assertSame('relational_shadow', $health['status']);
        self::assertTrue($health['cleanup_ready']);
        self::assertSame(['invoice_sequence', 'first_invoice_issued_at', 'last_invoice_generated_at', 'last_invoice_status_updated_at'], $health['legacy_meta_keys']);

        $cleanup = $service->cleanupLegacyShadow($tenant);

        self::assertTrue($cleanup['cleaned']);
        self::assertSame([], $tenant->fresh()->legacyBillingInvoices());
        self::assertNull(data_get($tenant->fresh(), 'invoice_sequence'));
        self::assertNull(data_get($tenant->fresh(), 'first_invoice_issued_at'));
    }

    public function test_it_detects_mismatch_between_legacy_and_relational_invoices(): void
    {
        $tenantId = 'tenant-audit-' . bin2hex(random_bytes(4));
        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Audit 2',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-AUDIT-002',
                    'period_key' => '2026-07-01-1m',
                    'period_label' => 'Juli 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 125000,
                    'monthly_total' => 125000,
                    'setup_fee' => 0,
                    'issued_at' => CarbonImmutable::parse('2026-07-01 00:00:00')->toIso8601String(),
                    'created_at' => CarbonImmutable::parse('2026-07-01 00:00:00')->toIso8601String(),
                    'payment' => [],
                ],
            ],
        ]);

        TenantSubscriptionInvoice::query()->create([
            'tenant_id' => $tenantId,
            'invoice_number' => 'INV-AUDIT-002',
            'period_key' => '2026-07-01-1m',
            'period_label' => 'Juli 2026',
            'status' => 'paid',
            'currency' => 'IDR',
            'invoice_total' => 125000,
            'monthly_total' => 125000,
            'setup_fee_total' => 0,
            'issued_at' => '2026-07-01 00:00:00',
            'paid_at' => '2026-07-02 10:00:00',
            'created_at' => '2026-07-01 00:00:00',
            'updated_at' => '2026-07-02 10:00:00',
            'payment_meta' => [],
            'usage_snapshot' => [],
        ]);

        $service = app(TenantSubscriptionInvoiceService::class);
        $health = $service->invoiceHealthForTenant($tenant);

        self::assertSame('mismatch', $health['status']);
        self::assertFalse($health['cleanup_ready']);
        self::assertSame(['INV-AUDIT-002'], $health['mismatch_invoice_numbers']);
    }

    public function test_it_repairs_legacy_only_tenant_to_relational_and_cleans_shadow(): void
    {
        $tenantId = 'tenant-audit-' . bin2hex(random_bytes(4));
        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Audit 3',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-AUDIT-003',
                    'period_key' => '2026-08-01-1m',
                    'period_label' => 'Agustus 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 150000,
                    'monthly_total' => 150000,
                    'setup_fee' => 0,
                    'issued_at' => CarbonImmutable::parse('2026-08-01 00:00:00')->toIso8601String(),
                    'created_at' => CarbonImmutable::parse('2026-08-01 00:00:00')->toIso8601String(),
                    'payment' => [],
                ],
            ],
            'invoice_sequence' => 3,
            'first_invoice_issued_at' => CarbonImmutable::parse('2026-08-01 00:00:00')->toIso8601String(),
        ]);

        $service = app(TenantSubscriptionInvoiceService::class);
        $result = $service->repairTenantInvoices($tenant, 'auto', true);

        self::assertSame('synced_and_cleaned', $result['action']);
        self::assertSame('legacy_backfilled_to_relational', $result['reason']);
        self::assertSame('relational_only', data_get($result, 'after.status'));
        self::assertCount(1, $service->invoicesForTenant($tenantId));
        self::assertSame([], $tenant->fresh()->legacyBillingInvoices());
    }

    public function test_auto_repair_skips_mismatch_until_mode_is_explicit(): void
    {
        $tenantId = 'tenant-audit-' . bin2hex(random_bytes(4));
        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Audit 4',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-AUDIT-004',
                    'period_key' => '2026-09-01-1m',
                    'period_label' => 'September 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 175000,
                    'monthly_total' => 175000,
                    'setup_fee' => 0,
                    'issued_at' => CarbonImmutable::parse('2026-09-01 00:00:00')->toIso8601String(),
                    'created_at' => CarbonImmutable::parse('2026-09-01 00:00:00')->toIso8601String(),
                    'payment' => [],
                ],
            ],
        ]);

        TenantSubscriptionInvoice::query()->create([
            'tenant_id' => $tenantId,
            'invoice_number' => 'INV-AUDIT-004',
            'period_key' => '2026-09-01-1m',
            'period_label' => 'September 2026',
            'status' => 'paid',
            'currency' => 'IDR',
            'invoice_total' => 175000,
            'monthly_total' => 175000,
            'setup_fee_total' => 0,
            'issued_at' => '2026-09-01 00:00:00',
            'paid_at' => '2026-09-02 10:00:00',
            'created_at' => '2026-09-01 00:00:00',
            'updated_at' => '2026-09-02 10:00:00',
            'payment_meta' => [],
            'usage_snapshot' => [],
        ]);

        $service = app(TenantSubscriptionInvoiceService::class);
        $autoResult = $service->repairTenantInvoices($tenant, 'auto', false);

        self::assertSame('skipped', $autoResult['action']);
        self::assertSame('manual_review_required', $autoResult['reason']);
        self::assertSame('mismatch', data_get($autoResult, 'after.status'));

        $forcedResult = $service->repairTenantInvoices($tenant->fresh(), 'legacy_to_relational', false);

        self::assertSame('synced', $forcedResult['action']);
        self::assertSame('legacy_backfilled_to_relational', $forcedResult['reason']);
        self::assertSame('relational_shadow', data_get($forcedResult, 'after.status'));
    }

    public function test_it_can_force_rebuild_legacy_shadow_from_relational_source_when_mismatch_happens(): void
    {
        $tenantId = 'tenant-audit-' . bin2hex(random_bytes(4));
        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Audit 5',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-AUDIT-005',
                    'period_key' => '2026-10-01-1m',
                    'period_label' => 'Oktober 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 200000,
                    'monthly_total' => 200000,
                    'setup_fee' => 0,
                    'issued_at' => CarbonImmutable::parse('2026-10-01 00:00:00')->toIso8601String(),
                    'created_at' => CarbonImmutable::parse('2026-10-01 00:00:00')->toIso8601String(),
                    'payment' => [],
                ],
            ],
        ]);

        TenantSubscriptionInvoice::query()->create([
            'tenant_id' => $tenantId,
            'invoice_number' => 'INV-AUDIT-005',
            'period_key' => '2026-10-01-1m',
            'period_label' => 'Oktober 2026',
            'status' => 'paid',
            'currency' => 'IDR',
            'invoice_total' => 200000,
            'monthly_total' => 200000,
            'setup_fee_total' => 0,
            'issued_at' => '2026-10-01 00:00:00',
            'paid_at' => '2026-10-02 07:00:00',
            'created_at' => '2026-10-01 00:00:00',
            'updated_at' => '2026-10-02 07:00:00',
            'payment_meta' => [],
            'usage_snapshot' => [],
        ]);

        $service = app(TenantSubscriptionInvoiceService::class);
        $result = $service->repairTenantInvoices($tenant, 'relational_to_legacy', false);

        self::assertSame('synced', $result['action']);
        self::assertSame('relational_shadow_rebuilt', $result['reason']);
        self::assertSame('relational_shadow', data_get($result, 'after.status'));
        self::assertSame('paid', data_get($tenant->fresh()->legacyBillingInvoices(), '0.status'));
    }
}
