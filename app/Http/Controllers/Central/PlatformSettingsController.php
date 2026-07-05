<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Services\Central\CentralAuditLogger;
use App\Services\Central\ManualTransferInboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PlatformSettingsController extends Controller
{
    private const WHATSAPP_GRAPH_BASE_URL = 'https://graph.facebook.com/v23.0';

    public function __construct(
        protected CentralAuditLogger $auditLogger,
        protected ManualTransferInboxService $manualTransferInboxService,
    ) {
    }

    public function landing(): View
    {
        $platformType = CentralSetting::platformSaasType();
        $platformExperience = CentralSetting::platformExperience($platformType);

        return view('central.landing', [
            'platformType' => $platformType,
            'platformExperience' => $platformExperience,
        ]);
    }

    public function edit(): View
    {
        $platformType = CentralSetting::platformSaasType();
        $platformContent = $this->platformContent($platformType);
        $platformExperience = CentralSetting::platformExperience($platformType);
        $paymentSettings = CentralSetting::paymentMethodSettings();
        $notificationSettings = CentralSetting::notificationChannelSettings();
        $automationSettings = CentralSetting::automationSettings();

        return view('central.settings', [
            'platformType' => $platformType,
            'availablePlatformTypes' => CentralSetting::availablePlatformTypes(),
            'platformContent' => $platformContent,
            'platformExperience' => $platformExperience,
            'centralAccent' => $platformContent['accent'],
            'activeModules' => CentralSetting::activeModules($platformType),
            'moduleCatalog' => CentralSetting::moduleCatalogForView($platformType),
            'blueprintModules' => CentralSetting::platformBlueprint($platformType)['modules'],
            'paymentSettings' => $paymentSettings,
            'notificationSettings' => $notificationSettings,
            'automationSettings' => $automationSettings,
            'settingsSummary' => $this->settingsSummary($paymentSettings, $notificationSettings, $automationSettings),
            'credentialStatus' => [
                'qris_api_key' => filled($paymentSettings['qris']['api_key'] ?? null),
                'manual_transfer_fetcher_password' => filled(data_get($paymentSettings, 'manual_transfer.bca_email_fetcher.password')),
                'telegram_bot_token' => filled($notificationSettings['telegram']['bot_token'] ?? null),
                'whatsapp_access_token' => filled($notificationSettings['whatsapp_cloud']['access_token'] ?? null),
                'whatsapp_verify_token' => filled($notificationSettings['whatsapp_cloud']['verify_token'] ?? null),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $catalogKeys = array_keys(CentralSetting::moduleCatalog());
        $existingPaymentSettings = CentralSetting::paymentMethodSettings();
        $existingNotificationSettings = CentralSetting::notificationChannelSettings();
        $currentPlatformType = CentralSetting::platformSaasType();

        $validated = $request->validate([
            'platform_saas_type' => ['required', 'string', Rule::in(CentralSetting::availablePlatformTypes())],
            'experience_platform_type' => ['nullable', 'string', Rule::in(CentralSetting::availablePlatformTypes())],
            'active_modules' => ['nullable', 'array'],
            'active_modules.*' => ['string', Rule::in($catalogKeys)],
            'sync_modules_with_platform' => ['nullable', 'boolean'],
            'payment_methods.qris.provider_name' => ['nullable', 'string', 'max:100'],
            'payment_methods.qris.api_key' => ['nullable', 'string', 'max:255'],
            'payment_methods.qris.merchant_id' => ['nullable', 'string', 'max:100'],
            'payment_methods.qris.nmid' => ['nullable', 'string', 'max:100'],
            'payment_methods.qris.base_url' => ['nullable', 'string', 'max:255'],
            'payment_methods.qris.use_tip' => ['nullable', 'string', Rule::in(['yes', 'no'])],
            'payment_methods.qris.instructions' => ['nullable', 'string'],
            'payment_methods.manual_transfer.bank_name' => ['nullable', 'string', 'max:100'],
            'payment_methods.manual_transfer.account_name' => ['nullable', 'string', 'max:100'],
            'payment_methods.manual_transfer.account_number' => ['nullable', 'string', 'max:100'],
            'payment_methods.manual_transfer.notes' => ['nullable', 'string'],
            'payment_methods.manual_transfer.bca_email_fetcher.host' => ['nullable', 'string', 'max:190'],
            'payment_methods.manual_transfer.bca_email_fetcher.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'payment_methods.manual_transfer.bca_email_fetcher.encryption' => ['nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
            'payment_methods.manual_transfer.bca_email_fetcher.username' => ['nullable', 'string', 'max:190'],
            'payment_methods.manual_transfer.bca_email_fetcher.password' => ['nullable', 'string', 'max:255'],
            'payment_methods.manual_transfer.bca_email_fetcher.folder' => ['nullable', 'string', 'max:120'],
            'payment_methods.manual_transfer.bca_email_fetcher.sender_filter' => ['nullable', 'string', 'max:190'],
            'payment_methods.manual_transfer.bca_email_fetcher.subject_keyword' => ['nullable', 'string', 'max:190'],
            'payment_methods.manual_transfer.bca_email_fetcher.lookback_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'payment_methods.manual_transfer.bca_email_fetcher.max_messages' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notifications.telegram.bot_name' => ['nullable', 'string', 'max:100'],
            'notifications.telegram.bot_token' => ['nullable', 'string', 'max:255'],
            'notifications.telegram.default_chat_id' => ['nullable', 'string', 'max:100'],
            'notifications.whatsapp_cloud.access_token' => ['nullable', 'string', 'max:255'],
            'notifications.whatsapp_cloud.phone_number_id' => ['nullable', 'string', 'max:100'],
            'notifications.whatsapp_cloud.business_account_id' => ['nullable', 'string', 'max:100'],
            'notifications.whatsapp_cloud.verify_token' => ['nullable', 'string', 'max:255'],
            'notifications.whatsapp_cloud.default_recipient_phone' => ['nullable', 'string', 'max:30'],
            'notifications.templates.billing_reminder' => ['nullable', 'string'],
            'notifications.templates.payment_success' => ['nullable', 'string'],
            'notifications.templates.public_payment_note' => ['nullable', 'string'],
            'automation.billing_auto_generate_time' => ['nullable', 'date_format:H:i'],
            'automation.billing_reminder_scan_time' => ['nullable', 'date_format:H:i'],
            'automation.subscription_reminder_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'experience.brand_name' => ['nullable', 'string', 'max:120'],
            'experience.brand_label' => ['nullable', 'string', 'max:120'],
            'experience.badge' => ['nullable', 'string', 'max:80'],
            'experience.headline' => ['nullable', 'string', 'max:255'],
            'experience.description' => ['nullable', 'string'],
            'experience.primary_cta' => ['nullable', 'string', 'max:80'],
            'experience.secondary_cta' => ['nullable', 'string', 'max:80'],
            'experience.login_title' => ['nullable', 'string', 'max:255'],
            'experience.login_description' => ['nullable', 'string'],
            'experience.register_title' => ['nullable', 'string', 'max:255'],
            'experience.register_description' => ['nullable', 'string'],
            'experience.feature_title' => ['nullable', 'string', 'max:120'],
            'experience.feature_description' => ['nullable', 'string'],
            'experience.visual_eyebrow' => ['nullable', 'string', 'max:80'],
            'experience.visual_title' => ['nullable', 'string', 'max:255'],
            'experience.visual_description' => ['nullable', 'string'],
            'experience.feature_one' => ['nullable', 'string', 'max:255'],
            'experience.feature_two' => ['nullable', 'string', 'max:255'],
            'experience.feature_three' => ['nullable', 'string', 'max:255'],
            'experience.metric_one_label' => ['nullable', 'string', 'max:50'],
            'experience.metric_one_value' => ['nullable', 'string', 'max:80'],
            'experience.metric_two_label' => ['nullable', 'string', 'max:50'],
            'experience.metric_two_value' => ['nullable', 'string', 'max:80'],
            'experience.metric_three_label' => ['nullable', 'string', 'max:50'],
            'experience.metric_three_value' => ['nullable', 'string', 'max:80'],
            'experience.metric_four_label' => ['nullable', 'string', 'max:50'],
            'experience.metric_four_value' => ['nullable', 'string', 'max:80'],
            'experience.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'experience.hero_image_url' => ['nullable', 'string', 'max:500'],
            'experience.auth_image_url' => ['nullable', 'string', 'max:500'],
        ]);
        $experiencePlatformType = (string) data_get($validated, 'experience_platform_type', $currentPlatformType);

        CentralSetting::setPlatformExperience(
            $experiencePlatformType,
            [
                'brand_name' => trim((string) data_get($validated, 'experience.brand_name', '')),
                'brand_label' => trim((string) data_get($validated, 'experience.brand_label', '')),
                'badge' => trim((string) data_get($validated, 'experience.badge', '')),
                'headline' => trim((string) data_get($validated, 'experience.headline', '')),
                'description' => trim((string) data_get($validated, 'experience.description', '')),
                'primary_cta' => trim((string) data_get($validated, 'experience.primary_cta', '')),
                'secondary_cta' => trim((string) data_get($validated, 'experience.secondary_cta', '')),
                'login_title' => trim((string) data_get($validated, 'experience.login_title', '')),
                'login_description' => trim((string) data_get($validated, 'experience.login_description', '')),
                'register_title' => trim((string) data_get($validated, 'experience.register_title', '')),
                'register_description' => trim((string) data_get($validated, 'experience.register_description', '')),
                'feature_title' => trim((string) data_get($validated, 'experience.feature_title', '')),
                'feature_description' => trim((string) data_get($validated, 'experience.feature_description', '')),
                'visual_eyebrow' => trim((string) data_get($validated, 'experience.visual_eyebrow', '')),
                'visual_title' => trim((string) data_get($validated, 'experience.visual_title', '')),
                'visual_description' => trim((string) data_get($validated, 'experience.visual_description', '')),
                'feature_one' => trim((string) data_get($validated, 'experience.feature_one', '')),
                'feature_two' => trim((string) data_get($validated, 'experience.feature_two', '')),
                'feature_three' => trim((string) data_get($validated, 'experience.feature_three', '')),
                'metric_one_label' => trim((string) data_get($validated, 'experience.metric_one_label', '')),
                'metric_one_value' => trim((string) data_get($validated, 'experience.metric_one_value', '')),
                'metric_two_label' => trim((string) data_get($validated, 'experience.metric_two_label', '')),
                'metric_two_value' => trim((string) data_get($validated, 'experience.metric_two_value', '')),
                'metric_three_label' => trim((string) data_get($validated, 'experience.metric_three_label', '')),
                'metric_three_value' => trim((string) data_get($validated, 'experience.metric_three_value', '')),
                'metric_four_label' => trim((string) data_get($validated, 'experience.metric_four_label', '')),
                'metric_four_value' => trim((string) data_get($validated, 'experience.metric_four_value', '')),
                'accent' => trim((string) data_get($validated, 'experience.accent', '')),
                'hero_image_url' => trim((string) data_get($validated, 'experience.hero_image_url', '')),
                'auth_image_url' => trim((string) data_get($validated, 'experience.auth_image_url', '')),
            ]
        );

        CentralSetting::setPlatformSaasType($validated['platform_saas_type']);
        $shouldSyncModules = $request->boolean('sync_modules_with_platform', true);

        if ($shouldSyncModules) {
            CentralSetting::syncActiveModulesForPlatform($validated['platform_saas_type']);
        } else {
            CentralSetting::setActiveModules($validated['active_modules'] ?? []);
        }

        CentralSetting::setPaymentMethodSettings([
            'qris' => [
                'enabled' => $request->boolean('payment_methods.qris.enabled'),
                'provider_name' => trim((string) data_get($validated, 'payment_methods.qris.provider_name', '')),
                'api_key' => filled(data_get($validated, 'payment_methods.qris.api_key'))
                    ? trim((string) data_get($validated, 'payment_methods.qris.api_key'))
                    : (string) data_get($existingPaymentSettings, 'qris.api_key', ''),
                'merchant_id' => trim((string) data_get($validated, 'payment_methods.qris.merchant_id', '')),
                'nmid' => trim((string) data_get($validated, 'payment_methods.qris.nmid', '')),
                'base_url' => trim((string) data_get($validated, 'payment_methods.qris.base_url', '')),
                'use_tip' => (string) data_get($validated, 'payment_methods.qris.use_tip', 'no'),
                'instructions' => trim((string) data_get($validated, 'payment_methods.qris.instructions', '')),
            ],
            'manual_transfer' => [
                'enabled' => $request->boolean('payment_methods.manual_transfer.enabled'),
                'bank_name' => trim((string) data_get($validated, 'payment_methods.manual_transfer.bank_name', '')),
                'account_name' => trim((string) data_get($validated, 'payment_methods.manual_transfer.account_name', '')),
                'account_number' => trim((string) data_get($validated, 'payment_methods.manual_transfer.account_number', '')),
                'notes' => trim((string) data_get($validated, 'payment_methods.manual_transfer.notes', '')),
                'bca_email_fetcher' => [
                    'enabled' => $request->boolean('payment_methods.manual_transfer.bca_email_fetcher.enabled'),
                    'host' => trim((string) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.host', '')),
                    'port' => (int) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.port', 993),
                    'encryption' => (string) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.encryption', 'ssl'),
                    'validate_certificate' => $request->boolean('payment_methods.manual_transfer.bca_email_fetcher.validate_certificate'),
                    'username' => trim((string) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.username', '')),
                    'password' => filled(data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.password'))
                        ? trim((string) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.password'))
                        : (string) data_get($existingPaymentSettings, 'manual_transfer.bca_email_fetcher.password', ''),
                    'folder' => trim((string) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.folder', 'INBOX')),
                    'sender_filter' => trim((string) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.sender_filter', '')),
                    'subject_keyword' => trim((string) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.subject_keyword', '')),
                    'lookback_minutes' => (int) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.lookback_minutes', 60),
                    'max_messages' => (int) data_get($validated, 'payment_methods.manual_transfer.bca_email_fetcher.max_messages', 20),
                    'unseen_only' => $request->boolean('payment_methods.manual_transfer.bca_email_fetcher.unseen_only', true),
                ],
            ],
        ]);

        CentralSetting::setNotificationChannelSettings([
            'events' => [
                'billing_due_reminder' => $request->boolean('notifications.events.billing_due_reminder'),
                'subscription_expiry_reminder' => $request->boolean('notifications.events.subscription_expiry_reminder'),
                'payment_success_alert' => $request->boolean('notifications.events.payment_success_alert'),
            ],
            'default_channels' => [
                'email' => $request->boolean('notifications.default_channels.email'),
                'telegram' => $request->boolean('notifications.default_channels.telegram'),
                'whatsapp' => $request->boolean('notifications.default_channels.whatsapp'),
            ],
            'telegram' => [
                'enabled' => $request->boolean('notifications.telegram.enabled'),
                'bot_name' => trim((string) data_get($validated, 'notifications.telegram.bot_name', '')),
                'bot_token' => filled(data_get($validated, 'notifications.telegram.bot_token'))
                    ? trim((string) data_get($validated, 'notifications.telegram.bot_token'))
                    : (string) data_get($existingNotificationSettings, 'telegram.bot_token', ''),
                'default_chat_id' => trim((string) data_get($validated, 'notifications.telegram.default_chat_id', '')),
            ],
            'whatsapp_cloud' => [
                'enabled' => $request->boolean('notifications.whatsapp_cloud.enabled'),
                'access_token' => filled(data_get($validated, 'notifications.whatsapp_cloud.access_token'))
                    ? trim((string) data_get($validated, 'notifications.whatsapp_cloud.access_token'))
                    : (string) data_get($existingNotificationSettings, 'whatsapp_cloud.access_token', ''),
                'phone_number_id' => trim((string) data_get($validated, 'notifications.whatsapp_cloud.phone_number_id', '')),
                'business_account_id' => trim((string) data_get($validated, 'notifications.whatsapp_cloud.business_account_id', '')),
                'verify_token' => filled(data_get($validated, 'notifications.whatsapp_cloud.verify_token'))
                    ? trim((string) data_get($validated, 'notifications.whatsapp_cloud.verify_token'))
                    : (string) data_get($existingNotificationSettings, 'whatsapp_cloud.verify_token', ''),
                'default_recipient_phone' => preg_replace('/\D+/', '', trim((string) data_get($validated, 'notifications.whatsapp_cloud.default_recipient_phone', ''))) ?? '',
            ],
            'templates' => [
                'billing_reminder' => trim((string) data_get($validated, 'notifications.templates.billing_reminder', data_get($existingNotificationSettings, 'templates.billing_reminder', ''))),
                'payment_success' => trim((string) data_get($validated, 'notifications.templates.payment_success', data_get($existingNotificationSettings, 'templates.payment_success', ''))),
                'public_payment_note' => trim((string) data_get($validated, 'notifications.templates.public_payment_note', data_get($existingNotificationSettings, 'templates.public_payment_note', ''))),
            ],
        ]);

        CentralSetting::setAutomationSettings([
            'billing_auto_generate_enabled' => $request->boolean('automation.billing_auto_generate_enabled'),
            'billing_auto_generate_time' => (string) data_get($validated, 'automation.billing_auto_generate_time', '00:10'),
            'billing_reminder_scan_enabled' => $request->boolean('automation.billing_reminder_scan_enabled'),
            'billing_reminder_scan_time' => (string) data_get($validated, 'automation.billing_reminder_scan_time', '08:00'),
            'subscription_reminder_days' => (int) data_get($validated, 'automation.subscription_reminder_days', 7),
        ]);

        $this->auditLogger->info(
            'settings.updated',
            'System settings pusat diperbarui.',
            [
                'target_type' => 'central_settings',
                'target_id' => 'system',
                'meta' => [
                    'platform_saas_type' => $validated['platform_saas_type'],
                    'active_module_count' => count((array) ($validated['active_modules'] ?? [])),
                ],
            ]
        );

        return redirect()
            ->route('central.super-admin.settings.edit')
            ->with('status', 'System settings pusat berhasil diperbarui.');
    }

    public function testQrisConnection(Request $request): RedirectResponse
    {
        $request->validate([
            'payment_methods.qris.provider_name' => ['nullable', 'string', 'max:100'],
            'payment_methods.qris.api_key' => ['nullable', 'string', 'max:255'],
            'payment_methods.qris.merchant_id' => ['nullable', 'string', 'max:100'],
            'payment_methods.qris.base_url' => ['nullable', 'string', 'max:255'],
            'payment_methods.qris.use_tip' => ['nullable', 'string', Rule::in(['yes', 'no'])],
        ]);

        $config = $this->qrisSettingsForTest($request);

        if ($config['api_key'] === '' || $config['merchant_id'] === '' || $config['base_url'] === '') {
            return $this->testResultRedirect('danger', 'QRIS test gagal', 'API Key, Merchant ID, dan Base URL QRIS wajib terisi untuk test connection.');
        }

        $testReference = 'TEST-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(rtrim($config['base_url'], '/') . '/show_qris.php', [
                    'do' => 'create-invoice',
                    'apikey' => $config['api_key'],
                    'mID' => $config['merchant_id'],
                    'cliTrxNumber' => $testReference,
                    'cliTrxAmount' => 10000,
                    'useTip' => $config['use_tip'],
                ]);
        } catch (Throwable $throwable) {
            return $this->testResultRedirect('danger', 'QRIS test gagal', 'Provider QRIS tidak bisa dihubungi dari server.', [
                'Provider' => $config['provider_name'] !== '' ? $config['provider_name'] : 'QRIS',
                'Error' => $throwable->getMessage(),
            ]);
        }

        $payload = $response->json();

        if (! $response->ok()) {
            return $this->testResultRedirect('danger', 'QRIS test gagal', 'Provider QRIS merespons error HTTP.', [
                'HTTP Status' => (string) $response->status(),
                'Body' => $response->body(),
            ]);
        }

        if (($payload['status'] ?? 'failed') !== 'success') {
            return $this->testResultRedirect('danger', 'QRIS test gagal', (string) data_get($payload, 'data.qris_status', 'Provider QRIS menolak request test.'));
        }

        return $this->testResultRedirect('success', 'QRIS siap dipakai', 'Koneksi ke provider QRIS berhasil. Request test invoice diterima.', [
            'Provider' => $config['provider_name'] !== '' ? $config['provider_name'] : 'QRIS',
            'Test Reference' => $testReference,
            'Invoice ID' => (string) data_get($payload, 'data.qris_invoiceid', '-'),
            'NMID' => (string) data_get($payload, 'data.qris_nmid', '-'),
        ]);
    }

    public function testTelegramConnection(Request $request): RedirectResponse
    {
        $request->validate([
            'notifications.telegram.bot_name' => ['nullable', 'string', 'max:100'],
            'notifications.telegram.bot_token' => ['nullable', 'string', 'max:255'],
            'notifications.telegram.default_chat_id' => ['nullable', 'string', 'max:100'],
        ]);

        $config = $this->telegramSettingsForTest($request);

        if ($config['bot_token'] === '' || $config['default_chat_id'] === '') {
            return $this->testResultRedirect('danger', 'Telegram test gagal', 'Bot token dan default chat ID wajib terisi untuk kirim test message.');
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->post(sprintf('https://api.telegram.org/bot%s/sendMessage', $config['bot_token']), [
                    'chat_id' => $config['default_chat_id'],
                    'text' => sprintf(
                        "Test Telegram berhasil.\nPlatform: %s\nBot: %s\nWaktu: %s",
                        ucfirst(CentralSetting::platformSaasType()),
                        $config['bot_name'] !== '' ? $config['bot_name'] : 'Central Bot',
                        now()->format('d M Y H:i:s')
                    ),
                ]);
        } catch (Throwable $throwable) {
            return $this->testResultRedirect('danger', 'Telegram test gagal', 'Telegram API tidak bisa dihubungi dari server.', [
                'Error' => $throwable->getMessage(),
            ]);
        }

        $payload = $response->json();

        if (! $response->ok() || ! (bool) ($payload['ok'] ?? false)) {
            return $this->testResultRedirect('danger', 'Telegram test gagal', (string) ($payload['description'] ?? 'Telegram menolak request test message.'), [
                'HTTP Status' => (string) $response->status(),
            ]);
        }

        return $this->testResultRedirect('success', 'Telegram siap dipakai', 'Pesan test berhasil dikirim ke chat Telegram default.', [
            'Bot' => $config['bot_name'] !== '' ? $config['bot_name'] : 'Central Bot',
            'Chat ID' => $config['default_chat_id'],
            'Message ID' => (string) data_get($payload, 'result.message_id', '-'),
        ]);
    }

    public function testWhatsAppConnection(Request $request): RedirectResponse
    {
        $request->validate([
            'notifications.whatsapp_cloud.access_token' => ['nullable', 'string', 'max:255'],
            'notifications.whatsapp_cloud.phone_number_id' => ['nullable', 'string', 'max:100'],
            'notifications.whatsapp_cloud.business_account_id' => ['nullable', 'string', 'max:100'],
            'notifications.whatsapp_cloud.test_recipient_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $config = $this->whatsAppSettingsForTest($request);

        if ($config['access_token'] === '' || $config['phone_number_id'] === '') {
            return $this->testResultRedirect('danger', 'WhatsApp test gagal', 'Access token dan Phone Number ID wajib terisi untuk test connection.');
        }

        try {
            $metadataResponse = Http::timeout(20)
                ->withToken($config['access_token'])
                ->acceptJson()
                ->get(self::WHATSAPP_GRAPH_BASE_URL . '/' . $config['phone_number_id'], [
                    'fields' => 'display_phone_number,verified_name,id',
                ]);
        } catch (Throwable $throwable) {
            return $this->testResultRedirect('danger', 'WhatsApp test gagal', 'WhatsApp Cloud API tidak bisa dihubungi dari server.', [
                'Error' => $throwable->getMessage(),
            ]);
        }

        $metadataPayload = $metadataResponse->json();

        if (! $metadataResponse->ok()) {
            return $this->testResultRedirect('danger', 'WhatsApp test gagal', (string) data_get($metadataPayload, 'error.message', 'Gagal membaca metadata WhatsApp sender.'), [
                'HTTP Status' => (string) $metadataResponse->status(),
            ]);
        }

        if ($config['test_recipient_phone'] === '') {
            return $this->testResultRedirect('success', 'WhatsApp connection berhasil', 'Koneksi metadata WhatsApp Cloud API berhasil dibaca. Isi nomor tujuan test kalau mau sekalian kirim pesan.', [
                'Verified Name' => (string) data_get($metadataPayload, 'verified_name', '-'),
                'Display Number' => (string) data_get($metadataPayload, 'display_phone_number', '-'),
                'Phone Number ID' => $config['phone_number_id'],
            ]);
        }

        try {
            $messageResponse = Http::timeout(20)
                ->withToken($config['access_token'])
                ->acceptJson()
                ->post(self::WHATSAPP_GRAPH_BASE_URL . '/' . $config['phone_number_id'] . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $config['test_recipient_phone'],
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => sprintf(
                            'Test WhatsApp Cloud API berhasil. Platform %s aktif dan koneksi dari panel pusat berjalan normal. Waktu %s.',
                            ucfirst(CentralSetting::platformSaasType()),
                            now()->format('d M Y H:i:s')
                        ),
                    ],
                ]);
        } catch (Throwable $throwable) {
            return $this->testResultRedirect('warning', 'WhatsApp metadata OK', 'Metadata sender berhasil dibaca, tapi kirim test message gagal dari server.', [
                'Verified Name' => (string) data_get($metadataPayload, 'verified_name', '-'),
                'Recipient' => $config['test_recipient_phone'],
                'Error' => $throwable->getMessage(),
            ]);
        }

        $messagePayload = $messageResponse->json();

        if (! $messageResponse->ok()) {
            return $this->testResultRedirect('warning', 'WhatsApp metadata OK', (string) data_get($messagePayload, 'error.message', 'Gagal kirim test message WhatsApp.'), [
                'Verified Name' => (string) data_get($metadataPayload, 'verified_name', '-'),
                'Recipient' => $config['test_recipient_phone'],
                'HTTP Status' => (string) $messageResponse->status(),
            ]);
        }

        return $this->testResultRedirect('success', 'WhatsApp siap dipakai', 'Metadata sender valid dan test message WhatsApp berhasil dikirim.', [
            'Verified Name' => (string) data_get($metadataPayload, 'verified_name', '-'),
            'Recipient' => $config['test_recipient_phone'],
            'Message ID' => (string) data_get($messagePayload, 'messages.0.id', '-'),
        ]);
    }

    public function testManualTransferFetcher(Request $request): RedirectResponse
    {
        $request->validate([
            'payment_methods.manual_transfer.bca_email_fetcher.host' => ['nullable', 'string', 'max:190'],
            'payment_methods.manual_transfer.bca_email_fetcher.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'payment_methods.manual_transfer.bca_email_fetcher.encryption' => ['nullable', 'string', Rule::in(['ssl', 'tls', 'none'])],
            'payment_methods.manual_transfer.bca_email_fetcher.username' => ['nullable', 'string', 'max:190'],
            'payment_methods.manual_transfer.bca_email_fetcher.password' => ['nullable', 'string', 'max:255'],
            'payment_methods.manual_transfer.bca_email_fetcher.folder' => ['nullable', 'string', 'max:120'],
        ]);

        $config = $this->manualTransferFetcherSettingsForTest($request);

        if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '') {
            return $this->testResultRedirect('danger', 'Inbox BCA test gagal', 'Host, username, dan password mailbox wajib terisi untuk test connection.');
        }

        $result = $this->manualTransferInboxService->testConnection($config);

        return $this->testResultRedirect(
            (string) ($result['status'] ?? 'danger'),
            (string) ($result['title'] ?? 'Inbox BCA test gagal'),
            (string) ($result['message'] ?? 'Koneksi inbox gagal diuji.'),
            (array) ($result['details'] ?? [])
        );
    }

    protected function platformContent(string $platformType): array
    {
        return CentralSetting::platformExperience($platformType);
    }

    protected function settingsSummary(array $paymentSettings, array $notificationSettings, array $automationSettings): array
    {
        $enabledPayments = collect($paymentSettings)
            ->filter(fn (array $definition): bool => (bool) ($definition['enabled'] ?? false))
            ->count();
        $enabledChannels = collect(data_get($notificationSettings, 'default_channels', []))
            ->filter(fn (bool $enabled): bool => $enabled)
            ->count();
        $activeAutomation = collect([
            (bool) data_get($automationSettings, 'billing_auto_generate_enabled', false),
            (bool) data_get($automationSettings, 'billing_reminder_scan_enabled', false),
        ])->filter()->count();

        return [
            'enabled_payments' => $enabledPayments,
            'enabled_channels' => $enabledChannels,
            'active_automation' => $activeAutomation,
        ];
    }

    protected function qrisSettingsForTest(Request $request): array
    {
        $existing = CentralSetting::paymentMethodSettings();

        return [
            'provider_name' => trim((string) $request->input('payment_methods.qris.provider_name', data_get($existing, 'qris.provider_name', ''))),
            'api_key' => filled($request->input('payment_methods.qris.api_key'))
                ? trim((string) $request->input('payment_methods.qris.api_key'))
                : trim((string) data_get($existing, 'qris.api_key', '')),
            'merchant_id' => trim((string) $request->input('payment_methods.qris.merchant_id', data_get($existing, 'qris.merchant_id', ''))),
            'base_url' => trim((string) $request->input('payment_methods.qris.base_url', data_get($existing, 'qris.base_url', ''))),
            'use_tip' => trim((string) $request->input('payment_methods.qris.use_tip', data_get($existing, 'qris.use_tip', 'no'))) ?: 'no',
        ];
    }

    protected function manualTransferFetcherSettingsForTest(Request $request): array
    {
        $existing = CentralSetting::paymentMethodSettings();

        return [
            'enabled' => true,
            'host' => trim((string) $request->input('payment_methods.manual_transfer.bca_email_fetcher.host', data_get($existing, 'manual_transfer.bca_email_fetcher.host', ''))),
            'port' => (int) $request->input('payment_methods.manual_transfer.bca_email_fetcher.port', data_get($existing, 'manual_transfer.bca_email_fetcher.port', 993)),
            'encryption' => trim((string) $request->input('payment_methods.manual_transfer.bca_email_fetcher.encryption', data_get($existing, 'manual_transfer.bca_email_fetcher.encryption', 'ssl'))) ?: 'ssl',
            'validate_certificate' => $request->boolean('payment_methods.manual_transfer.bca_email_fetcher.validate_certificate', (bool) data_get($existing, 'manual_transfer.bca_email_fetcher.validate_certificate', false)),
            'username' => trim((string) $request->input('payment_methods.manual_transfer.bca_email_fetcher.username', data_get($existing, 'manual_transfer.bca_email_fetcher.username', ''))),
            'password' => filled($request->input('payment_methods.manual_transfer.bca_email_fetcher.password'))
                ? trim((string) $request->input('payment_methods.manual_transfer.bca_email_fetcher.password'))
                : trim((string) data_get($existing, 'manual_transfer.bca_email_fetcher.password', '')),
            'folder' => trim((string) $request->input('payment_methods.manual_transfer.bca_email_fetcher.folder', data_get($existing, 'manual_transfer.bca_email_fetcher.folder', 'INBOX'))) ?: 'INBOX',
            'sender_filter' => trim((string) $request->input('payment_methods.manual_transfer.bca_email_fetcher.sender_filter', data_get($existing, 'manual_transfer.bca_email_fetcher.sender_filter', ''))),
            'subject_keyword' => trim((string) $request->input('payment_methods.manual_transfer.bca_email_fetcher.subject_keyword', data_get($existing, 'manual_transfer.bca_email_fetcher.subject_keyword', ''))),
            'lookback_minutes' => (int) $request->input('payment_methods.manual_transfer.bca_email_fetcher.lookback_minutes', data_get($existing, 'manual_transfer.bca_email_fetcher.lookback_minutes', 60)),
            'max_messages' => (int) $request->input('payment_methods.manual_transfer.bca_email_fetcher.max_messages', data_get($existing, 'manual_transfer.bca_email_fetcher.max_messages', 20)),
            'unseen_only' => $request->boolean('payment_methods.manual_transfer.bca_email_fetcher.unseen_only', (bool) data_get($existing, 'manual_transfer.bca_email_fetcher.unseen_only', true)),
        ];
    }

    protected function telegramSettingsForTest(Request $request): array
    {
        $existing = CentralSetting::notificationChannelSettings();

        return [
            'bot_name' => trim((string) $request->input('notifications.telegram.bot_name', data_get($existing, 'telegram.bot_name', ''))),
            'bot_token' => filled($request->input('notifications.telegram.bot_token'))
                ? trim((string) $request->input('notifications.telegram.bot_token'))
                : trim((string) data_get($existing, 'telegram.bot_token', '')),
            'default_chat_id' => trim((string) $request->input('notifications.telegram.default_chat_id', data_get($existing, 'telegram.default_chat_id', ''))),
        ];
    }

    protected function whatsAppSettingsForTest(Request $request): array
    {
        $existing = CentralSetting::notificationChannelSettings();

        return [
            'access_token' => filled($request->input('notifications.whatsapp_cloud.access_token'))
                ? trim((string) $request->input('notifications.whatsapp_cloud.access_token'))
                : trim((string) data_get($existing, 'whatsapp_cloud.access_token', '')),
            'phone_number_id' => trim((string) $request->input('notifications.whatsapp_cloud.phone_number_id', data_get($existing, 'whatsapp_cloud.phone_number_id', ''))),
            'business_account_id' => trim((string) $request->input('notifications.whatsapp_cloud.business_account_id', data_get($existing, 'whatsapp_cloud.business_account_id', ''))),
            'default_recipient_phone' => preg_replace('/\D+/', '', (string) $request->input('notifications.whatsapp_cloud.default_recipient_phone', data_get($existing, 'whatsapp_cloud.default_recipient_phone', ''))) ?? '',
            'test_recipient_phone' => preg_replace('/\D+/', '', (string) $request->input('notifications.whatsapp_cloud.test_recipient_phone', '')) ?? '',
        ];
    }

    protected function testResultRedirect(string $variant, string $title, string $message, array $details = []): RedirectResponse
    {
        return back()
            ->withInput()
            ->with('test_result', [
                'variant' => $variant,
                'title' => $title,
                'message' => $message,
                'details' => $details,
            ]);
    }
}
