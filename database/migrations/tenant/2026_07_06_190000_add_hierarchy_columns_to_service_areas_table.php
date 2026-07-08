<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_areas')) {
            return;
        }

        $addAreaType = ! Schema::hasColumn('service_areas', 'area_type');
        $addParentId = ! Schema::hasColumn('service_areas', 'parent_id');

        if (! $addAreaType && ! $addParentId) {
            return;
        }

        Schema::table('service_areas', function (Blueprint $table) use ($addAreaType, $addParentId): void {
            if ($addAreaType) {
                $table->string('area_type', 20)->default('rayon')->after('code')->index();
            }

            if ($addParentId) {
                $table->foreignUuid('parent_id')->nullable()->after('area_type')->constrained('service_areas')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_areas')) {
            return;
        }

        $dropParentId = Schema::hasColumn('service_areas', 'parent_id');
        $dropAreaType = Schema::hasColumn('service_areas', 'area_type');

        if (! $dropParentId && ! $dropAreaType) {
            return;
        }

        Schema::table('service_areas', function (Blueprint $table) use ($dropParentId, $dropAreaType): void {
            if ($dropParentId) {
                $table->dropConstrainedForeignId('parent_id');
            }

            if ($dropAreaType) {
                $table->dropColumn('area_type');
            }
        });
    }
};
