<?php

declare(strict_types=1);

use App\Models\CentralSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_settings')) {
            Schema::create('central_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->text('value');
            });
        }

        DB::table('central_settings')->updateOrInsert(
            ['key' => CentralSetting::PLATFORM_SAAS_TYPE_KEY],
            ['value' => 'universal'],
        );

        DB::table('central_settings')->updateOrInsert(
            ['key' => CentralSetting::ACTIVE_MODULES_KEY],
            ['value' => json_encode(['BaseFeature'], JSON_THROW_ON_ERROR)],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('central_settings');
    }
};
