<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('request_number', 40)->unique();
            $table->string('request_type', 30)->index();
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignUuid('service_area_id')->nullable()->constrained('service_areas')->nullOnDelete();
            $table->foreignUuid('service_connection_id')->nullable()->constrained('service_connections')->nullOnDelete();
            $table->foreignUuid('source_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignUuid('destination_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->uuid('requested_by_user_id')->nullable()->index();
            $table->uuid('approved_by_user_id')->nullable()->index();
            $table->uuid('completed_by_user_id')->nullable()->index();
            $table->string('title', 150);
            $table->string('reference_number', 50)->nullable()->index();
            $table->text('notes')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamp('requested_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_request_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_request_id')->constrained('inventory_requests')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_approved')->default(0);
            $table->unsignedInteger('quantity_completed')->default(0);
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['inventory_request_id', 'inventory_item_id'], 'inventory_request_line_request_item_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_request_lines');
        Schema::dropIfExists('inventory_requests');
    }
};

