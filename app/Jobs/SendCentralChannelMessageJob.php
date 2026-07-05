<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Central\CentralAuditLogger;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendCentralChannelMessageJob extends CentralAwareJob
{
    public function __construct(
        protected string $channel,
        protected array $config,
        protected string $message,
        protected string $eventKey,
        protected array $context = [],
    ) {
    }

    public function handle(CentralAuditLogger $auditLogger): void
    {
        try {
            $result = match ($this->channel) {
                'telegram' => $this->sendTelegram(),
                'whatsapp' => $this->sendWhatsApp(),
                default => ['status' => 'skipped', 'message' => 'Channel tidak dikenali.'],
            };
        } catch (Throwable $throwable) {
            $auditLogger->error($this->eventKey, 'Pengiriman notifikasi queue gagal.', [
                'target_type' => $this->context['target_type'] ?? 'notification',
                'target_id' => $this->context['target_id'] ?? null,
                'meta' => array_merge($this->context['meta'] ?? [], [
                    'channel' => $this->channel,
                    'error' => $throwable->getMessage(),
                ]),
            ]);

            throw $throwable;
        }

        $level = ($result['status'] ?? 'sent') === 'sent' ? 'info' : 'warning';
        $auditLogger->{$level}($this->eventKey, 'Pengiriman notifikasi queue selesai.', [
            'target_type' => $this->context['target_type'] ?? 'notification',
            'target_id' => $this->context['target_id'] ?? null,
            'meta' => array_merge($this->context['meta'] ?? [], [
                'channel' => $this->channel,
                'result' => $result,
            ]),
        ]);
    }

    protected function sendTelegram(): array
    {
        $botToken = trim((string) data_get($this->config, 'bot_token', ''));
        $chatId = trim((string) data_get($this->config, 'default_chat_id', ''));

        if ($botToken === '' || $chatId === '') {
            return ['status' => 'skipped', 'message' => 'Bot token atau chat id Telegram belum lengkap.'];
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->post(sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken), [
                'chat_id' => $chatId,
                'text' => $this->message,
            ]);

        $payload = $response->json();

        if (! $response->ok() || ! (bool) ($payload['ok'] ?? false)) {
            return ['status' => 'failed', 'message' => (string) ($payload['description'] ?? 'Telegram menolak request.')];
        }

        return ['status' => 'sent', 'message_id' => (string) data_get($payload, 'result.message_id', '-')];
    }

    protected function sendWhatsApp(): array
    {
        $accessToken = trim((string) data_get($this->config, 'access_token', ''));
        $phoneNumberId = trim((string) data_get($this->config, 'phone_number_id', ''));
        $recipientPhone = preg_replace('/\D+/', '', (string) data_get($this->config, 'default_recipient_phone', '')) ?? '';

        if ($accessToken === '' || $phoneNumberId === '' || $recipientPhone === '') {
            return ['status' => 'skipped', 'message' => 'Config WhatsApp belum lengkap.'];
        }

        $response = Http::timeout(20)
            ->withToken($accessToken)
            ->acceptJson()
            ->post('https://graph.facebook.com/v23.0/' . $phoneNumberId . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $recipientPhone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $this->message,
                ],
            ]);

        $payload = $response->json();

        if (! $response->ok()) {
            return ['status' => 'failed', 'message' => (string) data_get($payload, 'error.message', 'WhatsApp menolak request.')];
        }

        return ['status' => 'sent', 'message_id' => (string) data_get($payload, 'messages.0.id', '-')];
    }
}
