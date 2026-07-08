<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_connections', function (Blueprint $table): void {
            $table->timestamp('disconnected_at')->nullable()->after('installed_at');
            $table->string('disconnected_reason', 160)->nullable()->after('disconnected_at');
            $table->timestamp('reactivated_at')->nullable()->after('disconnected_reason');
            $table->timestamp('reactivation_requested_at')->nullable()->after('reactivated_at');
            $table->uuid('reactivation_activation_invoice_id')->nullable()->after('reactivation_requested_at');
            $table->boolean('reactivation_allow_installment')->default(true)->after('reactivation_activation_invoice_id');

            $table->index(['status', 'disconnected_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_connections', function (Blueprint $table): void {
            $table->dropIndex(['status', 'disconnected_at']);
            $table->dropColumn([
                'disconnected_at',
                'disconnected_reason',
                'reactivated_at',
                'reactivation_requested_at',
                'reactivation_activation_invoice_id',
                'reactivation_allow_installment',
            ]);
        });
    }
};

