<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'service_area_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('service_area_id')
                ->nullable()
                ->after('role_id')
                ->constrained('service_areas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'service_area_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_area_id');
        });
    }
};
