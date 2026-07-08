<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_requests', function (Blueprint $table): void {
            $table->foreignUuid('supplier_id')
                ->nullable()
                ->after('destination_location_id')
                ->constrained('inventory_suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};

