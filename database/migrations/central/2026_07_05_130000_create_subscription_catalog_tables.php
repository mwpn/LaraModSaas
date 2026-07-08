<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_module_catalog', function (Blueprint $table): void {
            $table->id();
            $table->string('module_code')->unique();
            $table->string('module_name')->unique();
            $table->string('platform_type')->index();
            $table->string('domain_group')->nullable();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_default_enabled')->default(false);
            $table->boolean('is_addon')->default(false);
            $table->boolean('subscription_visible')->default(true);
            $table->json('depends_on')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['platform_type', 'is_active']);
        });

        Schema::create('subscription_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('package_code')->unique();
            $table->string('platform_type')->index();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('billing_cycle', 20)->default('monthly');
            $table->unsignedBigInteger('base_price')->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedInteger('grace_days')->default(3);
            $table->json('billing_components')->nullable();
            $table->timestamps();

            $table->index(['platform_type', 'is_enabled']);
            $table->index(['platform_type', 'is_default']);
        });

        Schema::create('subscription_package_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')
                ->constrained('subscription_packages')
                ->cascadeOnDelete();
            $table->foreignId('module_id')
                ->constrained('platform_module_catalog')
                ->cascadeOnDelete();
            $table->string('access_mode', 20)->default('included');
            $table->boolean('is_enabled_by_default')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'module_id']);
        });

        Schema::create('subscription_package_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')
                ->constrained('subscription_packages')
                ->cascadeOnDelete();
            $table->string('feature_code');
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'feature_code']);
        });

        Schema::create('subscription_package_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')
                ->constrained('subscription_packages')
                ->cascadeOnDelete();
            $table->string('limit_code');
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['package_id', 'limit_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_package_limits');
        Schema::dropIfExists('subscription_package_features');
        Schema::dropIfExists('subscription_package_modules');
        Schema::dropIfExists('subscription_packages');
        Schema::dropIfExists('platform_module_catalog');
    }
};
