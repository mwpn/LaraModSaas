<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_reader_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_area_id')->constrained('service_areas')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('service_area_id', 'meter_reader_assignment_area_unique');
            $table->index(['user_id', 'is_active'], 'meter_reader_assignment_user_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_reader_assignments');
    }
};
