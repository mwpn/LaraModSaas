<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->string('evidence_photo_path')->nullable()->after('recorded_at');
            $table->decimal('recorded_latitude', 10, 7)->nullable()->after('evidence_photo_path');
            $table->decimal('recorded_longitude', 10, 7)->nullable()->after('recorded_latitude');
            $table->index(['recorded_latitude', 'recorded_longitude'], 'meter_readings_coordinates_index');
        });
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->dropIndex('meter_readings_coordinates_index');
            $table->dropColumn([
                'evidence_photo_path',
                'recorded_latitude',
                'recorded_longitude',
            ]);
        });
    }
};

