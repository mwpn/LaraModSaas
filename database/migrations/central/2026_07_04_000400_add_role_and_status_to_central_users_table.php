<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        $addRoleId = Schema::hasTable('users') && ! Schema::hasColumn('users', 'role_id');
        $addIsActive = Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_active');

        if ($addRoleId || $addIsActive) {
            Schema::table('users', function (Blueprint $table) use ($addRoleId, $addIsActive): void {
                if ($addRoleId) {
                    $table->uuid('role_id')->nullable()->index();
                }

                if ($addIsActive) {
                    $table->boolean('is_active')->default(true)->index();
                }
            });
        }

        foreach ([
            ['name' => 'Owner', 'slug' => 'owner'],
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Staff', 'slug' => 'staff'],
        ] as $role) {
            $exists = DB::table('roles')->where('slug', $role['slug'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('roles')->insert([
                'id' => (string) Str::uuid(),
                'name' => $role['name'],
                'slug' => $role['slug'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('users')) {
            return;
        }

        $ownerRoleId = DB::table('roles')->where('slug', 'owner')->value('id');
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');

        DB::table('users')
            ->whereNull('is_active')
            ->update(['is_active' => true]);

        $firstUserId = DB::table('users')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        if ($firstUserId && $ownerRoleId) {
            DB::table('users')
                ->where('id', $firstUserId)
                ->whereNull('role_id')
                ->update(['role_id' => $ownerRoleId]);
        }

        if ($adminRoleId) {
            DB::table('users')
                ->whereNull('role_id')
                ->update(['role_id' => $adminRoleId]);
        }
    }

    public function down(): void
    {
        $dropRoleId = Schema::hasTable('users') && Schema::hasColumn('users', 'role_id');
        $dropIsActive = Schema::hasTable('users') && Schema::hasColumn('users', 'is_active');

        if ($dropRoleId || $dropIsActive) {
            Schema::table('users', function (Blueprint $table) use ($dropRoleId, $dropIsActive): void {
                if ($dropRoleId) {
                    $table->dropColumn('role_id');
                }

                if ($dropIsActive) {
                    $table->dropColumn('is_active');
                }
            });
        }
    }
};
