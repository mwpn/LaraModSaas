<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->string('platform_type')->index();
            $table->foreignId('package_id')
                ->nullable()
                ->constrained('subscription_packages')
                ->nullOnDelete();
            $table->string('package_code_snapshot')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->json('billing_usage_snapshot')->nullable();
            $table->unsignedInteger('billing_grace_days')->default(3);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['platform_type', 'status']);
        });

        Schema::create('tenant_module_states', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('module_id')
                ->constrained('platform_module_catalog')
                ->cascadeOnDelete();
            $table->string('status', 20)->default('disabled');
            $table->string('enabled_source', 30)->nullable();
            $table->string('reason_code')->nullable();
            $table->boolean('is_allowed')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_id']);
            $table->index(['tenant_id', 'status', 'is_allowed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_states');
        Schema::dropIfExists('tenant_subscriptions');
    }
};
