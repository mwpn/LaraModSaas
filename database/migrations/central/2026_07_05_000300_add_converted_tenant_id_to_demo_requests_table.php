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
            $table->string('converted_tenant_id')->nullable()->after('converted_at');
            $table->index('converted_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropIndex(['converted_tenant_id']);
            $table->dropColumn('converted_tenant_id');
        });
    }
};
