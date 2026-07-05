<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('level', 16)->default('info');
            $table->string('event_key', 120);
            $table->string('target_type', 80)->nullable();
            $table->string('target_id', 120)->nullable();
            $table->string('summary');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['level', 'created_at']);
            $table->index(['event_key', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_audit_logs');
    }
};
