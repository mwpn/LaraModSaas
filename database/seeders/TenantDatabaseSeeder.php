<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CentralSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\BaseFeature\Models\TenantSetting;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $blueprint = CentralSetting::platformBlueprint((string) (tenant('saas_type') ?? 'universal'));

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

        TenantSetting::query()->firstOrCreate(
            [],
            [
                'brand_name' => (string) (tenant('name') ?? tenant('id') ?? config('app.name')),
                'description' => $blueprint['tenant_description'],
                'theme_color' => $blueprint['theme_color'],
            ]
        );
    }
}
