<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CentralSetting;
use Illuminate\Database\Seeder;

class CentralSettingSeeder extends Seeder
{
    public function run(): void
    {
        CentralSetting::setPlatformSaasType('universal');
        CentralSetting::syncActiveModulesForPlatform('universal');
    }
}
