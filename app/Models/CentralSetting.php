<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class CentralSetting extends Model
{
    public const PLATFORM_SAAS_TYPE_KEY = 'platform_saas_type';
    public const ACTIVE_MODULES_KEY = 'active_modules';
    public const PACKAGE_CATALOG_KEY = 'package_catalog';
    public const DEFAULT_PACKAGE_CODE_KEY = 'default_package_code';
    public const BILLING_AUTO_GENERATE_STATE_KEY = 'billing_auto_generate_state';
    public const BILLING_REMINDER_STATE_KEY = 'billing_reminder_state';
    public const PAYMENT_METHODS_KEY = 'payment_methods';
    public const NOTIFICATION_CHANNELS_KEY = 'notification_channels';
    public const AUTOMATION_RULES_KEY = 'automation_rules';
    public const PLATFORM_EXPERIENCE_KEY = 'platform_experience';

    public $timestamps = false;

    protected $table = 'central_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    public static function availablePlatformTypes(): array
    {
        return [
            'universal',
            'resto',
            'hotel',
            'tirta',
            'netbilling',
        ];
    }

    public static function moduleCatalog(): array
    {
        return [
            'BaseFeature' => [
                'label' => 'Base Feature',
                'description' => 'Login tenant, landing page, dashboard, dan pengaturan branding dasar.',
                'required' => true,
                'platforms' => static::availablePlatformTypes(),
            ],
            'RestoPOS' => [
                'label' => 'Resto POS',
                'description' => 'Kasir, meja, order, dan operasional restoran.',
                'required' => false,
                'platforms' => ['resto'],
            ],
            'HospitalityHub' => [
                'label' => 'Hospitality Hub',
                'description' => 'Reservasi, kamar, front office, dan housekeeping hotel.',
                'required' => false,
                'platforms' => ['hotel'],
            ],
            'TirtaBilling' => [
                'label' => 'Tirta Billing',
                'description' => 'Pelanggan, meter, pembacaan, tagihan, dan pembayaran air.',
                'required' => false,
                'platforms' => ['tirta'],
            ],
            'NetBilling' => [
                'label' => 'Net Billing',
                'description' => 'Billing pelanggan internet, paket layanan, dan operasional ISP.',
                'required' => false,
                'platforms' => ['netbilling'],
            ],
        ];
    }

    public static function packageFeatureCatalog(): array
    {
        return [
            'custom_domain' => [
                'label' => 'Custom Domain',
                'description' => 'Tenant bisa pakai domain sendiri.',
            ],
            'advanced_reports' => [
                'label' => 'Advanced Reports',
                'description' => 'Akses laporan lanjutan dan insight bisnis.',
            ],
            'api_access' => [
                'label' => 'API Access',
                'description' => 'Buka akses integrasi API untuk tenant.',
            ],
            'priority_support' => [
                'label' => 'Priority Support',
                'description' => 'Antrian support prioritas dari tim pusat.',
            ],
            'white_label' => [
                'label' => 'White Label',
                'description' => 'Hilangkan branding platform pusat pada tenant.',
            ],
        ];
    }

    public static function packageBillingComponentCatalog(?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();

        return [
            'setup_fee' => [
                'label' => 'Biaya Setup 1x',
                'description' => 'Tagihan sekali saat tenant onboarding atau aktivasi awal.',
                'kind' => 'flat',
            ],
            'monthly_base' => [
                'label' => 'Biaya Bulanan',
                'description' => 'Biaya tetap bulanan di luar skema usage.',
                'kind' => 'flat',
            ],
            'per_customer' => [
                'label' => 'Per Pelanggan',
                'description' => $platformType === 'tirta'
                    ? 'Biaya per pelanggan aktif tenant air.'
                    : 'Biaya per pelanggan aktif tenant.',
                'kind' => 'fixed_usage',
                'unit_label' => 'pelanggan',
            ],
            'per_success_transaction' => [
                'label' => 'Per Transaksi Berhasil',
                'description' => 'Biaya tetap untuk tiap transaksi sukses.',
                'kind' => 'fixed_usage',
                'unit_label' => 'transaksi',
            ],
            'per_checkout' => [
                'label' => 'Per Checkout',
                'description' => 'Biaya tetap untuk tiap checkout / penyelesaian layanan.',
                'kind' => 'fixed_usage',
                'unit_label' => 'checkout',
            ],
            'transaction_percentage' => [
                'label' => 'Persentase Transaksi',
                'description' => 'Biaya persentase dari total nominal transaksi.',
                'kind' => 'percentage_usage',
                'unit_label' => 'nominal transaksi',
            ],
        ];
    }

    public static function platformBlueprint(string $platformType): array
    {
        $platformType = in_array($platformType, static::availablePlatformTypes(), true)
            ? $platformType
            : 'universal';

        return match ($platformType) {
            'resto' => [
                'modules' => ['BaseFeature', 'RestoPOS'],
                'theme_color' => '#f97316',
                'tenant_description' => 'Workspace resto untuk kasir, pesanan, meja, dan laporan operasional.',
            ],
            'hotel' => [
                'modules' => ['BaseFeature', 'HospitalityHub'],
                'theme_color' => '#8b5cf6',
                'tenant_description' => 'Workspace hotel untuk reservasi, kamar, front office, dan housekeeping.',
            ],
            'tirta' => [
                'modules' => ['BaseFeature', 'TirtaBilling'],
                'theme_color' => '#06b6d4',
                'tenant_description' => 'Workspace tirta untuk pelanggan, meter, pembacaan, tagihan, dan pembayaran air.',
            ],
            'netbilling' => [
                'modules' => ['BaseFeature', 'NetBilling'],
                'theme_color' => '#22c55e',
                'tenant_description' => 'Workspace netbilling untuk paket internet, pelanggan aktif, dan billing berulang.',
            ],
            default => [
                'modules' => ['BaseFeature'],
                'theme_color' => '#38bdf8',
                'tenant_description' => 'Workspace universal untuk tenant modular lintas model bisnis.',
            ],
        };
    }

    public static function installedModuleNames(): array
    {
        try {
            $modules = app('modules')->all();
        } catch (\Throwable) {
            return ['BaseFeature'];
        }

        return array_values(array_unique(array_map(
            static fn ($module) => $module->getName(),
            $modules
        )));
    }

    public static function defaultPackageCatalog(?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();
        $platformModules = static::platformBlueprint($platformType)['modules'];
        $activeModules = static::activeModules($platformType);

        return [
            'starter' => [
                'code' => 'starter',
                'label' => 'Starter',
                'description' => 'Paket awal untuk tenant baru yang butuh fondasi inti.',
                'price_monthly' => 149000,
                'billing_cycle' => 'monthly',
                'enabled' => true,
                'highlight' => false,
                'sort_order' => 1,
                'limits' => [
                    'max_admin_users' => 2,
                    'max_staff_users' => 5,
                    'max_customers' => 500,
                    'max_monthly_transactions' => 1000,
                ],
                'features' => [
                    'custom_domain' => false,
                    'advanced_reports' => false,
                    'api_access' => false,
                    'priority_support' => false,
                    'white_label' => false,
                ],
                'modules' => ['BaseFeature'],
                'billing_components' => static::defaultPackageBillingComponents(),
            ],
            'growth' => [
                'code' => 'growth',
                'label' => 'Growth',
                'description' => 'Paket operasional utama untuk tenant yang mulai scale.',
                'price_monthly' => 349000,
                'billing_cycle' => 'monthly',
                'enabled' => true,
                'highlight' => true,
                'sort_order' => 2,
                'limits' => [
                    'max_admin_users' => 8,
                    'max_staff_users' => 25,
                    'max_customers' => 5000,
                    'max_monthly_transactions' => 10000,
                ],
                'features' => [
                    'custom_domain' => true,
                    'advanced_reports' => true,
                    'api_access' => false,
                    'priority_support' => false,
                    'white_label' => true,
                ],
                'modules' => $platformModules,
                'billing_components' => static::defaultPackageBillingComponents(),
            ],
            'enterprise' => [
                'code' => 'enterprise',
                'label' => 'Enterprise',
                'description' => 'Paket tertinggi untuk tenant besar dengan kebutuhan fleksibel.',
                'price_monthly' => 799000,
                'billing_cycle' => 'monthly',
                'enabled' => true,
                'highlight' => false,
                'sort_order' => 3,
                'limits' => [
                    'max_admin_users' => null,
                    'max_staff_users' => null,
                    'max_customers' => null,
                    'max_monthly_transactions' => null,
                ],
                'features' => [
                    'custom_domain' => true,
                    'advanced_reports' => true,
                    'api_access' => true,
                    'priority_support' => true,
                    'white_label' => true,
                ],
                'modules' => $activeModules,
                'billing_components' => static::defaultPackageBillingComponents(),
            ],
        ];
    }

    public static function packageCatalog(?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();

        $stored = static::query()
            ->where('key', self::PACKAGE_CATALOG_KEY)
            ->value('value');

        $decoded = json_decode((string) $stored, true);

        if (! is_array($decoded) || $decoded === []) {
            return static::defaultPackageCatalog($platformType);
        }

        return static::normalizePackageCatalog($decoded, $platformType);
    }

    public static function setPackageCatalog(array $packages, ?string $platformType = null): void
    {
        $platformType ??= static::platformSaasType();
        $normalized = static::normalizePackageCatalog($packages, $platformType);

        static::query()->updateOrCreate(
            ['key' => self::PACKAGE_CATALOG_KEY],
            ['value' => json_encode($normalized, JSON_THROW_ON_ERROR)],
        );
    }

    public static function defaultPackageCode(?string $platformType = null): string
    {
        $platformType ??= static::platformSaasType();
        $packages = static::packageCatalog($platformType);

        $stored = static::query()
            ->where('key', self::DEFAULT_PACKAGE_CODE_KEY)
            ->value('value');

        if (is_string($stored) && isset($packages[$stored]) && ($packages[$stored]['enabled'] ?? false)) {
            return $stored;
        }

        $enabledFallback = Collection::make($packages)
            ->first(fn (array $package): bool => (bool) ($package['enabled'] ?? false));

        return (string) ($enabledFallback['code'] ?? array_key_first($packages) ?? 'starter');
    }

    public static function setDefaultPackageCode(string $packageCode, ?string $platformType = null): void
    {
        $platformType ??= static::platformSaasType();
        $packages = static::packageCatalog($platformType);
        $normalizedCode = isset($packages[$packageCode]) && ($packages[$packageCode]['enabled'] ?? false)
            ? $packageCode
            : static::defaultPackageCode($platformType);

        static::query()->updateOrCreate(
            ['key' => self::DEFAULT_PACKAGE_CODE_KEY],
            ['value' => $normalizedCode],
        );
    }

    public static function findPackage(string $packageCode, ?string $platformType = null): ?array
    {
        $packages = static::packageCatalog($platformType);

        return $packages[$packageCode] ?? null;
    }

    public static function hasPackage(string $packageCode, ?string $platformType = null): bool
    {
        return static::findPackage($packageCode, $platformType) !== null;
    }

    public static function activeModules(?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();

        $stored = static::query()
            ->where('key', self::ACTIVE_MODULES_KEY)
            ->value('value');

        $decoded = json_decode((string) $stored, true);

        if (! is_array($decoded) || $decoded === []) {
            return static::platformBlueprint($platformType)['modules'];
        }

        $catalogKeys = array_keys(static::moduleCatalog());
        $modules = collect($decoded)
            ->filter(fn ($module) => is_string($module) && in_array($module, $catalogKeys, true))
            ->prepend('BaseFeature')
            ->unique()
            ->values()
            ->all();

        return $modules === [] ? static::platformBlueprint($platformType)['modules'] : $modules;
    }

    public static function runtimeEnabledModules(?string $platformType = null): array
    {
        $installed = static::installedModuleNames();

        return array_values(array_filter(
            static::activeModules($platformType),
            static fn (string $moduleName) => in_array($moduleName, $installed, true)
        ));
    }

    public static function platformSaasType(): string
    {
        $value = static::query()
            ->where('key', self::PLATFORM_SAAS_TYPE_KEY)
            ->value('value');

        return in_array($value, static::availablePlatformTypes(), true)
            ? $value
            : 'universal';
    }

    public static function setPlatformSaasType(string $platformSaasType): void
    {
        static::query()->updateOrCreate(
            ['key' => self::PLATFORM_SAAS_TYPE_KEY],
            ['value' => $platformSaasType],
        );
    }

    public static function setActiveModules(array $modules): void
    {
        $catalogKeys = array_keys(static::moduleCatalog());

        $normalized = collect($modules)
            ->filter(fn ($module) => is_string($module) && in_array($module, $catalogKeys, true))
            ->prepend('BaseFeature')
            ->unique()
            ->values()
            ->all();

        static::query()->updateOrCreate(
            ['key' => self::ACTIVE_MODULES_KEY],
            ['value' => json_encode($normalized, JSON_THROW_ON_ERROR)],
        );
    }

    public static function syncActiveModulesForPlatform(string $platformType): void
    {
        static::setActiveModules(static::platformBlueprint($platformType)['modules']);
    }

    public static function settingValue(string $key, mixed $default = null): mixed
    {
        $value = static::query()
            ->where('key', $key)
            ->value('value');

        return $value ?? $default;
    }

    public static function setSettingValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    public static function jsonSetting(string $key, array $default = []): array
    {
        $stored = static::settingValue($key);
        $decoded = json_decode((string) $stored, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function setJsonSetting(string $key, array $value): void
    {
        static::setSettingValue($key, json_encode($value, JSON_THROW_ON_ERROR));
    }

    public static function encryptedJsonSetting(string $key, array $default = []): array
    {
        $stored = static::settingValue($key);

        if (! is_string($stored) || trim($stored) === '') {
            return $default;
        }

        try {
            $decrypted = Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $default;
        }

        $decoded = json_decode($decrypted, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function setEncryptedJsonSetting(string $key, array $value): void
    {
        static::setSettingValue($key, Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR)));
    }

    public static function defaultPaymentMethodSettings(): array
    {
        return [
            'qris' => [
                'enabled' => false,
                'provider_name' => 'Interactive QRIS',
                'api_key' => '',
                'merchant_id' => '',
                'nmid' => '',
                'base_url' => 'https://qris.interactive.co.id/restapi/qris',
                'use_tip' => 'no',
                'instructions' => 'Scan QRIS dinamis yang tampil lalu cek status pembayaran dari panel billing.',
            ],
            'manual_transfer' => [
                'enabled' => false,
                'bank_name' => '',
                'account_name' => '',
                'account_number' => '',
                'notes' => 'Transfer sesuai nominal invoice lalu konfirmasi dari panel pusat.',
                'bca_email_fetcher' => [
                    'enabled' => false,
                    'host' => '',
                    'port' => 993,
                    'encryption' => 'ssl',
                    'validate_certificate' => false,
                    'username' => '',
                    'password' => '',
                    'folder' => 'INBOX',
                    'sender_filter' => '',
                    'subject_keyword' => '',
                    'lookback_minutes' => 60,
                    'max_messages' => 20,
                    'unseen_only' => true,
                ],
            ],
        ];
    }

    public static function paymentMethodSettings(): array
    {
        return static::normalizePaymentMethodSettings(
            static::encryptedJsonSetting(self::PAYMENT_METHODS_KEY, static::defaultPaymentMethodSettings())
        );
    }

    public static function setPaymentMethodSettings(array $settings): void
    {
        static::setEncryptedJsonSetting(
            self::PAYMENT_METHODS_KEY,
            static::normalizePaymentMethodSettings($settings)
        );
    }

    public static function defaultNotificationChannelSettings(): array
    {
        return [
            'events' => [
                'billing_due_reminder' => true,
                'subscription_expiry_reminder' => true,
                'payment_success_alert' => true,
            ],
            'default_channels' => [
                'email' => false,
                'telegram' => false,
                'whatsapp' => false,
            ],
            'telegram' => [
                'enabled' => false,
                'bot_name' => '',
                'bot_token' => '',
                'default_chat_id' => '',
            ],
            'whatsapp_cloud' => [
                'enabled' => false,
                'access_token' => '',
                'phone_number_id' => '',
                'business_account_id' => '',
                'verify_token' => '',
                'default_recipient_phone' => '',
            ],
            'templates' => [
                'billing_reminder' => "Billing reminder scan {{scan_time}}\nOverdue: {{overdue_count}} tenant\nSubscription soon: {{expiring_count}} tenant",
                'payment_success' => "Pembayaran invoice {{invoice_number}} untuk tenant {{tenant_name}} sudah masuk dengan status {{payment_status}}.",
                'public_payment_note' => "Gunakan QRIS atau transfer manual sesuai instruksi di halaman ini. Simpan bukti pembayaran untuk verifikasi internal.",
            ],
        ];
    }

    public static function notificationChannelSettings(): array
    {
        return static::normalizeNotificationChannelSettings(
            static::encryptedJsonSetting(self::NOTIFICATION_CHANNELS_KEY, static::defaultNotificationChannelSettings())
        );
    }

    public static function setNotificationChannelSettings(array $settings): void
    {
        static::setEncryptedJsonSetting(
            self::NOTIFICATION_CHANNELS_KEY,
            static::normalizeNotificationChannelSettings($settings)
        );
    }

    public static function defaultAutomationSettings(): array
    {
        return [
            'billing_auto_generate_enabled' => true,
            'billing_auto_generate_time' => '00:10',
            'billing_reminder_scan_enabled' => true,
            'billing_reminder_scan_time' => '08:00',
            'subscription_reminder_days' => 7,
        ];
    }

    public static function automationSettings(): array
    {
        return static::normalizeAutomationSettings(
            static::jsonSetting(self::AUTOMATION_RULES_KEY, static::defaultAutomationSettings())
        );
    }

    public static function setAutomationSettings(array $settings): void
    {
        static::setJsonSetting(
            self::AUTOMATION_RULES_KEY,
            static::normalizeAutomationSettings($settings)
        );
    }

    public static function defaultPlatformExperienceCatalog(): array
    {
        return [
            'universal' => [
                'brand_name' => 'AirCloud Suite',
                'brand_label' => 'Modular Multi-Business SaaS',
                'badge' => 'Universal Suite',
                'headline' => 'Kelola banyak model bisnis SaaS dari satu platform modular.',
                'description' => 'Mode universal cocok untuk platform multi-produk. Landing, login, dan register menampilkan citra suite yang fleksibel untuk tenant lintas industri.',
                'primary_cta' => 'Mulai Tenant Baru',
                'secondary_cta' => 'Login Super Admin',
                'login_title' => 'Masuk ke control center platform.',
                'login_description' => 'Kelola tenant, blueprint produk, billing, payment methods, dan automation dari satu workspace pusat.',
                'register_title' => 'Bangun tenant modular baru',
                'register_description' => 'Onboarding tenant baru akan otomatis mengikuti blueprint universal yang aktif di pusat.',
                'feature_title' => 'Apa yang tenant dapatkan',
                'feature_description' => 'Produk tampil fleksibel, tapi provisioning tetap satu pintu dari central control center.',
                'visual_eyebrow' => 'Centralized provisioning',
                'visual_title' => 'Satu shell untuk banyak vertical',
                'visual_description' => 'Pilih vertical bisnis di pusat, lalu landing dan onboarding tenant ikut berubah tanpa pecah arsitektur.',
                'feature_one' => 'Landing dan auth surface mengikuti mode SaaS aktif',
                'feature_two' => 'Package, billing, dan automation tetap terhubung ke platform pusat',
                'feature_three' => 'Cocok untuk build multi-vertical SaaS dari satu codebase',
                'metric_one_label' => 'Control',
                'metric_one_value' => 'Centralized',
                'metric_two_label' => 'Provisioning',
                'metric_two_value' => 'Auto',
                'metric_three_label' => 'Experience',
                'metric_three_value' => 'Per Platform',
                'metric_four_label' => 'Architecture',
                'metric_four_value' => 'Modular',
                'accent' => '#38bdf8',
                'hero_image_url' => '',
                'auth_image_url' => '',
                'hero_highlights' => [
                    ['label' => 'Model', 'value' => 'Multi Vertical'],
                    ['label' => 'Provisioning', 'value' => 'Central Blueprint'],
                    ['label' => 'Go To Market', 'value' => 'One Codebase'],
                ],
                'solution_cards' => [
                    ['icon' => 'fa-cubes', 'title' => 'Multi-product shell', 'body' => 'Satu landing core bisa diposisikan ulang untuk beberapa vertical tanpa pecah arsitektur.'],
                    ['icon' => 'fa-sliders', 'title' => 'Preset by platform', 'body' => 'Package, module, billing, dan auth surface tetap membaca mode SaaS yang aktif di pusat.'],
                    ['icon' => 'fa-gear', 'title' => 'Operational control', 'body' => 'Superadmin tetap jadi titik kontrol untuk payment, notifications, automation, dan branding.'],
                ],
                'workflow_steps' => [
                    ['title' => 'Pilih vertical aktif', 'body' => 'Atur platform pusat ke vertical yang mau dijual tanpa ubah fondasi aplikasi.'],
                    ['title' => 'Poles experience publik', 'body' => 'Landing, login, dan register mengikuti preset visual serta copy yang paling relevan.'],
                    ['title' => 'Onboard tenant baru', 'body' => 'Tenant baru langsung ikut blueprint, module, package, dan flow operasional pusat.'],
                ],
                'auth_panels' => [
                    ['label' => 'Area', 'value' => 'Central Guard'],
                    ['label' => 'Audience', 'value' => 'Multi-business admin'],
                    ['label' => 'Flow', 'value' => 'Landing + Login + Register'],
                    ['label' => 'Control', 'value' => 'Blueprint, Billing, Automation'],
                ],
                'example_business_name' => 'AirCloud Ops Center',
                'example_subdomain' => 'aircloud-ops',
            ],
            'resto' => [
                'brand_name' => 'AirCloud Resto',
                'brand_label' => 'POS & Restaurant Operation SaaS',
                'badge' => 'Resto SaaS',
                'headline' => 'Jual sistem kasir, dapur, dan operasional resto dari satu platform.',
                'description' => 'Landing dan onboarding difokuskan untuk restoran: POS, meja, order, kitchen flow, dan laporan penjualan yang rapi.',
                'primary_cta' => 'Bangun Tenant Resto',
                'secondary_cta' => 'Masuk Panel Pusat',
                'login_title' => 'Masuk ke control room resto.',
                'login_description' => 'Kelola tenant restoran, package, billing, payment methods, dan automasi operasional dari satu workspace.',
                'register_title' => 'Mulai onboarding tenant resto',
                'register_description' => 'Tenant baru otomatis ikut preset operasional resto yang aktif di pusat.',
                'feature_title' => 'Siap untuk operasional restoran',
                'feature_description' => 'Experience publik dan auth page disusun supaya langsung terasa seperti SaaS vertikal restoran.',
                'visual_eyebrow' => 'Restaurant workflow',
                'visual_title' => 'Order, kitchen, dan outlet dalam satu alur',
                'visual_description' => 'Tampilan publik menonjolkan workflow resto modern dengan positioning yang lebih profesional di mata calon tenant.',
                'feature_one' => 'POS, meja, dan kitchen flow dalam satu workspace',
                'feature_two' => 'Onboarding tenant resto langsung ikut preset pusat',
                'feature_three' => 'Package dan billing bisa diarahkan ke model restoran',
                'metric_one_label' => 'Vertical',
                'metric_one_value' => 'Resto',
                'metric_two_label' => 'Focus',
                'metric_two_value' => 'POS & Kitchen',
                'metric_three_label' => 'Onboarding',
                'metric_three_value' => 'Ready',
                'metric_four_label' => 'Positioning',
                'metric_four_value' => 'Professional',
                'accent' => '#f97316',
                'hero_image_url' => '',
                'auth_image_url' => '',
                'hero_highlights' => [
                    ['label' => 'Vertical', 'value' => 'Restaurant Ops'],
                    ['label' => 'Focus', 'value' => 'POS, Kitchen, Table'],
                    ['label' => 'Buyer', 'value' => 'Cafe, Resto, Multi Outlet'],
                ],
                'solution_cards' => [
                    ['icon' => 'fa-cash-register', 'title' => 'POS cepat dipakai', 'body' => 'Cocok untuk jualan SaaS kasir restoran dengan checkout yang clean dan operasional outlet yang rapi.'],
                    ['icon' => 'fa-utensils', 'title' => 'Kitchen workflow', 'body' => 'Landing menjual alur order ke kitchen, status meja, dan sinkronisasi antar tim operasional.'],
                    ['icon' => 'fa-store', 'title' => 'Outlet growth', 'body' => 'Positioning visualnya pas buat resto yang mau naik level dari pencatatan manual ke sistem terpusat.'],
                ],
                'workflow_steps' => [
                    ['title' => 'Terima order', 'body' => 'Kasir, waiter, dan order channel masuk ke workflow yang sama.'],
                    ['title' => 'Proses kitchen', 'body' => 'Tiket produksi dan status menu bisa dipantau per outlet atau station.'],
                    ['title' => 'Pantau penjualan', 'body' => 'Owner dan tim operasional lihat performa harian tanpa ribet rekap manual.'],
                ],
                'auth_panels' => [
                    ['label' => 'Target Buyer', 'value' => 'Cafe & Multi Outlet'],
                    ['label' => 'Value', 'value' => 'POS + Kitchen Flow'],
                    ['label' => 'Onboarding', 'value' => 'Preset Menu & Outlet'],
                    ['label' => 'Tone', 'value' => 'Fast, Clean, Modern'],
                ],
                'example_business_name' => 'Rasa Nusantara Group',
                'example_subdomain' => 'rasa-nusantara',
            ],
            'hotel' => [
                'brand_name' => 'AirCloud Hospitality',
                'brand_label' => 'Hotel PMS & Reservation SaaS',
                'badge' => 'Hotel SaaS',
                'headline' => 'Siapkan PMS, reservasi, dan housekeeping untuk bisnis hotel.',
                'description' => 'Landing mode hotel menonjolkan reservasi, manajemen kamar, front office, dan housekeeping secara lebih premium.',
                'primary_cta' => 'Bangun Tenant Hotel',
                'secondary_cta' => 'Masuk Panel Pusat',
                'login_title' => 'Masuk ke control room hospitality.',
                'login_description' => 'Kontrol tenant hotel, paket subscription, invoice, payment setup, dan automation dari satu dashboard pusat.',
                'register_title' => 'Mulai onboarding tenant hotel',
                'register_description' => 'Tenant baru langsung mengikuti blueprint hotel dan modul hospitality yang aktif.',
                'feature_title' => 'Dirancang untuk operasional hotel',
                'feature_description' => 'Visual, copywriting, dan CTA dibuat lebih cocok untuk target pasar hospitality.',
                'visual_eyebrow' => 'Hospitality operations',
                'visual_title' => 'Reservasi, kamar, dan front office terhubung',
                'visual_description' => 'Cocok untuk menampilkan citra PMS modern yang lebih enterprise dan rapi.',
                'feature_one' => 'Reservasi kamar dan status housekeeping lebih jelas',
                'feature_two' => 'Landing dan auth page langsung bicara bahasa hospitality',
                'feature_three' => 'Tenant hotel baru otomatis ikut preset pusat',
                'metric_one_label' => 'Vertical',
                'metric_one_value' => 'Hospitality',
                'metric_two_label' => 'Focus',
                'metric_two_value' => 'PMS',
                'metric_three_label' => 'Experience',
                'metric_three_value' => 'Premium',
                'metric_four_label' => 'Workflow',
                'metric_four_value' => 'End-to-End',
                'accent' => '#8b5cf6',
                'hero_image_url' => '',
                'auth_image_url' => '',
                'hero_highlights' => [
                    ['label' => 'Vertical', 'value' => 'Hospitality'],
                    ['label' => 'Focus', 'value' => 'Reservation & PMS'],
                    ['label' => 'Buyer', 'value' => 'Hotel, Villa, Guest House'],
                ],
                'solution_cards' => [
                    ['icon' => 'fa-bed', 'title' => 'Room inventory', 'body' => 'Narasi landing menonjolkan reservasi kamar, inventori, dan status hunian yang lebih premium.'],
                    ['icon' => 'fa-bell-concierge', 'title' => 'Front office flow', 'body' => 'Pengalaman publik dan auth surface terasa cocok untuk operasional resepsionis, check-in, dan checkout.'],
                    ['icon' => 'fa-soap', 'title' => 'Housekeeping clarity', 'body' => 'Preset hotel menempatkan housekeeping sebagai bagian penting dari workflow, bukan sekadar tambahan.'],
                ],
                'workflow_steps' => [
                    ['title' => 'Terima reservasi', 'body' => 'Permintaan kamar dan ketersediaan masuk ke satu control flow yang rapi.'],
                    ['title' => 'Kelola stay guest', 'body' => 'Front office, pembayaran, dan status kamar tetap sinkron selama masa inap.'],
                    ['title' => 'Rapikan turnaround', 'body' => 'Housekeeping dan kesiapan kamar lebih mudah dikontrol untuk okupansi berikutnya.'],
                ],
                'auth_panels' => [
                    ['label' => 'Target Buyer', 'value' => 'Hotel & Villa'],
                    ['label' => 'Value', 'value' => 'PMS + Reservation'],
                    ['label' => 'Onboarding', 'value' => 'Preset Room & Front Office'],
                    ['label' => 'Tone', 'value' => 'Premium & Structured'],
                ],
                'example_business_name' => 'Hotel Sagara Residence',
                'example_subdomain' => 'sagara-residence',
            ],
            'tirta' => [
                'brand_name' => 'AirCloud Tirta',
                'brand_label' => 'Utility Billing SaaS',
                'badge' => 'Tirta SaaS',
                'headline' => 'Kelola pembacaan meter, tagihan, dan pelanggan air dengan lebih rapi.',
                'description' => 'Mode tirta difokuskan untuk billing utilitas air dengan positioning yang cocok untuk PDAM mini, BUMDes, dan operator lokal.',
                'primary_cta' => 'Bangun Tenant Tirta',
                'secondary_cta' => 'Masuk Panel Pusat',
                'login_title' => 'Masuk ke control room tirta.',
                'login_description' => 'Atur tenant utilitas, package billing, payment methods, reminder, dan automasi operasional dari panel pusat.',
                'register_title' => 'Mulai onboarding tenant tirta',
                'register_description' => 'Tenant baru akan langsung ikut blueprint pembacaan meter, pelanggan, dan billing air.',
                'feature_title' => 'Siap untuk billing utilitas air',
                'feature_description' => 'Visual dan copy diarahkan ke operasional pelanggan, meter reading, tagihan, dan pembayaran.',
                'visual_eyebrow' => 'Utility billing',
                'visual_title' => 'Pelanggan, meter, dan tagihan dalam satu ritme',
                'visual_description' => 'Citra produk jadi lebih spesifik untuk vertical air, bukan sekadar landing SaaS generik.',
                'feature_one' => 'Pembacaan meter dan billing pelanggan lebih terstruktur',
                'feature_two' => 'Onboarding tenant tirta otomatis ikut preset pusat',
                'feature_three' => 'Cocok untuk operator air lokal yang butuh sistem rapi',
                'metric_one_label' => 'Vertical',
                'metric_one_value' => 'Tirta',
                'metric_two_label' => 'Focus',
                'metric_two_value' => 'Billing Air',
                'metric_three_label' => 'Provisioning',
                'metric_three_value' => 'Central',
                'metric_four_label' => 'Audience',
                'metric_four_value' => 'Utility',
                'accent' => '#06b6d4',
                'hero_image_url' => '',
                'auth_image_url' => '',
                'hero_highlights' => [
                    ['label' => 'Vertical', 'value' => 'Utility Water'],
                    ['label' => 'Focus', 'value' => 'Meter & Billing'],
                    ['label' => 'Buyer', 'value' => 'BUMDes, PDAM Mini, Operator Lokal'],
                ],
                'solution_cards' => [
                    ['icon' => 'fa-gauge-high', 'title' => 'Meter reading flow', 'body' => 'Preset tirta menonjolkan alur catat meter, verifikasi pemakaian, dan siklus billing pelanggan.'],
                    ['icon' => 'fa-file-invoice-dollar', 'title' => 'Tagihan utilitas', 'body' => 'Cocok buat positioning billing air bulanan yang butuh rapi, jelas, dan mudah ditagih ulang.'],
                    ['icon' => 'fa-users-between-lines', 'title' => 'Pelanggan terkelola', 'body' => 'Landing terasa relevan untuk operator lokal yang mengurus banyak sambungan dan tunggakan.'],
                ],
                'workflow_steps' => [
                    ['title' => 'Catat meter', 'body' => 'Petugas atau admin input pemakaian pelanggan sebagai dasar tagihan.'],
                    ['title' => 'Generate tagihan', 'body' => 'Sistem menyusun billing periodik per pelanggan berdasarkan siklus yang aktif.'],
                    ['title' => 'Pantau pembayaran', 'body' => 'Tunggakan, status bayar, dan reminder bisa diposisikan sebagai workflow inti platform.'],
                ],
                'auth_panels' => [
                    ['label' => 'Target Buyer', 'value' => 'BUMDes & Operator Air'],
                    ['label' => 'Value', 'value' => 'Meter to Invoice'],
                    ['label' => 'Onboarding', 'value' => 'Preset Pelanggan & Billing'],
                    ['label' => 'Tone', 'value' => 'Clean & Utility Focused'],
                ],
                'example_business_name' => 'Tirta Sejahtera Mandiri',
                'example_subdomain' => 'tirta-sejahtera',
            ],
            'netbilling' => [
                'brand_name' => 'AirCloud NetBilling',
                'brand_label' => 'ISP & Subscription Billing SaaS',
                'badge' => 'Netbilling SaaS',
                'headline' => 'Operasikan billing ISP dan paket internet dari panel yang sama.',
                'description' => 'Mode netbilling menonjolkan kebutuhan WISP atau RT/RW Net: paket pelanggan, aktivasi, isolir, dan billing berulang.',
                'primary_cta' => 'Bangun Tenant ISP',
                'secondary_cta' => 'Masuk Panel Pusat',
                'login_title' => 'Masuk ke control room netbilling.',
                'login_description' => 'Kelola tenant ISP, recurring billing, payment setup, reminder, dan operasional pusat dari satu workspace.',
                'register_title' => 'Mulai onboarding tenant ISP',
                'register_description' => 'Tenant baru langsung mengikuti blueprint netbilling yang aktif di pusat.',
                'feature_title' => 'Siap untuk billing ISP',
                'feature_description' => 'Visual dan CTA dibikin lebih cocok untuk bisnis langganan internet dan operasional pelanggan.',
                'visual_eyebrow' => 'Recurring connectivity',
                'visual_title' => 'Paket, pelanggan aktif, dan billing berulang',
                'visual_description' => 'Landing terasa lebih profesional untuk target WISP dan operator internet lokal.',
                'feature_one' => 'Kelola paket internet dan pelanggan berlangganan',
                'feature_two' => 'Reminder dan recurring billing lebih mudah diposisikan',
                'feature_three' => 'Tenant baru otomatis mengikuti preset netbilling pusat',
                'metric_one_label' => 'Vertical',
                'metric_one_value' => 'ISP',
                'metric_two_label' => 'Focus',
                'metric_two_value' => 'Recurring Billing',
                'metric_three_label' => 'Workflow',
                'metric_three_value' => 'Subscription',
                'metric_four_label' => 'Audience',
                'metric_four_value' => 'WISP',
                'accent' => '#22c55e',
                'hero_image_url' => '',
                'auth_image_url' => '',
                'hero_highlights' => [
                    ['label' => 'Vertical', 'value' => 'ISP Billing'],
                    ['label' => 'Focus', 'value' => 'Recurring Subscription'],
                    ['label' => 'Buyer', 'value' => 'WISP, RT/RW Net, ISP Lokal'],
                ],
                'solution_cards' => [
                    ['icon' => 'fa-wifi', 'title' => 'Paket langganan', 'body' => 'Netbilling preset memposisikan platform untuk jualan paket internet dan recurring billing pelanggan.'],
                    ['icon' => 'fa-arrows-rotate', 'title' => 'Billing berulang', 'body' => 'Visual publik dan auth page lebih cocok untuk bisnis dengan penagihan bulanan terus-menerus.'],
                    ['icon' => 'fa-signal', 'title' => 'Operasional pelanggan', 'body' => 'Narasi platform lebih nyambung buat WISP yang butuh aktivasi, isolir, dan pemantauan status pelanggan.'],
                ],
                'workflow_steps' => [
                    ['title' => 'Aktifkan pelanggan', 'body' => 'Paket, status aktif, dan masa langganan bisa diposisikan sebagai workflow utama.'],
                    ['title' => 'Tagih recurring', 'body' => 'Invoice periodik dan reminder jadi inti value proposition untuk operator internet lokal.'],
                    ['title' => 'Jaga retention', 'body' => 'Status bayar dan layanan aktif lebih mudah dipantau sebelum pelanggan drop.'],
                ],
                'auth_panels' => [
                    ['label' => 'Target Buyer', 'value' => 'WISP & RT/RW Net'],
                    ['label' => 'Value', 'value' => 'Subscription Billing'],
                    ['label' => 'Onboarding', 'value' => 'Preset Paket & Customer'],
                    ['label' => 'Tone', 'value' => 'Network-first & Efficient'],
                ],
                'example_business_name' => 'NetFiber Cakrawala',
                'example_subdomain' => 'netfiber-cakrawala',
            ],
        ];
    }

    public static function platformExperienceCatalog(): array
    {
        $defaults = static::defaultPlatformExperienceCatalog();
        $stored = static::jsonSetting(self::PLATFORM_EXPERIENCE_KEY, $defaults);

        return Collection::make($defaults)
            ->mapWithKeys(fn (array $_definition, string $platformType): array => [
                $platformType => static::normalizePlatformExperience(
                    is_array($stored[$platformType] ?? null) ? $stored[$platformType] : [],
                    $platformType
                ),
            ])
            ->all();
    }

    public static function platformExperience(?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();
        $catalog = static::platformExperienceCatalog();

        return $catalog[$platformType] ?? $catalog['universal'];
    }

    public static function setPlatformExperience(string $platformType, array $experience): void
    {
        $catalog = static::platformExperienceCatalog();
        $catalog[$platformType] = static::normalizePlatformExperience($experience, $platformType);

        static::setJsonSetting(self::PLATFORM_EXPERIENCE_KEY, $catalog);
    }

    public static function moduleCatalogForView(?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();
        $catalog = static::moduleCatalog();
        $installed = static::installedModuleNames();
        $active = static::activeModules($platformType);

        return Collection::make($catalog)
            ->map(function (array $definition, string $moduleName) use ($installed, $active, $platformType): array {
                return array_merge($definition, [
                    'name' => $moduleName,
                    'installed' => in_array($moduleName, $installed, true),
                    'selected' => in_array($moduleName, $active, true),
                    'recommended' => in_array($platformType, $definition['platforms'], true),
                ]);
            })
            ->values()
            ->all();
    }

    public static function packageCatalogForView(?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();
        $defaultPackageCode = static::defaultPackageCode($platformType);

        return Collection::make(static::packageCatalog($platformType))
            ->map(function (array $package) use ($defaultPackageCode): array {
                return array_merge($package, [
                    'is_default' => $package['code'] === $defaultPackageCode,
                    'billing_preview' => static::packageBillingEstimate($package),
                ]);
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    public static function packageBillingEstimate(array $package): array
    {
        $components = static::normalizePackageBillingComponents((array) data_get($package, 'billing_components', []));
        $monthlyTotal = 0;
        $setupFee = 0;

        foreach ($components as $componentKey => $component) {
            if (! ($component['enabled'] ?? false)) {
                continue;
            }

            if ($componentKey === 'setup_fee') {
                $setupFee += (int) ($component['amount'] ?? 0);
                continue;
            }

            if (($component['kind'] ?? 'flat') === 'percentage_usage') {
                $monthlyTotal += (int) round(((float) ($component['sample_amount'] ?? 0) * (float) ($component['rate'] ?? 0)) / 100);
                continue;
            }

            if (($component['kind'] ?? 'flat') === 'fixed_usage') {
                $monthlyTotal += (int) ($component['amount'] ?? 0) * (int) ($component['sample_qty'] ?? 0);
                continue;
            }

            $monthlyTotal += (int) ($component['amount'] ?? 0);
        }

        return [
            'setup_fee' => $setupFee,
            'monthly_total' => $monthlyTotal,
            'first_invoice_total' => $setupFee + $monthlyTotal,
        ];
    }

    public static function packageBillingInvoice(array $package, array $usage = [], bool $includeSetupFee = false): array
    {
        $components = static::normalizePackageBillingComponents((array) data_get($package, 'billing_components', []));
        $usage = [
            'customers' => max((int) data_get($usage, 'customers', 0), 0),
            'successful_transactions' => max((int) data_get($usage, 'successful_transactions', 0), 0),
            'checkouts' => max((int) data_get($usage, 'checkouts', 0), 0),
            'transaction_amount' => max((int) data_get($usage, 'transaction_amount', 0), 0),
        ];
        $lines = [];
        $setupFee = 0;
        $monthlyTotal = 0;

        foreach ($components as $componentKey => $component) {
            if (! ($component['enabled'] ?? false)) {
                continue;
            }

            $lineTotal = 0;
            $quantity = 1;

            if ($componentKey === 'setup_fee') {
                $lineTotal = (int) ($component['amount'] ?? 0);
                $quantity = 1;
                $setupFee = $lineTotal;
            } elseif ($componentKey === 'monthly_base') {
                $lineTotal = (int) ($component['amount'] ?? 0);
            } elseif ($componentKey === 'per_customer') {
                $quantity = $usage['customers'];
                $lineTotal = (int) ($component['amount'] ?? 0) * $quantity;
            } elseif ($componentKey === 'per_success_transaction') {
                $quantity = $usage['successful_transactions'];
                $lineTotal = (int) ($component['amount'] ?? 0) * $quantity;
            } elseif ($componentKey === 'per_checkout') {
                $quantity = $usage['checkouts'];
                $lineTotal = (int) ($component['amount'] ?? 0) * $quantity;
            } elseif ($componentKey === 'transaction_percentage') {
                $quantity = $usage['transaction_amount'];
                $lineTotal = (int) round($quantity * ((float) ($component['rate'] ?? 0) / 100));
            }

            if ($componentKey !== 'setup_fee') {
                $monthlyTotal += $lineTotal;
            }

            $lines[$componentKey] = [
                'key' => $componentKey,
                'label' => static::packageBillingComponentCatalog()[$componentKey]['label'] ?? $componentKey,
                'kind' => $component['kind'] ?? 'flat',
                'quantity' => $quantity,
                'amount' => (int) ($component['amount'] ?? 0),
                'rate' => (float) ($component['rate'] ?? 0),
                'total' => $lineTotal,
            ];
        }

        return [
            'usage' => $usage,
            'lines' => $lines,
            'setup_fee' => $setupFee,
            'monthly_total' => $monthlyTotal,
            'invoice_total' => $monthlyTotal + ($includeSetupFee ? $setupFee : 0),
        ];
    }

    protected static function normalizePackageCatalog(array $packages, ?string $platformType = null): array
    {
        $platformType ??= static::platformSaasType();
        $defaults = static::defaultPackageCatalog($platformType);
        $featureKeys = array_keys(static::packageFeatureCatalog());
        $moduleKeys = array_keys(static::moduleCatalog());
        $billingKeys = array_keys(static::packageBillingComponentCatalog($platformType));
        $normalized = Collection::make($packages)
            ->filter(fn ($definition, $packageCode): bool => is_array($definition) && is_string($packageCode) && trim($packageCode) !== '')
            ->mapWithKeys(function (array $incoming, string $packageCode) use ($defaults, $featureKeys, $moduleKeys, $billingKeys, $platformType): array {
                $packageCode = strtolower(trim($packageCode));
                $defaultPackage = $defaults[$packageCode] ?? static::packageSkeleton($packageCode);

                $features = Collection::make($featureKeys)
                    ->mapWithKeys(fn (string $featureKey): array => [
                        $featureKey => (bool) data_get($incoming, 'features.' . $featureKey, $defaultPackage['features'][$featureKey]),
                    ])
                    ->all();

                $modules = Collection::make(data_get($incoming, 'modules', $defaultPackage['modules']))
                    ->filter(fn ($module) => is_string($module) && in_array($module, $moduleKeys, true))
                    ->prepend('BaseFeature')
                    ->unique()
                    ->values()
                    ->all();

                $billingDefaults = is_array(data_get($defaultPackage, 'billing_components'))
                    ? $defaultPackage['billing_components']
                    : static::defaultPackageBillingComponents();

                $billingComponents = static::normalizePackageBillingComponents(
                    Collection::make($billingKeys)
                        ->mapWithKeys(fn (string $billingKey): array => [
                            $billingKey => is_array(data_get($incoming, 'billing_components.' . $billingKey))
                                ? data_get($incoming, 'billing_components.' . $billingKey)
                                : ($billingDefaults[$billingKey] ?? []),
                        ])
                        ->all(),
                    $platformType
                );

                return [
                    $packageCode => [
                        'code' => $packageCode,
                        'label' => trim((string) data_get($incoming, 'label', $defaultPackage['label'])) ?: $defaultPackage['label'],
                        'description' => trim((string) data_get($incoming, 'description', $defaultPackage['description'])),
                        'price_monthly' => max((int) data_get($incoming, 'price_monthly', $defaultPackage['price_monthly']), 0),
                        'billing_cycle' => in_array(data_get($incoming, 'billing_cycle'), ['monthly', 'quarterly', 'yearly'], true)
                            ? (string) data_get($incoming, 'billing_cycle')
                            : $defaultPackage['billing_cycle'],
                        'enabled' => (bool) data_get($incoming, 'enabled', $defaultPackage['enabled']),
                        'highlight' => (bool) data_get($incoming, 'highlight', $defaultPackage['highlight']),
                        'sort_order' => max((int) data_get($incoming, 'sort_order', $defaultPackage['sort_order']), 1),
                        'limits' => [
                            'max_admin_users' => static::normalizeNullableLimit(data_get($incoming, 'limits.max_admin_users', $defaultPackage['limits']['max_admin_users'])),
                            'max_staff_users' => static::normalizeNullableLimit(data_get($incoming, 'limits.max_staff_users', $defaultPackage['limits']['max_staff_users'])),
                            'max_customers' => static::normalizeNullableLimit(data_get($incoming, 'limits.max_customers', $defaultPackage['limits']['max_customers'])),
                            'max_monthly_transactions' => static::normalizeNullableLimit(data_get($incoming, 'limits.max_monthly_transactions', $defaultPackage['limits']['max_monthly_transactions'])),
                        ],
                        'features' => $features,
                        'modules' => $modules,
                        'billing_components' => $billingComponents,
                    ],
                ];
            })
            ->sortBy('sort_order')
            ->all();

        return $normalized !== []
            ? $normalized
            : $defaults;
    }

    protected static function packageSkeleton(string $packageCode): array
    {
        return [
            'code' => $packageCode,
            'label' => ucfirst(str_replace(['-', '_'], ' ', $packageCode)),
            'description' => '',
            'price_monthly' => 0,
            'billing_cycle' => 'monthly',
            'enabled' => true,
            'highlight' => false,
            'sort_order' => 99,
            'limits' => [
                'max_admin_users' => null,
                'max_staff_users' => null,
                'max_customers' => null,
                'max_monthly_transactions' => null,
            ],
            'features' => Collection::make(array_keys(static::packageFeatureCatalog()))
                ->mapWithKeys(fn (string $featureKey): array => [$featureKey => false])
                ->all(),
            'modules' => ['BaseFeature'],
            'billing_components' => static::defaultPackageBillingComponents(),
        ];
    }

    protected static function defaultPackageBillingComponents(): array
    {
        return [
            'setup_fee' => [
                'enabled' => false,
                'kind' => 'flat',
                'amount' => 0,
            ],
            'monthly_base' => [
                'enabled' => false,
                'kind' => 'flat',
                'amount' => 0,
            ],
            'per_customer' => [
                'enabled' => false,
                'kind' => 'fixed_usage',
                'amount' => 0,
                'sample_qty' => 0,
            ],
            'per_success_transaction' => [
                'enabled' => false,
                'kind' => 'fixed_usage',
                'amount' => 0,
                'sample_qty' => 0,
            ],
            'per_checkout' => [
                'enabled' => false,
                'kind' => 'fixed_usage',
                'amount' => 0,
                'sample_qty' => 0,
            ],
            'transaction_percentage' => [
                'enabled' => false,
                'kind' => 'percentage_usage',
                'rate' => 0,
                'sample_amount' => 0,
            ],
        ];
    }

    protected static function normalizePackageBillingComponents(array $components, ?string $platformType = null): array
    {
        $catalog = static::packageBillingComponentCatalog($platformType);
        $defaults = static::defaultPackageBillingComponents();

        return Collection::make($catalog)
            ->mapWithKeys(function (array $definition, string $componentKey) use ($components, $defaults): array {
                $incoming = is_array($components[$componentKey] ?? null)
                    ? $components[$componentKey]
                    : [];
                $default = $defaults[$componentKey] ?? [];

                return [
                    $componentKey => [
                        'enabled' => (bool) data_get($incoming, 'enabled', $default['enabled'] ?? false),
                        'kind' => (string) ($definition['kind'] ?? ($default['kind'] ?? 'flat')),
                        'amount' => max((int) data_get($incoming, 'amount', $default['amount'] ?? 0), 0),
                        'rate' => max((float) data_get($incoming, 'rate', $default['rate'] ?? 0), 0),
                        'sample_qty' => max((int) data_get($incoming, 'sample_qty', $default['sample_qty'] ?? 0), 0),
                        'sample_amount' => max((int) data_get($incoming, 'sample_amount', $default['sample_amount'] ?? 0), 0),
                    ],
                ];
            })
            ->all();
    }

    protected static function normalizeNullableLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max((int) $value, 1);
    }

    protected static function normalizePaymentMethodSettings(array $settings): array
    {
        $defaults = static::defaultPaymentMethodSettings();

        return [
            'qris' => [
                'enabled' => (bool) data_get($settings, 'qris.enabled', $defaults['qris']['enabled']),
                'provider_name' => trim((string) data_get($settings, 'qris.provider_name', $defaults['qris']['provider_name'])),
                'api_key' => trim((string) data_get($settings, 'qris.api_key', $defaults['qris']['api_key'])),
                'merchant_id' => trim((string) data_get($settings, 'qris.merchant_id', $defaults['qris']['merchant_id'])),
                'nmid' => trim((string) data_get($settings, 'qris.nmid', $defaults['qris']['nmid'])),
                'base_url' => trim((string) data_get($settings, 'qris.base_url', $defaults['qris']['base_url'])),
                'use_tip' => data_get($settings, 'qris.use_tip') === 'yes' ? 'yes' : 'no',
                'instructions' => trim((string) data_get($settings, 'qris.instructions', $defaults['qris']['instructions'])),
            ],
            'manual_transfer' => [
                'enabled' => (bool) data_get($settings, 'manual_transfer.enabled', $defaults['manual_transfer']['enabled']),
                'bank_name' => trim((string) data_get($settings, 'manual_transfer.bank_name', $defaults['manual_transfer']['bank_name'])),
                'account_name' => trim((string) data_get($settings, 'manual_transfer.account_name', $defaults['manual_transfer']['account_name'])),
                'account_number' => trim((string) data_get($settings, 'manual_transfer.account_number', $defaults['manual_transfer']['account_number'])),
                'notes' => trim((string) data_get($settings, 'manual_transfer.notes', $defaults['manual_transfer']['notes'])),
                'bca_email_fetcher' => [
                    'enabled' => (bool) data_get($settings, 'manual_transfer.bca_email_fetcher.enabled', data_get($defaults, 'manual_transfer.bca_email_fetcher.enabled', false)),
                    'host' => trim((string) data_get($settings, 'manual_transfer.bca_email_fetcher.host', data_get($defaults, 'manual_transfer.bca_email_fetcher.host', ''))),
                    'port' => max((int) data_get($settings, 'manual_transfer.bca_email_fetcher.port', data_get($defaults, 'manual_transfer.bca_email_fetcher.port', 993)), 1),
                    'encryption' => in_array(data_get($settings, 'manual_transfer.bca_email_fetcher.encryption'), ['ssl', 'tls', 'none'], true)
                        ? (string) data_get($settings, 'manual_transfer.bca_email_fetcher.encryption')
                        : (string) data_get($defaults, 'manual_transfer.bca_email_fetcher.encryption', 'ssl'),
                    'validate_certificate' => (bool) data_get($settings, 'manual_transfer.bca_email_fetcher.validate_certificate', data_get($defaults, 'manual_transfer.bca_email_fetcher.validate_certificate', false)),
                    'username' => trim((string) data_get($settings, 'manual_transfer.bca_email_fetcher.username', data_get($defaults, 'manual_transfer.bca_email_fetcher.username', ''))),
                    'password' => trim((string) data_get($settings, 'manual_transfer.bca_email_fetcher.password', data_get($defaults, 'manual_transfer.bca_email_fetcher.password', ''))),
                    'folder' => trim((string) data_get($settings, 'manual_transfer.bca_email_fetcher.folder', data_get($defaults, 'manual_transfer.bca_email_fetcher.folder', 'INBOX'))) ?: 'INBOX',
                    'sender_filter' => trim((string) data_get($settings, 'manual_transfer.bca_email_fetcher.sender_filter', data_get($defaults, 'manual_transfer.bca_email_fetcher.sender_filter', ''))),
                    'subject_keyword' => trim((string) data_get($settings, 'manual_transfer.bca_email_fetcher.subject_keyword', data_get($defaults, 'manual_transfer.bca_email_fetcher.subject_keyword', ''))),
                    'lookback_minutes' => max((int) data_get($settings, 'manual_transfer.bca_email_fetcher.lookback_minutes', data_get($defaults, 'manual_transfer.bca_email_fetcher.lookback_minutes', 60)), 1),
                    'max_messages' => max((int) data_get($settings, 'manual_transfer.bca_email_fetcher.max_messages', data_get($defaults, 'manual_transfer.bca_email_fetcher.max_messages', 20)), 1),
                    'unseen_only' => (bool) data_get($settings, 'manual_transfer.bca_email_fetcher.unseen_only', data_get($defaults, 'manual_transfer.bca_email_fetcher.unseen_only', true)),
                ],
            ],
        ];
    }

    protected static function normalizeNotificationChannelSettings(array $settings): array
    {
        $defaults = static::defaultNotificationChannelSettings();

        return [
            'events' => [
                'billing_due_reminder' => (bool) data_get($settings, 'events.billing_due_reminder', $defaults['events']['billing_due_reminder']),
                'subscription_expiry_reminder' => (bool) data_get($settings, 'events.subscription_expiry_reminder', $defaults['events']['subscription_expiry_reminder']),
                'payment_success_alert' => (bool) data_get($settings, 'events.payment_success_alert', $defaults['events']['payment_success_alert']),
            ],
            'default_channels' => [
                'email' => (bool) data_get($settings, 'default_channels.email', $defaults['default_channels']['email']),
                'telegram' => (bool) data_get($settings, 'default_channels.telegram', $defaults['default_channels']['telegram']),
                'whatsapp' => (bool) data_get($settings, 'default_channels.whatsapp', $defaults['default_channels']['whatsapp']),
            ],
            'telegram' => [
                'enabled' => (bool) data_get($settings, 'telegram.enabled', $defaults['telegram']['enabled']),
                'bot_name' => trim((string) data_get($settings, 'telegram.bot_name', $defaults['telegram']['bot_name'])),
                'bot_token' => trim((string) data_get($settings, 'telegram.bot_token', $defaults['telegram']['bot_token'])),
                'default_chat_id' => trim((string) data_get($settings, 'telegram.default_chat_id', $defaults['telegram']['default_chat_id'])),
            ],
            'whatsapp_cloud' => [
                'enabled' => (bool) data_get($settings, 'whatsapp_cloud.enabled', $defaults['whatsapp_cloud']['enabled']),
                'access_token' => trim((string) data_get($settings, 'whatsapp_cloud.access_token', $defaults['whatsapp_cloud']['access_token'])),
                'phone_number_id' => trim((string) data_get($settings, 'whatsapp_cloud.phone_number_id', $defaults['whatsapp_cloud']['phone_number_id'])),
                'business_account_id' => trim((string) data_get($settings, 'whatsapp_cloud.business_account_id', $defaults['whatsapp_cloud']['business_account_id'])),
                'verify_token' => trim((string) data_get($settings, 'whatsapp_cloud.verify_token', $defaults['whatsapp_cloud']['verify_token'])),
                'default_recipient_phone' => preg_replace('/\D+/', '', (string) data_get($settings, 'whatsapp_cloud.default_recipient_phone', $defaults['whatsapp_cloud']['default_recipient_phone'])) ?? '',
            ],
            'templates' => [
                'billing_reminder' => trim((string) data_get($settings, 'templates.billing_reminder', $defaults['templates']['billing_reminder'])),
                'payment_success' => trim((string) data_get($settings, 'templates.payment_success', $defaults['templates']['payment_success'])),
                'public_payment_note' => trim((string) data_get($settings, 'templates.public_payment_note', $defaults['templates']['public_payment_note'])),
            ],
        ];
    }

    protected static function normalizeAutomationSettings(array $settings): array
    {
        $defaults = static::defaultAutomationSettings();

        return [
            'billing_auto_generate_enabled' => (bool) data_get($settings, 'billing_auto_generate_enabled', $defaults['billing_auto_generate_enabled']),
            'billing_auto_generate_time' => static::normalizeDailyTime(
                (string) data_get($settings, 'billing_auto_generate_time', $defaults['billing_auto_generate_time']),
                $defaults['billing_auto_generate_time']
            ),
            'billing_reminder_scan_enabled' => (bool) data_get($settings, 'billing_reminder_scan_enabled', $defaults['billing_reminder_scan_enabled']),
            'billing_reminder_scan_time' => static::normalizeDailyTime(
                (string) data_get($settings, 'billing_reminder_scan_time', $defaults['billing_reminder_scan_time']),
                $defaults['billing_reminder_scan_time']
            ),
            'subscription_reminder_days' => max((int) data_get($settings, 'subscription_reminder_days', $defaults['subscription_reminder_days']), 1),
        ];
    }

    protected static function normalizePlatformExperience(array $settings, string $platformType): array
    {
        $defaults = static::defaultPlatformExperienceCatalog()[$platformType] ?? static::defaultPlatformExperienceCatalog()['universal'];

        return [
            'brand_name' => trim((string) data_get($settings, 'brand_name', $defaults['brand_name'])),
            'brand_label' => trim((string) data_get($settings, 'brand_label', $defaults['brand_label'])),
            'badge' => trim((string) data_get($settings, 'badge', $defaults['badge'])),
            'headline' => trim((string) data_get($settings, 'headline', $defaults['headline'])),
            'description' => trim((string) data_get($settings, 'description', $defaults['description'])),
            'primary_cta' => trim((string) data_get($settings, 'primary_cta', $defaults['primary_cta'])),
            'secondary_cta' => trim((string) data_get($settings, 'secondary_cta', $defaults['secondary_cta'])),
            'login_title' => trim((string) data_get($settings, 'login_title', $defaults['login_title'])),
            'login_description' => trim((string) data_get($settings, 'login_description', $defaults['login_description'])),
            'register_title' => trim((string) data_get($settings, 'register_title', $defaults['register_title'])),
            'register_description' => trim((string) data_get($settings, 'register_description', $defaults['register_description'])),
            'feature_title' => trim((string) data_get($settings, 'feature_title', $defaults['feature_title'])),
            'feature_description' => trim((string) data_get($settings, 'feature_description', $defaults['feature_description'])),
            'visual_eyebrow' => trim((string) data_get($settings, 'visual_eyebrow', $defaults['visual_eyebrow'])),
            'visual_title' => trim((string) data_get($settings, 'visual_title', $defaults['visual_title'])),
            'visual_description' => trim((string) data_get($settings, 'visual_description', $defaults['visual_description'])),
            'feature_one' => trim((string) data_get($settings, 'feature_one', $defaults['feature_one'])),
            'feature_two' => trim((string) data_get($settings, 'feature_two', $defaults['feature_two'])),
            'feature_three' => trim((string) data_get($settings, 'feature_three', $defaults['feature_three'])),
            'metric_one_label' => trim((string) data_get($settings, 'metric_one_label', $defaults['metric_one_label'])),
            'metric_one_value' => trim((string) data_get($settings, 'metric_one_value', $defaults['metric_one_value'])),
            'metric_two_label' => trim((string) data_get($settings, 'metric_two_label', $defaults['metric_two_label'])),
            'metric_two_value' => trim((string) data_get($settings, 'metric_two_value', $defaults['metric_two_value'])),
            'metric_three_label' => trim((string) data_get($settings, 'metric_three_label', $defaults['metric_three_label'])),
            'metric_three_value' => trim((string) data_get($settings, 'metric_three_value', $defaults['metric_three_value'])),
            'metric_four_label' => trim((string) data_get($settings, 'metric_four_label', $defaults['metric_four_label'])),
            'metric_four_value' => trim((string) data_get($settings, 'metric_four_value', $defaults['metric_four_value'])),
            'accent' => static::normalizeHexColor(
                trim((string) data_get($settings, 'accent', $defaults['accent'])),
                $defaults['accent']
            ),
            'hero_image_url' => trim((string) data_get($settings, 'hero_image_url', $defaults['hero_image_url'])),
            'auth_image_url' => trim((string) data_get($settings, 'auth_image_url', $defaults['auth_image_url'])),
            'hero_highlights' => static::normalizePlatformExperienceItems(
                is_array(data_get($settings, 'hero_highlights')) ? data_get($settings, 'hero_highlights') : [],
                $defaults['hero_highlights'] ?? [],
                ['label', 'value']
            ),
            'solution_cards' => static::normalizePlatformExperienceItems(
                is_array(data_get($settings, 'solution_cards')) ? data_get($settings, 'solution_cards') : [],
                $defaults['solution_cards'] ?? [],
                ['icon', 'title', 'body']
            ),
            'workflow_steps' => static::normalizePlatformExperienceItems(
                is_array(data_get($settings, 'workflow_steps')) ? data_get($settings, 'workflow_steps') : [],
                $defaults['workflow_steps'] ?? [],
                ['title', 'body']
            ),
            'auth_panels' => static::normalizePlatformExperienceItems(
                is_array(data_get($settings, 'auth_panels')) ? data_get($settings, 'auth_panels') : [],
                $defaults['auth_panels'] ?? [],
                ['label', 'value']
            ),
            'example_business_name' => trim((string) data_get($settings, 'example_business_name', $defaults['example_business_name'] ?? 'AirCloud Demo')),
            'example_subdomain' => trim((string) data_get($settings, 'example_subdomain', $defaults['example_subdomain'] ?? 'aircloud-demo')),
        ];
    }

    protected static function normalizeDailyTime(string $value, string $fallback): string
    {
        return preg_match('/^(2[0-3]|[01]\d):[0-5]\d$/', $value) === 1
            ? $value
            : $fallback;
    }

    protected static function normalizeHexColor(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
            ? strtolower($value)
            : strtolower($fallback);
    }

    protected static function normalizePlatformExperienceItems(array $incoming, array $defaults, array $keys): array
    {
        return Collection::make($defaults)
            ->map(function (array $defaultItem, int $index) use ($incoming, $keys): array {
                $item = is_array($incoming[$index] ?? null) ? $incoming[$index] : [];

                return Collection::make($keys)
                    ->mapWithKeys(fn (string $key): array => [
                        $key => trim((string) data_get($item, $key, $defaultItem[$key] ?? '')),
                    ])
                    ->all();
            })
            ->filter(fn (array $item): bool => Collection::make($item)->contains(fn (string $value): bool => $value !== ''))
            ->values()
            ->all();
    }
}
