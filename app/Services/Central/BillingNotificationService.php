<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Jobs\SendCentralChannelMessageJob;
use App\Models\CentralSetting;
use App\Models\Tenant;

class BillingNotificationService
{
    public function __construct(
        protected CentralAuditLogger $auditLogger,
        protected MessageTemplateRenderer $templateRenderer,
    ) {
    }

    public function dispatchPaymentSuccess(Tenant $tenant, array $invoice): void
    {
        $settings = CentralSetting::notificationChannelSettings();

        if (! (bool) data_get($settings, 'events.payment_success_alert', true)) {
            return;
        }

        $template = (string) data_get($settings, 'templates.payment_success', '');
        $message = $this->templateRenderer->render($template, [
            'tenant_name' => (string) ($tenant->name ?? $tenant->id),
            'tenant_id' => (string) $tenant->id,
            'invoice_number' => (string) ($invoice['invoice_number'] ?? '-'),
            'payment_status' => strtoupper((string) data_get($invoice, 'payment.status', $invoice['status'] ?? 'paid')),
            'invoice_total' => (int) ($invoice['invoice_total'] ?? 0),
        ]);

        $this->dispatchChannelNotifications(
            $settings,
            $message,
            'billing.payment_success_dispatched',
            [
                'target_type' => 'tenant',
                'target_id' => (string) $tenant->id,
                'meta' => ['invoice_number' => (string) ($invoice['invoice_number'] ?? '-')],
            ]
        );
    }

    protected function dispatchChannelNotifications(array $settings, string $message, string $eventKey, array $context = []): array
    {
        $result = [
            'telegram' => ['status' => 'skipped', 'message' => 'Channel Telegram tidak aktif.'],
            'whatsapp' => ['status' => 'skipped', 'message' => 'Channel WhatsApp tidak aktif.'],
        ];

        if ((bool) data_get($settings, 'default_channels.telegram', false) && (bool) data_get($settings, 'telegram.enabled', false)) {
            SendCentralChannelMessageJob::dispatch(
                'telegram',
                (array) data_get($settings, 'telegram', []),
                $message,
                $eventKey,
                $context
            );
            $result['telegram'] = ['status' => 'queued', 'message' => 'Telegram notification queued.'];
        }

        if ((bool) data_get($settings, 'default_channels.whatsapp', false) && (bool) data_get($settings, 'whatsapp_cloud.enabled', false)) {
            $whatsAppConfig = (array) data_get($settings, 'whatsapp_cloud', []);
            $whatsAppConfig['default_recipient_phone'] = data_get($settings, 'whatsapp_cloud.default_recipient_phone', '');

            SendCentralChannelMessageJob::dispatch(
                'whatsapp',
                $whatsAppConfig,
                $message,
                $eventKey,
                $context
            );
            $result['whatsapp'] = ['status' => 'queued', 'message' => 'WhatsApp notification queued.'];
        }

        return $result;
    }
}
