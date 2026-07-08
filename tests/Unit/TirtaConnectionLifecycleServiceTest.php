<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tirta\BillingInvoice;
use App\Models\Tirta\ServiceConnection;
use App\Services\Tirta\TirtaConnectionLifecycleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\BaseFeature\Models\TenantSetting;
use Tests\TestCase;

class TirtaConnectionLifecycleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('service_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->nullable();
            $table->uuid('service_area_id')->nullable();
            $table->uuid('service_category_id')->nullable();
            $table->uuid('tariff_scheme_id')->nullable();
            $table->string('service_number')->nullable();
            $table->string('service_label')->nullable();
            $table->string('meter_number')->nullable();
            $table->text('service_address')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('installed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->text('disconnected_reason')->nullable();
            $table->timestamp('reactivated_at')->nullable();
            $table->timestamp('reactivation_requested_at')->nullable();
            $table->uuid('reactivation_activation_invoice_id')->nullable();
            $table->boolean('reactivation_allow_installment')->default(true);
            $table->timestamps();
        });

        Schema::create('billing_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('meter_reading_period_id')->nullable();
            $table->string('name');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('billing_period_id')->nullable()->index();
            $table->uuid('meter_reading_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->uuid('service_connection_id')->nullable()->index();
            $table->uuid('tariff_scheme_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status', 20)->default('issued')->index();
            $table->unsignedInteger('usage_volume')->default(0);
            $table->unsignedBigInteger('water_charge_total')->default(0);
            $table->unsignedBigInteger('minimum_charge_applied')->default(0);
            $table->unsignedBigInteger('admin_fee_total')->default(0);
            $table->unsignedBigInteger('penalty_total')->default(0);
            $table->unsignedBigInteger('invoice_total')->default(0);
            $table->date('due_date')->nullable()->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('calculation_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('billing_invoice_id')->index();
            $table->string('line_type', 30)->nullable();
            $table->string('label')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->json('meta')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('billing_invoice_id')->index();
            $table->string('payment_method', 30)->nullable();
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_disconnects_connection_after_three_overdue_invoices(): void
    {
        $service = new TirtaConnectionLifecycleService();
        $setting = (new TenantSetting())->forceFill([
            'billing_disconnect_after_months' => 3,
        ]);
        $asOf = Carbon::parse('2026-07-06 10:00:00');

        $eligibleConnection = ServiceConnection::query()->create([
            'service_number' => 'SRV-001',
            'status' => 'active',
        ]);
        $notEligibleConnection = ServiceConnection::query()->create([
            'service_number' => 'SRV-002',
            'status' => 'active',
        ]);

        BillingInvoice::query()->create([
            'service_connection_id' => $eligibleConnection->id,
            'invoice_number' => 'INV-EL-1',
            'status' => 'issued',
            'due_date' => Carbon::parse('2026-03-01'),
            'invoice_total' => 100000,
        ]);
        BillingInvoice::query()->create([
            'service_connection_id' => $eligibleConnection->id,
            'invoice_number' => 'INV-EL-2',
            'status' => 'issued',
            'due_date' => Carbon::parse('2026-04-01'),
            'invoice_total' => 100000,
        ]);
        BillingInvoice::query()->create([
            'service_connection_id' => $eligibleConnection->id,
            'invoice_number' => 'INV-EL-3',
            'status' => 'issued',
            'due_date' => Carbon::parse('2026-05-01'),
            'invoice_total' => 100000,
        ]);

        BillingInvoice::query()->create([
            'service_connection_id' => $notEligibleConnection->id,
            'invoice_number' => 'INV-NEL-1',
            'status' => 'issued',
            'due_date' => Carbon::parse('2026-05-01'),
            'invoice_total' => 100000,
        ]);
        BillingInvoice::query()->create([
            'service_connection_id' => $notEligibleConnection->id,
            'invoice_number' => 'INV-NEL-2',
            'status' => 'issued',
            'due_date' => Carbon::parse('2026-06-01'),
            'invoice_total' => 100000,
        ]);

        $report = $service->auditAndDisconnectOverdueConnections($setting, $asOf);

        self::assertSame(3, $report['threshold_months']);
        self::assertSame(1, $report['scanned']);
        self::assertSame(1, $report['disconnected']);

        $eligibleConnection->refresh();
        $notEligibleConnection->refresh();

        self::assertSame('disconnected', $eligibleConnection->status);
        self::assertSame('active', $notEligibleConnection->status);
    }

    public function test_it_creates_activation_invoice_and_marks_connection_as_reactivation_requested(): void
    {
        $service = new TirtaConnectionLifecycleService();
        $setting = (new TenantSetting())->forceFill([
            'billing_reactivation_fee_amount' => 50000,
        ]);

        $connection = ServiceConnection::query()->create([
            'service_number' => 'SRV-003',
            'status' => 'disconnected',
        ]);

        $invoice = $service->requestReactivation($connection, $setting, true, Carbon::parse('2026-07-06 09:00:00'));

        self::assertSame('issued', $invoice->status);
        self::assertSame(50000, $invoice->invoice_total);
        self::assertSame('reactivation', data_get($invoice->calculation_snapshot, 'invoice_type'));
        self::assertTrue((bool) data_get($invoice->calculation_snapshot, 'allow_installment'));
        self::assertTrue($invoice->lines()->where('line_type', 'activation_fee')->exists());

        $connection->refresh();
        self::assertSame($invoice->id, $connection->reactivation_activation_invoice_id);
        self::assertTrue((bool) $connection->reactivation_allow_installment);
        self::assertNotNull($connection->reactivation_requested_at);

        $sameInvoice = $service->requestReactivation($connection, $setting, true, Carbon::parse('2026-07-06 10:00:00'));
        self::assertSame($invoice->id, $sameInvoice->id);
    }

    public function test_it_reactivates_after_activation_paid_when_installment_is_allowed(): void
    {
        $service = new TirtaConnectionLifecycleService();
        $setting = (new TenantSetting())->forceFill([
            'billing_reactivation_fee_amount' => 50000,
        ]);

        $connection = ServiceConnection::query()->create([
            'service_number' => 'SRV-004',
            'status' => 'disconnected',
        ]);

        $activationInvoice = $service->requestReactivation($connection, $setting, true, Carbon::parse('2026-07-06 09:00:00'));
        $activationInvoice->forceFill(['status' => 'paid'])->save();

        BillingInvoice::query()->create([
            'service_connection_id' => $connection->id,
            'invoice_number' => 'INV-ARREARS-1',
            'status' => 'issued',
            'due_date' => Carbon::parse('2026-06-01'),
            'invoice_total' => 100000,
        ]);

        $reactivated = $service->finalizeReactivationIfEligible($connection, Carbon::parse('2026-07-06 11:00:00'));

        self::assertTrue($reactivated);

        $connection->refresh();
        self::assertSame('active', $connection->status);
        self::assertNull($connection->reactivation_activation_invoice_id);
        self::assertNotNull($connection->reactivated_at);
    }

    public function test_it_requires_all_arrears_paid_when_installment_is_not_allowed(): void
    {
        $service = new TirtaConnectionLifecycleService();
        $setting = (new TenantSetting())->forceFill([
            'billing_reactivation_fee_amount' => 50000,
        ]);

        $connection = ServiceConnection::query()->create([
            'service_number' => 'SRV-005',
            'status' => 'disconnected',
        ]);

        $activationInvoice = $service->requestReactivation($connection, $setting, false, Carbon::parse('2026-07-06 09:00:00'));
        $activationInvoice->forceFill(['status' => 'paid'])->save();

        $arrears = BillingInvoice::query()->create([
            'service_connection_id' => $connection->id,
            'invoice_number' => 'INV-ARREARS-2',
            'status' => 'issued',
            'due_date' => Carbon::parse('2026-06-01'),
            'invoice_total' => 100000,
        ]);

        $reactivated = $service->finalizeReactivationIfEligible($connection, Carbon::parse('2026-07-06 11:00:00'));
        self::assertFalse($reactivated);

        $connection->refresh();
        self::assertSame('disconnected', $connection->status);

        $arrears->forceFill(['status' => 'paid'])->save();
        $reactivatedAfterArrearsPaid = $service->finalizeReactivationIfEligible($connection, Carbon::parse('2026-07-06 12:00:00'));
        self::assertTrue($reactivatedAfterArrearsPaid);

        $connection->refresh();
        self::assertSame('active', $connection->status);
    }
}
