<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->timestamp('last_contacted_at')->nullable()->after('status');
            $table->timestamp('converted_at')->nullable()->after('last_contacted_at');
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropColumn(['last_contacted_at', 'converted_at']);
        });
    }
};
