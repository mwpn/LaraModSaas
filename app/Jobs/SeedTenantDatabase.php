<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SeedTenantDatabase
{
    public function __construct(protected TenantWithDatabase $tenant)
    {
    }

    public function handle(): void
    {
        tenancy()->initialize($this->tenant);

        try {
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
                '--database' => 'tenant',
                '--force' => true,
            ]);
        } finally {
            tenancy()->end();
        }
    }
}
