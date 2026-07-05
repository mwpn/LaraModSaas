<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $addPhoneNumber = ! Schema::hasColumn('users', 'phone_number');
        $addAvatarPath = ! Schema::hasColumn('users', 'avatar_path');

        if (! $addPhoneNumber && ! $addAvatarPath) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($addPhoneNumber, $addAvatarPath): void {
            if ($addPhoneNumber) {
                $table->string('phone_number', 32)->nullable()->after('email');
            }

            if ($addAvatarPath) {
                $table->string('avatar_path')->nullable()->after('phone_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $dropPhoneNumber = Schema::hasColumn('users', 'phone_number');
        $dropAvatarPath = Schema::hasColumn('users', 'avatar_path');

        if (! $dropPhoneNumber && ! $dropAvatarPath) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($dropPhoneNumber, $dropAvatarPath): void {
            if ($dropAvatarPath) {
                $table->dropColumn('avatar_path');
            }

            if ($dropPhoneNumber) {
                $table->dropColumn('phone_number');
            }
        });
    }
};
