<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->boolean('is_serialized')
                ->default(false)
                ->after('minimum_stock')
                ->index();
        });

        Schema::table('service_connections', function (Blueprint $table): void {
            $table->string('installation_workflow_status', 40)
                ->nullable()
                ->after('status')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('service_connections', function (Blueprint $table): void {
            $table->dropColumn('installation_workflow_status');
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn('is_serialized');
        });
    }
};

