<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\Central\SuperAdminTenantController;
use App\Services\Central\CentralAuditLogger;

class RunBillingReminderScanJob extends CentralAwareJob
{
    public function handle(SuperAdminTenantController $controller, CentralAuditLogger $auditLogger): void
    {
        $result = $controller->scanBillingReminders('queue');

        $auditLogger->info(
            'billing.reminder_scan_queued',
            'Billing reminder scan dijalankan dari queue.',
            [
                'target_type' => 'billing',
                'target_id' => 'reminder-scan',
                'meta' => [
                    'overdue_count' => (int) ($result['overdue_count'] ?? 0),
                    'expiring_soon_count' => (int) ($result['expiring_soon_count'] ?? 0),
                ],
            ]
        );
    }
}
