<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->string('visit_status', 30)
                ->default('read')
                ->after('reading_status')
                ->index();
            $table->string('follow_up_action', 30)
                ->nullable()
                ->after('visit_status');
            $table->string('review_status', 30)
                ->default('auto_pass')
                ->after('follow_up_action')
                ->index();
            $table->json('review_flags')
                ->nullable()
                ->after('review_status');
            $table->string('customer_notification_status', 30)
                ->default('not_applicable')
                ->after('review_flags')
                ->index();
            $table->json('customer_notification_channels')
                ->nullable()
                ->after('customer_notification_status');
            $table->text('customer_notification_message')
                ->nullable()
                ->after('customer_notification_channels');
            $table->timestamp('customer_notified_at')
                ->nullable()
                ->after('customer_notification_message');
        });
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table): void {
            $table->dropColumn([
                'visit_status',
                'follow_up_action',
                'review_status',
                'review_flags',
                'customer_notification_status',
                'customer_notification_channels',
                'customer_notification_message',
                'customer_notified_at',
            ]);
        });
    }
};
