<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'job_title')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('job_title', 120)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'job_title')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('job_title');
        });
    }
};
