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
            $table->boolean('use_job_title_master')
                ->default(false)
                ->after('theme_color');

            $table->json('role_label_overrides')
                ->nullable()
                ->after('use_job_title_master');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn(['use_job_title_master', 'role_label_overrides']);
        });
    }
};

