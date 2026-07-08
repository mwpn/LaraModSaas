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
            $table->boolean('billing_penalty_enabled')->default(false)->after('theme_color');
            $table->string('billing_penalty_type', 20)->default('fixed')->after('billing_penalty_enabled');
            $table->string('billing_penalty_frequency', 20)->default('daily')->after('billing_penalty_type');
            $table->string('billing_penalty_base', 30)->default('outstanding_total')->after('billing_penalty_frequency');
            $table->decimal('billing_penalty_value', 12, 4)->default(0)->after('billing_penalty_base');
            $table->unsignedInteger('billing_penalty_grace_days')->default(0)->after('billing_penalty_value');
            $table->unsignedBigInteger('billing_penalty_max_amount')->nullable()->after('billing_penalty_grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_penalty_enabled',
                'billing_penalty_type',
                'billing_penalty_frequency',
                'billing_penalty_base',
                'billing_penalty_value',
                'billing_penalty_grace_days',
                'billing_penalty_max_amount',
            ]);
        });
    }
};
