<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_areas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 20)->nullable()->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('service_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 20)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('tariff_schemes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->string('name');
            $table->string('calculation_mode', 20)->default('flat')->index();
            $table->decimal('base_price_per_m3', 14, 2)->nullable();
            $table->decimal('minimum_charge', 14, 2)->default(0);
            $table->decimal('admin_fee', 14, 2)->default(0);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_area_id')->nullable()->constrained('service_areas')->nullOnDelete();
            $table->string('name');
            $table->text('address');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('service_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('service_area_id')->nullable()->constrained('service_areas')->nullOnDelete();
            $table->foreignUuid('service_category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->foreignUuid('tariff_scheme_id')->nullable()->constrained('tariff_schemes')->nullOnDelete();
            $table->string('service_number', 6)->unique();
            $table->string('service_label')->nullable();
            $table->string('meter_number', 50)->nullable();
            $table->text('service_address')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->date('installed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tariff_scheme_tiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tariff_scheme_id')->constrained('tariff_schemes')->cascadeOnDelete();
            $table->unsignedInteger('start_usage');
            $table->unsignedInteger('end_usage')->nullable();
            $table->string('charge_type', 20)->default('per_m3');
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tariff_scheme_id', 'start_usage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_scheme_tiers');
        Schema::dropIfExists('service_connections');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('tariff_schemes');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('service_areas');
    }
};
