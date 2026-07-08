<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_reading_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
        });

        Schema::create('meter_readings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('meter_reading_period_id')->constrained('meter_reading_periods')->cascadeOnDelete();
            $table->foreignUuid('service_connection_id')->constrained('service_connections')->cascadeOnDelete();
            $table->unsignedBigInteger('previous_reading')->default(0);
            $table->unsignedBigInteger('current_reading')->default(0);
            $table->unsignedBigInteger('usage_volume')->default(0);
            $table->string('reading_status', 20)->default('normal')->index();
            $table->string('reader_name')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->text('anomaly_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['meter_reading_period_id', 'service_connection_id'], 'meter_reading_period_connection_unique');
            $table->index(['service_connection_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('meter_reading_periods');
    }
};
