<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('meter_reading_period_id')->nullable()->constrained('meter_reading_periods')->nullOnDelete();
            $table->string('name');
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['meter_reading_period_id']);
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_period_id')->constrained('billing_periods')->cascadeOnDelete();
            $table->foreignUuid('meter_reading_id')->nullable()->constrained('meter_readings')->nullOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('service_connection_id')->constrained('service_connections')->cascadeOnDelete();
            $table->foreignUuid('tariff_scheme_id')->nullable()->constrained('tariff_schemes')->nullOnDelete();
            $table->string('invoice_number', 60)->unique();
            $table->string('status', 20)->default('issued')->index();
            $table->unsignedBigInteger('usage_volume')->default(0);
            $table->unsignedBigInteger('water_charge_total')->default(0);
            $table->unsignedBigInteger('minimum_charge_applied')->default(0);
            $table->unsignedBigInteger('admin_fee_total')->default(0);
            $table->unsignedBigInteger('invoice_total')->default(0);
            $table->date('due_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('calculation_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['billing_period_id', 'service_connection_id'], 'billing_period_connection_unique');
            $table->index(['service_connection_id', 'status']);
        });

        Schema::create('billing_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('line_type', 40)->index();
            $table->string('label');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->json('meta')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_lines');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_periods');
    }
};
