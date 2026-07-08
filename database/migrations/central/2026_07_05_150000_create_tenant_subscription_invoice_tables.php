<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('invoice_number')->unique();
            $table->string('period_key')->nullable()->index();
            $table->string('period_label')->nullable();
            $table->foreignId('package_id')
                ->nullable()
                ->constrained('subscription_packages')
                ->nullOnDelete();
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

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_subscription_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')
                ->constrained('tenant_subscription_invoices')
                ->cascadeOnDelete();
            $table->string('line_code')->nullable();
            $table->string('label');
            $table->string('kind', 30)->default('flat');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->unsignedBigInteger('amount')->default(0);
            $table->decimal('rate', 12, 2)->nullable();
            $table->unsignedBigInteger('line_total')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_invoice_lines');
        Schema::dropIfExists('tenant_subscription_invoices');
    }
};
