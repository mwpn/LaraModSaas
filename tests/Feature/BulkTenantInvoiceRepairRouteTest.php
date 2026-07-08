<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\TenantSubscriptionInvoice;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BulkTenantInvoiceRepairRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->uuid('role_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

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

    public function test_owner_can_bulk_repair_filtered_legacy_only_tenants(): void
    {
        $owner = $this->ownerUser();
        $legacyOnlyTenantId = 'tenant-bulk-' . bin2hex(random_bytes(4));
        $relationalOnlyTenantId = 'tenant-bulk-' . bin2hex(random_bytes(4));

        $targetTenant = Tenant::query()->create([
            'id' => $legacyOnlyTenantId,
            'name' => 'Tenant Bulk 1',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-BULK-001',
                    'period_key' => '2026-11-01-1m',
                    'period_label' => 'November 2026',
                    'status' => 'issued',
                    'currency' => 'IDR',
                    'invoice_total' => 150000,
                    'monthly_total' => 150000,
                    'setup_fee' => 0,
                    'issued_at' => CarbonImmutable::parse('2026-11-01 00:00:00')->toIso8601String(),
                    'created_at' => CarbonImmutable::parse('2026-11-01 00:00:00')->toIso8601String(),
                    'payment' => [],
                ],
            ],
            'invoice_sequence' => 1,
        ]);

        TenantSubscriptionInvoice::query()->create([
            'tenant_id' => $relationalOnlyTenantId,
            'invoice_number' => 'INV-BULK-' . strtoupper(bin2hex(random_bytes(3))),
            'period_key' => '2026-11-01-1m',
            'period_label' => 'November 2026',
            'status' => 'issued',
            'currency' => 'IDR',
            'invoice_total' => 175000,
            'monthly_total' => 175000,
            'setup_fee_total' => 0,
            'issued_at' => '2026-11-01 00:00:00',
            'created_at' => '2026-11-01 00:00:00',
            'updated_at' => '2026-11-01 00:00:00',
            'payment_meta' => [],
            'usage_snapshot' => [],
        ]);

        Tenant::query()->create([
            'id' => $relationalOnlyTenantId,
            'name' => 'Tenant Bulk 2',
        ]);

        $response = $this
            ->from('http://aircloud.biz.id/super-admin/tenants?invoice_health=legacy_only')
            ->actingAs($owner, 'central')
            ->post('http://aircloud.biz.id/super-admin/tenants/billing/repair-bulk', [
                'invoice_health' => 'legacy_only',
            ]);

        $response->assertRedirect('http://aircloud.biz.id/super-admin/tenants?invoice_health=legacy_only');
        $response->assertSessionHas('status');

        self::assertCount(1, TenantSubscriptionInvoice::query()->where('tenant_id', $targetTenant->id)->get());
        self::assertSame([], $targetTenant->fresh()->legacyBillingInvoices());
        self::assertSame([], Tenant::query()->findOrFail($relationalOnlyTenantId)->legacyBillingInvoices());
    }

    protected function ownerUser(): User
    {
        $role = Role::query()->create([
            'id' => 'role-owner',
            'name' => 'Owner',
            'slug' => 'owner',
        ]);

        $user = User::query()->create([
            'name' => 'Central Owner',
            'email' => 'bulk-owner@aircloud.test',
            'password' => Hash::make('Secret123'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return $user->setRelation('role', $role);
    }
}
