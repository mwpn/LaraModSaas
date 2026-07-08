<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('payment_method', 30)->default('cash')->index();
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamp('paid_at')->nullable()->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->string('received_by', 120)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['billing_invoice_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
