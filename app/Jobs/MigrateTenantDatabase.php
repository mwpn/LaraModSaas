<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class MigrateTenantDatabase
{
    public function __construct(protected TenantWithDatabase $tenant)
    {
    }

    public function handle(): void
    {
        tenancy()->initialize($this->tenant);

        try {
            Artisan::call('migrate', config('tenancy.migration_parameters'));
        } finally {
            tenancy()->end();
        }
    }
}
