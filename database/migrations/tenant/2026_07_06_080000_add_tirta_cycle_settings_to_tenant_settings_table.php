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
            $table->unsignedTinyInteger('meter_reading_window_start_day')->default(25)->after('theme_color');
            $table->unsignedTinyInteger('meter_reading_window_end_day')->default(30)->after('meter_reading_window_start_day');
            $table->unsignedSmallInteger('billing_publish_offset_days')->default(1)->after('meter_reading_window_end_day');
            $table->unsignedSmallInteger('billing_due_offset_days')->default(10)->after('billing_publish_offset_days');
            $table->string('billing_penalty_start_basis', 20)->default('due_date')->after('billing_due_offset_days');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'meter_reading_window_start_day',
                'meter_reading_window_end_day',
                'billing_publish_offset_days',
                'billing_due_offset_days',
                'billing_penalty_start_basis',
            ]);
        });
    }
};
