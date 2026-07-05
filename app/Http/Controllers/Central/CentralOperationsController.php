<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralAuditLog;
use App\Models\CentralSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CentralOperationsController extends Controller
{
    public function health(): View
    {
        $paymentSettings = CentralSetting::paymentMethodSettings();
        $notificationSettings = CentralSetting::notificationChannelSettings();
        $automationSettings = CentralSetting::automationSettings();
        $lastAutoGenerate = CentralSetting::jsonSetting(CentralSetting::BILLING_AUTO_GENERATE_STATE_KEY);
        $lastReminderRun = CentralSetting::jsonSetting(CentralSetting::BILLING_REMINDER_STATE_KEY);

        return view('central.operations.health', [
            'checks' => [
                [
                    'label' => 'Queue Connection',
                    'status' => config('queue.default') !== '' ? 'ok' : 'warning',
                    'value' => (string) config('queue.default', 'sync'),
                ],
                [
                    'label' => 'QRIS Payment',
                    'status' => data_get($paymentSettings, 'qris.enabled') && filled(data_get($paymentSettings, 'qris.api_key')) ? 'ok' : 'warning',
                    'value' => data_get($paymentSettings, 'qris.enabled') ? 'Enabled' : 'Disabled',
                ],
                [
                    'label' => 'Manual Transfer',
                    'status' => data_get($paymentSettings, 'manual_transfer.enabled') ? 'ok' : 'warning',
                    'value' => data_get($paymentSettings, 'manual_transfer.enabled') ? 'Enabled' : 'Disabled',
                ],
                [
                    'label' => 'Telegram Channel',
                    'status' => data_get($notificationSettings, 'telegram.enabled') && filled(data_get($notificationSettings, 'telegram.bot_token')) ? 'ok' : 'warning',
                    'value' => data_get($notificationSettings, 'telegram.enabled') ? 'Enabled' : 'Disabled',
                ],
                [
                    'label' => 'WhatsApp Channel',
                    'status' => data_get($notificationSettings, 'whatsapp_cloud.enabled') && filled(data_get($notificationSettings, 'whatsapp_cloud.access_token')) ? 'ok' : 'warning',
                    'value' => data_get($notificationSettings, 'whatsapp_cloud.enabled') ? 'Enabled' : 'Disabled',
                ],
                [
                    'label' => 'Billing Auto Generate',
                    'status' => data_get($automationSettings, 'billing_auto_generate_enabled', false) ? 'ok' : 'warning',
                    'value' => (string) data_get($automationSettings, 'billing_auto_generate_time', '00:10'),
                ],
                [
                    'label' => 'Billing Reminder Scan',
                    'status' => data_get($automationSettings, 'billing_reminder_scan_enabled', false) ? 'ok' : 'warning',
                    'value' => (string) data_get($automationSettings, 'billing_reminder_scan_time', '08:00'),
                ],
            ],
            'recentErrors' => CentralAuditLog::query()
                ->where('level', 'error')
                ->latest()
                ->limit(10)
                ->get(),
            'errorCount24h' => CentralAuditLog::query()
                ->where('level', 'error')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'lastAutoGenerate' => $lastAutoGenerate,
            'lastReminderRun' => $lastReminderRun,
        ]);
    }

    public function logs(Request $request): View
    {
        $level = trim((string) $request->string('level'));

        $logs = CentralAuditLog::query()
            ->when(in_array($level, ['info', 'warning', 'error'], true), fn ($query) => $query->where('level', $level))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('central.operations.logs', [
            'logs' => $logs,
            'level' => $level,
        ]);
    }

    public function backupSop(): View
    {
        return view('central.operations.backup-sop');
    }
}
