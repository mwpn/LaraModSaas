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
            $table->unsignedBigInteger('billing_installation_fee_amount')
                ->default(0)
                ->after('billing_reactivation_default_allow_installment');

            $table->boolean('billing_installation_allow_installment')
                ->default(false)
                ->after('billing_installation_fee_amount');

            $table->unsignedInteger('billing_installation_default_installment_months')
                ->default(3)
                ->after('billing_installation_allow_installment');

            $table->boolean('billing_installation_promo_enabled')
                ->default(false)
                ->after('billing_installation_default_installment_months');

            $table->unsignedBigInteger('billing_installation_promo_discount_amount')
                ->default(0)
                ->after('billing_installation_promo_enabled');

            $table->date('billing_installation_promo_start_date')
                ->nullable()
                ->after('billing_installation_promo_discount_amount');

            $table->date('billing_installation_promo_end_date')
                ->nullable()
                ->after('billing_installation_promo_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_installation_fee_amount',
                'billing_installation_allow_installment',
                'billing_installation_default_installment_months',
                'billing_installation_promo_enabled',
                'billing_installation_promo_discount_amount',
                'billing_installation_promo_start_date',
                'billing_installation_promo_end_date',
            ]);
        });
    }
};

