<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_area_id')->nullable()->constrained('service_areas')->nullOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable()->unique();
            $table->string('location_type', 30)->default('warehouse')->index();
            $table->string('manager_name', 100)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('sku', 40)->nullable()->unique();
            $table->string('name');
            $table->string('category', 50)->nullable()->index();
            $table->string('unit', 20)->default('pcs');
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('on_hand')->default(0);
            $table->timestamps();

            $table->unique(['inventory_location_id', 'inventory_item_id'], 'inventory_stock_location_item_unique');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignUuid('source_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('destination_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->uuid('created_by_user_id')->nullable();
            $table->string('movement_type', 20)->index();
            $table->unsignedInteger('quantity');
            $table->date('movement_date')->index();
            $table->string('reference_number', 50)->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'movement_date'], 'inventory_movement_item_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
    }
};

