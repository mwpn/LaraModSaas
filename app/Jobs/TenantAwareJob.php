<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

abstract class TenantAwareJob implements ShouldQueue
{
    use Queueable;

    public function tags(): array
    {
        if (! tenancy()->initialized) {
            return ['tenant:central'];
        }

        return ['tenant:' . tenant('id')];
    }
}
