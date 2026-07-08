<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->unsignedInteger('billing_disconnect_after_months')
                ->default(3)
                ->after('billing_penalty_auto_post_on_payment');

            $table->unsignedBigInteger('billing_reactivation_fee_amount')
                ->default(0)
                ->after('billing_disconnect_after_months');

            $table->boolean('billing_reactivation_default_allow_installment')
                ->default(true)
                ->after('billing_reactivation_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_disconnect_after_months',
                'billing_reactivation_fee_amount',
                'billing_reactivation_default_allow_installment',
            ]);
        });
    }
};

