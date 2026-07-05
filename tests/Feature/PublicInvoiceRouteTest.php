<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class PublicInvoiceRouteTest extends TestCase
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

        Schema::create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->timestamps();
        });
    }

    public function test_public_invoice_show_route_returns_invoice_page(): void
    {
        $tenantId = 'tenant-demo-' . bin2hex(random_bytes(4));

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Demo',
            'billing_invoices' => [
                [
                    'invoice_number' => 'INV-001',
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
                            'base_amount' => 125000,
                            'unique_code' => 321,
                            'expected_amount' => 125321,
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->get('http://aircloud.biz.id/pay/' . $tenantId . '/INV-001');

        $response->assertOk();
        $response->assertSee('Tenant Demo');
        $response->assertSee('INV-001');
        $response->assertSee('Rp 125.000');
        $response->assertSee('125.321');
        $response->assertSee('321');
    }

    public function test_public_invoice_show_route_returns_404_for_missing_invoice(): void
    {
        $tenantId = 'tenant-missing-' . bin2hex(random_bytes(4));

        Tenant::query()->create([
            'id' => $tenantId,
            'name' => 'Tenant Demo',
            'billing_invoices' => [],
        ]);

        $response = $this->get('http://aircloud.biz.id/pay/' . $tenantId . '/INV-404');

        $response->assertNotFound();
    }
}
