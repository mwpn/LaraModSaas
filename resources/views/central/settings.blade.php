@extends('central.layouts.master')

@section('page_title', 'System Settings')
@section('page_subtitle', 'Platform, payment, notification, dan automation pusat')

@section('content')
    @php
        $qris = $paymentSettings['qris'] ?? [];
        $manualTransfer = $paymentSettings['manual_transfer'] ?? [];
        $notifications = $notificationSettings['events'] ?? [];
        $defaultChannels = $notificationSettings['default_channels'] ?? [];
        $telegram = $notificationSettings['telegram'] ?? [];
        $whatsappCloud = $notificationSettings['whatsapp_cloud'] ?? [];
        $notificationTemplates = $notificationSettings['templates'] ?? [];
        $automation = $automationSettings ?? [];
        $experience = $platformExperience ?? [];
        $currentUser = auth('central')->user();
        $canManageSettings = $currentUser?->canAccessCentral('settings.manage') ?? false;
        $canManageTenants = $currentUser?->canAccessCentral('tenants.manage') ?? false;
        $settingsPanels = [
            'platform-blueprint' => ['label' => 'Platform', 'description' => 'Mode SaaS dan blueprint modul'],
            'platform-experience' => ['label' => 'Landing', 'description' => 'Brand dan judul template'],
            'payment-methods' => ['label' => 'Payment Method', 'description' => 'QRIS, transfer, inbox BCA'],
            'notification-channels' => ['label' => 'Notification', 'description' => 'Channel, template, integrasi'],
            'automation-rules' => ['label' => 'Automation', 'description' => 'Scheduler dan reminder'],
        ];
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if (session('test_result'))
            @php
                $testResult = session('test_result');
            @endphp
            <div class="alert alert-{{ data_get($testResult, 'variant', 'success') === 'success' ? 'success' : (data_get($testResult, 'variant') === 'warning' ? 'warning' : 'danger') }}">
                <strong style="display: block; margin-bottom: 6px;">{{ data_get($testResult, 'title', 'Test Result') }}</strong>
                <div>{{ data_get($testResult, 'message', '-') }}</div>

                @if (is_array(data_get($testResult, 'details')) && data_get($testResult, 'details') !== [])
                    <div style="margin-top: 10px; display: grid; gap: 6px;">
                        @foreach (data_get($testResult, 'details', []) as $label => $value)
                            <div><strong>{{ $label }}:</strong> {{ $value }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @unless ($canManageSettings)
            <div class="alert alert-warning">
                Role ini hanya punya akses baca untuk `System Settings`. Perubahan setting pusat dan test credential hanya bisa dijalankan owner.
            </div>
        @endunless

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-sliders"></i> System Settings</span>
                <h2>Central Control Blueprint</h2>
                <p>Kelola platform blueprint, payment methods, notification channels, dan automation dari satu workspace pusat.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Mode</span>
                    <strong>{{ ucfirst($platformType) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Payment Active</span>
                    <strong>{{ $settingsSummary['enabled_payments'] }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Channels</span>
                    <strong>{{ $settingsSummary['enabled_channels'] }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Automation</span>
                    <strong>{{ $settingsSummary['active_automation'] }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-compass"></i></span>
                    <div class="stat-copy">
                        <p>Platform</p>
                        <strong>{{ ucfirst($platformType) }}</strong>
                        <span>{{ $platformContent['badge'] }}</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                    <div class="stat-copy">
                        <p>Payment Methods</p>
                        <strong>{{ $settingsSummary['enabled_payments'] }}</strong>
                        <span>Metode aktif pusat</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-paper-plane"></i></span>
                    <div class="stat-copy">
                        <p>Notif Channels</p>
                        <strong>{{ $settingsSummary['enabled_channels'] }}</strong>
                        <span>Channel default aktif</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-robot"></i></span>
                    <div class="stat-copy">
                        <p>Automation</p>
                        <strong>{{ $settingsSummary['active_automation'] }}</strong>
                        <span>Job harian aktif</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-grid">
            <div class="dashboard-card settings-workspace-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">System Settings Workspace</h3>
                        <p class="card-subtitle">Workspace dibagi per panel biar fokus, nggak numpuk semua setting dalam satu viewport.</p>
                    </div>
                    <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.index') }}">Tenant Panel</a>
                </div>

                <form id="system-settings-form" method="POST" action="{{ route('central.super-admin.settings.update') }}" class="form-stack">
                    @csrf
                    <input type="hidden" name="experience_platform_type" value="{{ $platformType }}">
                    <input type="hidden" name="settings_active_panel" id="settings-active-panel-input" value="platform-blueprint">

                    <div class="settings-workspace">
                        <div class="settings-workspace-tabs-shell">
                            <div class="settings-workspace-tabs" role="tablist" aria-label="System settings panels">
                                @foreach ($settingsPanels as $panelId => $panelMeta)
                                    <button
                                        type="button"
                                        class="settings-workspace-tab"
                                        data-settings-tab="{{ $panelId }}"
                                        data-settings-label="{{ $panelMeta['label'] }}"
                                        data-settings-description="{{ $panelMeta['description'] }}"
                                        role="tab"
                                        aria-controls="{{ $panelId }}"
                                        aria-selected="false"
                                    >
                                        <span class="settings-workspace-tab-copy">{{ $panelMeta['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="settings-workspace-body">
                            <div class="settings-panel-heading">
                                <div class="settings-panel-heading-copy">
                                    <span class="settings-panel-heading-eyebrow">System Settings</span>
                                    <strong data-settings-panel-title>Platform</strong>
                                    <p data-settings-panel-description>Mode SaaS dan blueprint modul</p>
                                </div>
                                <span class="settings-panel-heading-badge">
                                    <i class="fas fa-layer-group"></i> Focused panel
                                </span>
                            </div>

                    <fieldset @disabled(! $canManageSettings) style="display: grid; gap: 20px; border: 0; padding: 0; margin: 0;">

                    <div class="form-block settings-panel" id="platform-blueprint" data-settings-panel="platform-blueprint" data-settings-panel-label="Platform">
                        <div class="card-head" style="padding: 0; margin-bottom: 16px;">
                            <div>
                                <h4 class="form-title">Platform Blueprint</h4>
                                <p class="card-subtitle">Titik kontrol tenant provisioning, preset modul, dan mode SaaS pusat.</p>
                            </div>
                        </div>

                        <label class="field-label" for="platform_saas_type">Platform Mode</label>
                        <select id="platform_saas_type" name="platform_saas_type">
                            @foreach ($availablePlatformTypes as $availablePlatformType)
                                <option value="{{ $availablePlatformType }}" @selected(old('platform_saas_type', $platformType) === $availablePlatformType)>
                                    {{ ucfirst($availablePlatformType) }}
                                </option>
                            @endforeach
                        </select>

                        <label class="checkbox-row" style="margin-top: 16px;">
                            <input type="hidden" name="sync_modules_with_platform" value="0">
                            <input type="checkbox" name="sync_modules_with_platform" value="1" @checked(old('sync_modules_with_platform', true)) style="margin-top: 3px;">
                            <span>
                                <strong style="display: block;">Sync modules with platform preset</strong>
                                <span class="muted">Rekomendasi default biar tenant otomatis ikut blueprint pusat.</span>
                            </span>
                        </label>

                        <div class="module-list" style="margin-top: 12px;">
                            <input type="hidden" name="active_modules[]" value="BaseFeature">
                            @foreach ($moduleCatalog as $module)
                                <label class="module-item{{ $module['selected'] ? ' module-item-active' : '' }}">
                                    <input
                                        type="checkbox"
                                        name="active_modules[]"
                                        value="{{ $module['name'] }}"
                                        @checked(in_array($module['name'], old('active_modules', $activeModules), true))
                                        @disabled($module['required'])
                                        style="margin-top: 3px;"
                                    >
                                    <span class="module-copy">
                                        <strong>{{ $module['label'] }}</strong>
                                        <span>{{ $module['description'] }}</span>
                                        <span class="badge-row">
                                            @if ($module['required'])
                                                <span class="badge">Required</span>
                                            @endif
                                            @if ($module['recommended'])
                                                <span class="badge">{{ ucfirst($platformType) }}</span>
                                            @endif
                                            <span class="badge badge-neutral">{{ $module['installed'] ? 'Installed' : 'Not Installed' }}</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-block settings-panel" id="platform-experience" data-settings-panel="platform-experience" data-settings-panel-label="Landing">
                        <div class="card-head" style="padding: 0; margin-bottom: 16px;">
                            <div>
                                <h4 class="form-title">Landing Template Settings</h4>
                                <p class="card-subtitle">Landing sekarang pakai template source. Jadi panel ini cuma menampilkan field yang memang aktif di template.</p>
                            </div>
                        </div>

                        @php
                            $landingTemplateCatalog = [
                                'tirta' => [
                                    'brand' => 'Aqualytic',
                                    'headline' => 'Catat Meter Air Digital, Tagihan Terkirim via WA, Bebas Tunggakan',
                                ],
                                'hotel' => [
                                    'brand' => 'InnSystem',
                                    'headline' => 'Isi Kamar Lebih Maksimal, Urus Operasional Tanpa Pusing Harian',
                                ],
                                'resto' => [
                                    'brand' => 'RestoFlow',
                                    'headline' => 'Antrean Kasir Beres, Stok Dapur Terkontrol Otomatis',
                                ],
                                'netbilling' => [
                                    'brand' => 'NetFlow',
                                    'headline' => 'Tagihan Otomatis, Jatuh Tempo Langsung Isolir Tanpa Manual',
                                ],
                                'universal' => [
                                    'brand' => config('app.name', 'AirCloud'),
                                    'headline' => 'Kelola produk SaaS dari satu panel pusat yang rapi.',
                                ],
                            ];
                            $activeLandingTemplate = $landingTemplateCatalog[$platformType] ?? $landingTemplateCatalog['universal'];
                            $landingBrandValue = old('experience.brand_name', data_get($experience, 'brand_name', '')) ?: $activeLandingTemplate['brand'];
                            $landingHeadlineValue = old('experience.headline', data_get($experience, 'headline', '')) ?: $activeLandingTemplate['headline'];
                            $landingPageTitleValue = trim((string) $landingHeadlineValue) !== ''
                                ? $landingHeadlineValue . ' | ' . $landingBrandValue
                                : $landingBrandValue;
                        @endphp

                        <div class="dashboard-card" style="padding: 20px; background: rgba(15, 23, 42, 0.02);">
                            <div class="inline-actions">
                                <div style="flex: 1 1 280px;">
                                    <label class="field-label" for="experience-brand-name">Brand Name</label>
                                    <input id="experience-brand-name" class="field" type="text" name="experience[brand_name]" value="{{ old('experience.brand_name', data_get($experience, 'brand_name', '')) }}">
                                    <span class="status-muted">Dipakai untuk browser title dan fallback nama brand di landing.</span>
                                </div>
                            </div>

                            <div style="margin-top: 16px;">
                                <label class="field-label" for="experience-headline">Landing Headline</label>
                                <textarea id="experience-headline" class="field" name="experience[headline]" rows="2">{{ old('experience.headline', data_get($experience, 'headline', '')) }}</textarea>
                                <span class="status-muted">Dipakai untuk override judul hero template. Kalau kosong, sistem pakai judul bawaan template platform.</span>
                            </div>

                            <div class="quick-grid" style="margin-top: 18px;">
                                <div class="quick-item" style="align-items: flex-start;">
                                    <div>
                                        <strong>Yang bisa diatur</strong>
                                        <span>Brand name untuk web title dan landing headline untuk judul hero.</span>
                                    </div>
                                </div>
                                <div class="quick-item" style="align-items: flex-start;">
                                    <div>
                                        <strong>Yang ikut template</strong>
                                        <span>Menu, section copy, CTA, dan struktur visual tetap mengikuti template masing-masing platform.</span>
                                    </div>
                                </div>
                                <div class="quick-item" style="align-items: flex-start;">
                                    <div>
                                        <strong>Mode aman</strong>
                                        <span>Kalau field dikosongkan, landing otomatis balik ke judul dan brand default template.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="dashboard-card"
                            data-settings-preview-root
                            data-default-brand="{{ $activeLandingTemplate['brand'] }}"
                            data-default-headline="{{ $activeLandingTemplate['headline'] }}"
                            style="padding: 24px; margin-top: 18px; background: linear-gradient(180deg, rgba(15, 23, 42, 0.02), rgba(15, 23, 42, 0.05));"
                        >
                            <div class="card-head" style="padding: 0; margin-bottom: 16px;">
                                <div>
                                    <h4 class="form-title">Live Preview</h4>
                                    <p class="card-subtitle">Preview mengikuti perilaku landing yang aktif sekarang, bukan layout generik lama.</p>
                                </div>
                            </div>

                            <div class="inline-actions" style="align-items: stretch;">
                                <div class="dashboard-card" style="flex: 1 1 380px; padding: 24px; border: 1px solid rgba(148, 163, 184, 0.18); background: #ffffff;">
                                    <span style="display: inline-flex; align-items: center; gap: 8px; padding: 6px 11px; border-radius: 999px; background: rgba(15, 23, 42, 0.04); color: #475569; font-size: 12px;">
                                        Template Landing Aktif
                                    </span>
                                    <h3 data-preview-headline style="margin: 16px 0 10px; font-size: 1.6rem; line-height: 1.25; color: #0f172a;">
                                        {{ $landingHeadlineValue }}
                                    </h3>
                                    <p style="margin: 0; color: #475569; line-height: 1.7;">
                                        Template section, subheadline, CTA, dan visual utama tetap mengikuti layout source platform aktif.
                                    </p>
                                </div>

                                <div class="dashboard-card" style="flex: 1 1 420px; padding: 24px; border: 1px solid rgba(148, 163, 184, 0.18); background: #ffffff;">
                                    <div class="quick-grid">
                                        <div class="quick-item" style="background: rgba(248, 250, 252, 0.8);">
                                            <div>
                                                <strong>Browser Title</strong>
                                                <span data-preview-page-title>{{ $landingPageTitleValue }}</span>
                                            </div>
                                        </div>
                                        <div class="quick-item" style="background: rgba(248, 250, 252, 0.8);">
                                            <div>
                                                <strong>Brand Aktif</strong>
                                                <span data-preview-brand-name>{{ $landingBrandValue }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-top: 18px; padding: 16px; border-radius: 14px; background: rgba(15, 23, 42, 0.03);">
                                        <span style="display: block; font-size: 12px; color: #64748b;">Catatan Template</span>
                                        <strong style="display: block; margin-top: 6px; color: #0f172a;">Copy section lain tetap ikut template source.</strong>
                                        <p style="margin: 8px 0 0; color: #64748b; line-height: 1.7;">
                                            Jadi perubahan di panel ini hanya memengaruhi judul hero dan web title, bukan seluruh isi landing.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-block settings-panel" id="payment-methods" data-settings-panel="payment-methods" data-settings-panel-label="Payment">
                        <div class="card-head" style="padding: 0; margin-bottom: 16px;">
                            <div>
                                <h4 class="form-title">Payment Methods</h4>
                                <p class="card-subtitle">Atur QRIS dan transfer manual dari panel pusat, tanpa edit kode lagi.</p>
                            </div>
                        </div>

                        <div class="dashboard-card" style="padding: 20px; background: rgba(15, 23, 42, 0.02);">
                            <label class="checkbox-row">
                                <input type="hidden" name="payment_methods[qris][enabled]" value="0">
                                <input type="checkbox" name="payment_methods[qris][enabled]" value="1" @checked(old('payment_methods.qris.enabled', data_get($qris, 'enabled', false))) style="margin-top: 3px;">
                                <span>
                                    <strong style="display: block;">Aktifkan QRIS</strong>
                                    <span class="muted">Provider live QRIS untuk generate dan cek status invoice billing.</span>
                                </span>
                            </label>

                            <div class="inline-actions" style="margin-top: 16px;">
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="qris-provider-name">Provider Name</label>
                                    <input id="qris-provider-name" class="field" type="text" name="payment_methods[qris][provider_name]" value="{{ old('payment_methods.qris.provider_name', data_get($qris, 'provider_name', '')) }}">
                                </div>
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="qris-merchant-id">Merchant ID / mID</label>
                                    <input id="qris-merchant-id" class="field" type="text" name="payment_methods[qris][merchant_id]" value="{{ old('payment_methods.qris.merchant_id', data_get($qris, 'merchant_id', '')) }}">
                                </div>
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="qris-nmid">NMID</label>
                                    <input id="qris-nmid" class="field" type="text" name="payment_methods[qris][nmid]" value="{{ old('payment_methods.qris.nmid', data_get($qris, 'nmid', '')) }}">
                                </div>
                            </div>

                            <div class="inline-actions">
                                <div style="flex: 1 1 320px;">
                                    <label class="field-label" for="qris-base-url">Base URL</label>
                                    <input id="qris-base-url" class="field" type="text" name="payment_methods[qris][base_url]" value="{{ old('payment_methods.qris.base_url', data_get($qris, 'base_url', '')) }}">
                                </div>
                                <div style="flex: 1 1 200px;">
                                    <label class="field-label" for="qris-use-tip">Use Tip</label>
                                    <select id="qris-use-tip" name="payment_methods[qris][use_tip]">
                                        @foreach (['no' => 'No Tip', 'yes' => 'Allow Tip'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('payment_methods.qris.use_tip', data_get($qris, 'use_tip', 'no')) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div style="flex: 1 1 260px;">
                                    <label class="field-label" for="qris-api-key">API Key</label>
                                    <input id="qris-api-key" class="field" type="password" name="payment_methods[qris][api_key]" value="">
                                    <span class="status-muted">{{ $credentialStatus['qris_api_key'] ? 'Sudah tersimpan. Isi hanya jika mau ganti.' : 'Belum ada API key tersimpan.' }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="field-label" for="qris-instructions">Instruksi Pembayaran QRIS</label>
                                <textarea id="qris-instructions" class="field" name="payment_methods[qris][instructions]" rows="3">{{ old('payment_methods.qris.instructions', data_get($qris, 'instructions', '')) }}</textarea>
                            </div>

                            <div class="inline-actions" style="margin-top: 16px;">
                                <button
                                    class="central-btn-secondary"
                                    type="submit"
                                    formaction="{{ route('central.super-admin.settings.test-qris') }}"
                                    formmethod="POST"
                                    formnovalidate
                                >
                                    Test QRIS Connection
                                </button>
                                <span class="status-muted">Test pakai nilai field QRIS yang sedang terbuka. API key kosong tetap pakai secret lama kalau sudah tersimpan.</span>
                            </div>
                        </div>

                        <div class="dashboard-card" style="padding: 20px; margin-top: 18px; background: rgba(15, 23, 42, 0.02);">
                            <label class="checkbox-row">
                                <input type="hidden" name="payment_methods[manual_transfer][enabled]" value="0">
                                <input type="checkbox" name="payment_methods[manual_transfer][enabled]" value="1" @checked(old('payment_methods.manual_transfer.enabled', data_get($manualTransfer, 'enabled', false))) style="margin-top: 3px;">
                                <span>
                                    <strong style="display: block;">Aktifkan Transfer Manual</strong>
                                    <span class="muted">Rekening pusat untuk konfirmasi pembayaran manual invoice tenant.</span>
                                </span>
                            </label>

                            <div class="inline-actions" style="margin-top: 16px;">
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="manual-transfer-bank-name">Nama Bank</label>
                                    <input id="manual-transfer-bank-name" class="field" type="text" name="payment_methods[manual_transfer][bank_name]" value="{{ old('payment_methods.manual_transfer.bank_name', data_get($manualTransfer, 'bank_name', '')) }}">
                                </div>
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="manual-transfer-account-name">Nama Rekening</label>
                                    <input id="manual-transfer-account-name" class="field" type="text" name="payment_methods[manual_transfer][account_name]" value="{{ old('payment_methods.manual_transfer.account_name', data_get($manualTransfer, 'account_name', '')) }}">
                                </div>
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="manual-transfer-account-number">Nomor Rekening</label>
                                    <input id="manual-transfer-account-number" class="field" type="text" name="payment_methods[manual_transfer][account_number]" value="{{ old('payment_methods.manual_transfer.account_number', data_get($manualTransfer, 'account_number', '')) }}">
                                </div>
                            </div>

                            <div>
                                <label class="field-label" for="manual-transfer-notes">Instruksi Transfer Manual</label>
                                <textarea id="manual-transfer-notes" class="field" name="payment_methods[manual_transfer][notes]" rows="3">{{ old('payment_methods.manual_transfer.notes', data_get($manualTransfer, 'notes', '')) }}</textarea>
                            </div>

                            @php
                                $bcaFetcher = data_get($manualTransfer, 'bca_email_fetcher', []);
                            @endphp

                            <div class="dashboard-card" style="padding: 18px; margin-top: 18px; background: rgba(2, 132, 199, 0.05); border: 1px solid rgba(56, 189, 248, 0.14);">
                                <label class="checkbox-row">
                                    <input type="hidden" name="payment_methods[manual_transfer][bca_email_fetcher][enabled]" value="0">
                                    <input type="checkbox" name="payment_methods[manual_transfer][bca_email_fetcher][enabled]" value="1" @checked(old('payment_methods.manual_transfer.bca_email_fetcher.enabled', data_get($bcaFetcher, 'enabled', false))) style="margin-top: 3px;">
                                    <span>
                                        <strong style="display: block;">Aktifkan Auto Fetch Email BCA</strong>
                                        <span class="muted">Dipakai saat tenant klik konfirmasi transfer agar sistem langsung scan inbox dan cocokin nominal exact.</span>
                                    </span>
                                </label>

                                <div class="inline-actions" style="margin-top: 16px;">
                                    <div style="flex: 1 1 220px;">
                                        <label class="field-label" for="manual-transfer-mail-host">IMAP Host</label>
                                        <input id="manual-transfer-mail-host" class="field" type="text" name="payment_methods[manual_transfer][bca_email_fetcher][host]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.host', data_get($bcaFetcher, 'host', '')) }}" placeholder="imap.gmail.com">
                                    </div>
                                    <div style="flex: 0 1 120px;">
                                        <label class="field-label" for="manual-transfer-mail-port">Port</label>
                                        <input id="manual-transfer-mail-port" class="field" type="number" name="payment_methods[manual_transfer][bca_email_fetcher][port]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.port', data_get($bcaFetcher, 'port', 993)) }}">
                                    </div>
                                    <div style="flex: 0 1 160px;">
                                        <label class="field-label" for="manual-transfer-mail-encryption">Encryption</label>
                                        <select id="manual-transfer-mail-encryption" name="payment_methods[manual_transfer][bca_email_fetcher][encryption]">
                                            @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('payment_methods.manual_transfer.bca_email_fetcher.encryption', data_get($bcaFetcher, 'encryption', 'ssl')) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="inline-actions" style="margin-top: 16px;">
                                    <div style="flex: 1 1 240px;">
                                        <label class="field-label" for="manual-transfer-mail-username">Mailbox Username</label>
                                        <input id="manual-transfer-mail-username" class="field" type="text" name="payment_methods[manual_transfer][bca_email_fetcher][username]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.username', data_get($bcaFetcher, 'username', '')) }}">
                                    </div>
                                    <div style="flex: 1 1 240px;">
                                        <label class="field-label" for="manual-transfer-mail-password">Mailbox Password / App Password</label>
                                        <input id="manual-transfer-mail-password" class="field" type="password" name="payment_methods[manual_transfer][bca_email_fetcher][password]" value="">
                                        <span class="status-muted">{{ $credentialStatus['manual_transfer_fetcher_password'] ? 'Sudah tersimpan. Isi hanya jika mau ganti.' : 'Belum ada password mailbox tersimpan.' }}</span>
                                    </div>
                                    <div style="flex: 0 1 180px;">
                                        <label class="field-label" for="manual-transfer-mail-folder">Folder</label>
                                        <input id="manual-transfer-mail-folder" class="field" type="text" name="payment_methods[manual_transfer][bca_email_fetcher][folder]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.folder', data_get($bcaFetcher, 'folder', 'INBOX')) }}">
                                    </div>
                                </div>

                                <div class="inline-actions" style="margin-top: 16px;">
                                    <div style="flex: 1 1 220px;">
                                        <label class="field-label" for="manual-transfer-mail-sender-filter">Sender Filter</label>
                                        <input id="manual-transfer-mail-sender-filter" class="field" type="text" name="payment_methods[manual_transfer][bca_email_fetcher][sender_filter]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.sender_filter', data_get($bcaFetcher, 'sender_filter', '')) }}" placeholder="mis. no-reply@klikbca.com">
                                    </div>
                                    <div style="flex: 1 1 220px;">
                                        <label class="field-label" for="manual-transfer-mail-subject">Subject Keyword</label>
                                        <input id="manual-transfer-mail-subject" class="field" type="text" name="payment_methods[manual_transfer][bca_email_fetcher][subject_keyword]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.subject_keyword', data_get($bcaFetcher, 'subject_keyword', '')) }}" placeholder="mis. notifikasi transfer">
                                    </div>
                                </div>

                                <div class="inline-actions" style="margin-top: 16px;">
                                    <div style="flex: 0 1 180px;">
                                        <label class="field-label" for="manual-transfer-mail-lookback">Lookback Menit</label>
                                        <input id="manual-transfer-mail-lookback" class="field" type="number" name="payment_methods[manual_transfer][bca_email_fetcher][lookback_minutes]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.lookback_minutes', data_get($bcaFetcher, 'lookback_minutes', 60)) }}">
                                    </div>
                                    <div style="flex: 0 1 180px;">
                                        <label class="field-label" for="manual-transfer-mail-max">Max Messages</label>
                                        <input id="manual-transfer-mail-max" class="field" type="number" name="payment_methods[manual_transfer][bca_email_fetcher][max_messages]" value="{{ old('payment_methods.manual_transfer.bca_email_fetcher.max_messages', data_get($bcaFetcher, 'max_messages', 20)) }}">
                                    </div>
                                    <label class="quick-item" style="align-items: flex-start; flex: 1 1 220px; margin: 28px 0 0;">
                                        <input type="hidden" name="payment_methods[manual_transfer][bca_email_fetcher][unseen_only]" value="0">
                                        <input type="checkbox" name="payment_methods[manual_transfer][bca_email_fetcher][unseen_only]" value="1" @checked(old('payment_methods.manual_transfer.bca_email_fetcher.unseen_only', data_get($bcaFetcher, 'unseen_only', true))) style="margin-top: 4px;">
                                        <div>
                                            <strong>Scan Unseen Only</strong>
                                            <span>Batasi pencarian ke email yang belum dibaca.</span>
                                        </div>
                                    </label>
                                    <label class="quick-item" style="align-items: flex-start; flex: 1 1 220px; margin: 28px 0 0;">
                                        <input type="hidden" name="payment_methods[manual_transfer][bca_email_fetcher][validate_certificate]" value="0">
                                        <input type="checkbox" name="payment_methods[manual_transfer][bca_email_fetcher][validate_certificate]" value="1" @checked(old('payment_methods.manual_transfer.bca_email_fetcher.validate_certificate', data_get($bcaFetcher, 'validate_certificate', false))) style="margin-top: 4px;">
                                        <div>
                                            <strong>Validate Certificate</strong>
                                            <span>Aktifkan kalau sertifikat IMAP mailbox valid penuh.</span>
                                        </div>
                                    </label>
                                </div>

                                <div class="inline-actions" style="margin-top: 16px;">
                                    <button
                                        class="central-btn-secondary"
                                        type="submit"
                                        formaction="{{ route('central.super-admin.settings.test-manual-transfer-fetcher') }}"
                                        formmethod="POST"
                                        formnovalidate
                                    >
                                        Test Inbox BCA
                                    </button>
                                    <span class="status-muted">Dipakai untuk simulasi koneksi IMAP ke mailbox BCA notifier tanpa mengubah data invoice tenant.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-block settings-panel" id="notification-channels" data-settings-panel="notification-channels" data-settings-panel-label="Notifications">
                        <div class="card-head" style="padding: 0; margin-bottom: 16px;">
                            <div>
                                <h4 class="form-title">Notifications & Integrations</h4>
                                <p class="card-subtitle">Fondasi channel notifikasi untuk billing reminder, payment alert, Telegram bot, dan WhatsApp Cloud API.</p>
                            </div>
                        </div>

                        <div class="dashboard-card" style="padding: 20px; background: rgba(15, 23, 42, 0.02);">
                            <h4 class="form-title" style="margin-bottom: 12px;">Event Rules</h4>
                            <div class="quick-grid">
                                @foreach ([
                                    'billing_due_reminder' => 'Billing Due Reminder',
                                    'subscription_expiry_reminder' => 'Subscription Expiry Reminder',
                                    'payment_success_alert' => 'Payment Success Alert',
                                ] as $eventKey => $eventLabel)
                                    <label class="quick-item" style="align-items: flex-start;">
                                        <input type="hidden" name="notifications[events][{{ $eventKey }}]" value="0">
                                        <input type="checkbox" name="notifications[events][{{ $eventKey }}]" value="1" @checked(old('notifications.events.' . $eventKey, data_get($notifications, $eventKey, false))) style="margin-top: 4px;">
                                        <div>
                                            <strong>{{ $eventLabel }}</strong>
                                            <span>Kontrol event notifikasi pusat</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            <h4 class="form-title" style="margin: 20px 0 12px;">Default Channels</h4>
                            <div class="quick-grid">
                                @foreach ([
                                    'email' => 'Email',
                                    'telegram' => 'Telegram',
                                    'whatsapp' => 'WhatsApp',
                                ] as $channelKey => $channelLabel)
                                    <label class="quick-item" style="align-items: flex-start;">
                                        <input type="hidden" name="notifications[default_channels][{{ $channelKey }}]" value="0">
                                        <input type="checkbox" name="notifications[default_channels][{{ $channelKey }}]" value="1" @checked(old('notifications.default_channels.' . $channelKey, data_get($defaultChannels, $channelKey, false))) style="margin-top: 4px;">
                                        <div>
                                            <strong>{{ $channelLabel }}</strong>
                                            <span>Channel default untuk event aktif</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="dashboard-card" style="padding: 20px; margin-top: 18px; background: rgba(15, 23, 42, 0.02);">
                            <label class="checkbox-row">
                                <input type="hidden" name="notifications[telegram][enabled]" value="0">
                                <input type="checkbox" name="notifications[telegram][enabled]" value="1" @checked(old('notifications.telegram.enabled', data_get($telegram, 'enabled', false))) style="margin-top: 3px;">
                                <span>
                                    <strong style="display: block;">Aktifkan Telegram Bot</strong>
                                    <span class="muted">Siap dipakai untuk notifikasi admin atau queue reminder.</span>
                                </span>
                            </label>

                            <div class="inline-actions" style="margin-top: 16px;">
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="telegram-bot-name">Bot Name</label>
                                    <input id="telegram-bot-name" class="field" type="text" name="notifications[telegram][bot_name]" value="{{ old('notifications.telegram.bot_name', data_get($telegram, 'bot_name', '')) }}">
                                </div>
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="telegram-default-chat-id">Default Chat ID</label>
                                    <input id="telegram-default-chat-id" class="field" type="text" name="notifications[telegram][default_chat_id]" value="{{ old('notifications.telegram.default_chat_id', data_get($telegram, 'default_chat_id', '')) }}">
                                </div>
                                <div style="flex: 1 1 260px;">
                                    <label class="field-label" for="telegram-bot-token">Bot Token</label>
                                    <input id="telegram-bot-token" class="field" type="password" name="notifications[telegram][bot_token]" value="">
                                    <span class="status-muted">{{ $credentialStatus['telegram_bot_token'] ? 'Sudah tersimpan. Isi hanya jika mau ganti.' : 'Belum ada token tersimpan.' }}</span>
                                </div>
                            </div>

                            <div class="inline-actions" style="margin-top: 16px;">
                                <button
                                    class="central-btn-secondary"
                                    type="submit"
                                    formaction="{{ route('central.super-admin.settings.test-telegram') }}"
                                    formmethod="POST"
                                    formnovalidate
                                >
                                    Send Telegram Test
                                </button>
                                <span class="status-muted">Pesan test akan dikirim ke Default Chat ID yang sedang tampil di form.</span>
                            </div>
                        </div>

                        <div class="dashboard-card" style="padding: 20px; margin-top: 18px; background: rgba(15, 23, 42, 0.02);">
                            <label class="checkbox-row">
                                <input type="hidden" name="notifications[whatsapp_cloud][enabled]" value="0">
                                <input type="checkbox" name="notifications[whatsapp_cloud][enabled]" value="1" @checked(old('notifications.whatsapp_cloud.enabled', data_get($whatsappCloud, 'enabled', false))) style="margin-top: 3px;">
                                <span>
                                    <strong style="display: block;">Aktifkan WhatsApp Cloud API</strong>
                                    <span class="muted">Fondasi integrasi WA billing reminder dan status invoice.</span>
                                </span>
                            </label>

                            <div class="inline-actions" style="margin-top: 16px;">
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="wa-phone-number-id">Phone Number ID</label>
                                    <input id="wa-phone-number-id" class="field" type="text" name="notifications[whatsapp_cloud][phone_number_id]" value="{{ old('notifications.whatsapp_cloud.phone_number_id', data_get($whatsappCloud, 'phone_number_id', '')) }}">
                                </div>
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="wa-business-account-id">Business Account ID</label>
                                    <input id="wa-business-account-id" class="field" type="text" name="notifications[whatsapp_cloud][business_account_id]" value="{{ old('notifications.whatsapp_cloud.business_account_id', data_get($whatsappCloud, 'business_account_id', '')) }}">
                                </div>
                                <div style="flex: 1 1 220px;">
                                    <label class="field-label" for="wa-default-recipient-phone">Nomor Admin Reminder</label>
                                    <input id="wa-default-recipient-phone" class="field" type="text" name="notifications[whatsapp_cloud][default_recipient_phone]" value="{{ old('notifications.whatsapp_cloud.default_recipient_phone', data_get($whatsappCloud, 'default_recipient_phone', '')) }}" placeholder="62812xxxxxx">
                                </div>
                            </div>

                            <div class="inline-actions">
                                <div style="flex: 1 1 260px;">
                                    <label class="field-label" for="wa-access-token">Access Token</label>
                                    <input id="wa-access-token" class="field" type="password" name="notifications[whatsapp_cloud][access_token]" value="">
                                    <span class="status-muted">{{ $credentialStatus['whatsapp_access_token'] ? 'Sudah tersimpan. Isi hanya jika mau ganti.' : 'Belum ada access token tersimpan.' }}</span>
                                </div>
                                <div style="flex: 1 1 260px;">
                                    <label class="field-label" for="wa-verify-token">Verify Token</label>
                                    <input id="wa-verify-token" class="field" type="password" name="notifications[whatsapp_cloud][verify_token]" value="">
                                    <span class="status-muted">{{ $credentialStatus['whatsapp_verify_token'] ? 'Sudah tersimpan. Isi hanya jika mau ganti.' : 'Belum ada verify token tersimpan.' }}</span>
                                </div>
                            </div>

                            <div style="margin-top: 16px; max-width: 340px;">
                                <label class="field-label" for="wa-test-recipient-phone">Nomor Tujuan Test</label>
                                <input id="wa-test-recipient-phone" class="field" type="text" name="notifications[whatsapp_cloud][test_recipient_phone]" value="{{ old('notifications.whatsapp_cloud.test_recipient_phone', '') }}" placeholder="62812xxxxxx">
                                <span class="status-muted">Opsional. Kalau dikosongkan, sistem hanya cek metadata sender WhatsApp tanpa kirim pesan.</span>
                            </div>

                            <div class="inline-actions" style="margin-top: 16px;">
                                <button
                                    class="central-btn-secondary"
                                    type="submit"
                                    formaction="{{ route('central.super-admin.settings.test-whatsapp') }}"
                                    formmethod="POST"
                                    formnovalidate
                                >
                                    Test WhatsApp Cloud
                                </button>
                                <span class="status-muted">Gunakan nomor internasional tanpa spasi. Nomor admin reminder dipakai untuk notifikasi billing real, sedangkan nomor tujuan test opsional untuk kirim pesan uji coba.</span>
                            </div>
                        </div>

                        <div class="dashboard-card" style="padding: 20px; margin-top: 18px; background: rgba(15, 23, 42, 0.02);">
                            <div class="card-head" style="padding: 0; margin-bottom: 16px;">
                                <div>
                                    <h4 class="form-title">Message Templates</h4>
                                    <p class="card-subtitle">Gunakan placeholder seperti <code>@{{tenant_name}}</code>, <code>@{{invoice_number}}</code>, <code>@{{payment_status}}</code>, <code>@{{scan_time}}</code>, <code>@{{overdue_count}}</code>, dan <code>@{{expiring_count}}</code>.</p>
                                </div>
                            </div>

                            <div class="form-stack">
                                <div>
                                    <label class="field-label" for="template-billing-reminder">Billing Reminder</label>
                                    <textarea id="template-billing-reminder" class="field" name="notifications[templates][billing_reminder]" rows="5">{{ old('notifications.templates.billing_reminder', data_get($notificationTemplates, 'billing_reminder', '')) }}</textarea>
                                </div>

                                <div>
                                    <label class="field-label" for="template-payment-success">Payment Success Alert</label>
                                    <textarea id="template-payment-success" class="field" name="notifications[templates][payment_success]" rows="4">{{ old('notifications.templates.payment_success', data_get($notificationTemplates, 'payment_success', '')) }}</textarea>
                                </div>

                                <div>
                                    <label class="field-label" for="template-public-payment-note">Public Payment Note</label>
                                    <textarea id="template-public-payment-note" class="field" name="notifications[templates][public_payment_note]" rows="4">{{ old('notifications.templates.public_payment_note', data_get($notificationTemplates, 'public_payment_note', '')) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-block settings-panel" id="automation-rules" data-settings-panel="automation-rules" data-settings-panel-label="Automation">
                        <div class="card-head" style="padding: 0; margin-bottom: 16px;">
                            <div>
                                <h4 class="form-title">Automation Rules</h4>
                                <p class="card-subtitle">Kontrol jam auto-generate invoice dan reminder scan harian dari panel pusat.</p>
                            </div>
                        </div>

                        <div class="inline-actions">
                            <div class="dashboard-card" style="padding: 20px; flex: 1 1 340px; background: rgba(15, 23, 42, 0.02);">
                                <label class="checkbox-row">
                                    <input type="hidden" name="automation[billing_auto_generate_enabled]" value="0">
                                    <input type="checkbox" name="automation[billing_auto_generate_enabled]" value="1" @checked(old('automation.billing_auto_generate_enabled', data_get($automation, 'billing_auto_generate_enabled', true))) style="margin-top: 3px;">
                                    <span>
                                        <strong style="display: block;">Auto Generate Due Invoices</strong>
                                        <span class="muted">Scheduler billing invoice harian.</span>
                                    </span>
                                </label>
                                <div style="margin-top: 16px;">
                                    <label class="field-label" for="billing-auto-generate-time">Jam Run</label>
                                    <input id="billing-auto-generate-time" class="field" type="time" name="automation[billing_auto_generate_time]" value="{{ old('automation.billing_auto_generate_time', data_get($automation, 'billing_auto_generate_time', '00:10')) }}">
                                </div>
                            </div>

                            <div class="dashboard-card" style="padding: 20px; flex: 1 1 340px; background: rgba(15, 23, 42, 0.02);">
                                <label class="checkbox-row">
                                    <input type="hidden" name="automation[billing_reminder_scan_enabled]" value="0">
                                    <input type="checkbox" name="automation[billing_reminder_scan_enabled]" value="1" @checked(old('automation.billing_reminder_scan_enabled', data_get($automation, 'billing_reminder_scan_enabled', true))) style="margin-top: 3px;">
                                    <span>
                                        <strong style="display: block;">Billing Reminder Scan</strong>
                                        <span class="muted">Scheduler scan overdue dan subscription soon.</span>
                                    </span>
                                </label>
                                <div style="margin-top: 16px;">
                                    <label class="field-label" for="billing-reminder-scan-time">Jam Run</label>
                                    <input id="billing-reminder-scan-time" class="field" type="time" name="automation[billing_reminder_scan_time]" value="{{ old('automation.billing_reminder_scan_time', data_get($automation, 'billing_reminder_scan_time', '08:00')) }}">
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 16px; max-width: 280px;">
                            <label class="field-label" for="subscription-reminder-days">Subscription Reminder Days</label>
                            <input id="subscription-reminder-days" class="field" type="number" min="1" max="30" name="automation[subscription_reminder_days]" value="{{ old('automation.subscription_reminder_days', data_get($automation, 'subscription_reminder_days', 7)) }}">
                        </div>
                    </div>

                    </fieldset>

                        </div>
                    </div>

                    <div class="inline-actions settings-save-bar">
                        @if ($canManageSettings)
                            <button class="central-btn" type="submit">Save System Settings</button>
                            <span class="status-muted">Credential sensitif disimpan aman. Kalau field secret dikosongkan, nilai lama tetap dipakai.</span>
                        @else
                            <span class="status-muted">Mode read-only aktif untuk role ini.</span>
                        @endif
                    </div>
                </form>
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Mode Snapshot</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Badge</span>
                            <strong>{{ $platformContent['badge'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Headline</span>
                            <strong>{{ $platformContent['headline'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Theme</span>
                            <strong>{{ $platformContent['accent'] ?? $centralAccent ?? '#2563eb' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Active Modules</span>
                            <strong>{{ implode(', ', $activeModules) }}</strong>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Credential Status</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>QRIS API Key</span>
                            <strong>{{ $credentialStatus['qris_api_key'] ? 'Stored' : 'Empty' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Telegram Token</span>
                            <strong>{{ $credentialStatus['telegram_bot_token'] ? 'Stored' : 'Empty' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>WA Access Token</span>
                            <strong>{{ $credentialStatus['whatsapp_access_token'] ? 'Stored' : 'Empty' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>WA Verify Token</span>
                            <strong>{{ $credentialStatus['whatsapp_verify_token'] ? 'Stored' : 'Empty' }}</strong>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Blueprint Modules</h3>
                        </div>
                    </div>

                    <div class="quick-grid">
                        @foreach ($blueprintModules as $moduleName)
                            <div class="quick-item">
                                <div>
                                    <strong>{{ $moduleName }}</strong>
                                </div>
                                <span class="status-active">Default</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Tenant Sync</h3>
                            <p class="card-subtitle">Terapkan mode pusat ini ke tenant lama.</p>
                        </div>
                    </div>

                    @if ($canManageTenants)
                        <form method="POST" action="{{ route('central.super-admin.tenants.sync-platform') }}" class="form-stack">
                            @csrf

                            <label class="checkbox-row">
                                <input type="checkbox" name="sync_branding" value="1" checked style="margin-top: 3px;">
                                <span>
                                    <strong style="display: block;">Sinkron tema & deskripsi tenant</strong>
                                    <span class="muted">Brand tenant tidak di-overwrite.</span>
                                </span>
                            </label>

                            <div class="inline-actions">
                                <button
                                    class="central-btn"
                                    type="submit"
                                    data-confirm
                                    data-confirm-variant="danger"
                                    data-confirm-title="Terapkan Mode ke Tenant Lama"
                                    data-confirm-message="Terapkan mode {{ ucfirst($platformType) }} ke tenant lama sekarang? Tenant yang belum sinkron akan mengikuti mode pusat."
                                    data-confirm-confirm-label="Ya, terapkan"
                                >
                                    Sinkron Tenant Lama
                                </button>
                                <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.index') }}">Buka Tenant Panel</a>
                            </div>
                        </form>
                    @else
                        <div class="mini-list">
                            <div class="mini-row">
                                <span>Status</span>
                                <strong>Read only</strong>
                            </div>
                            <div class="mini-row">
                                <span>Catatan</span>
                                <strong>Sinkron tenant lama hanya tersedia untuk role yang punya akses manage tenant.</strong>
                            </div>
                        </div>
                    @endif
                </section>
            </aside>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .settings-workspace {
            display: grid;
            gap: 20px;
        }

        .settings-workspace-card {
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96)),
                #ffffff;
            border-color: rgba(148, 163, 184, 0.18);
            box-shadow: 0 28px 50px rgba(15, 23, 42, 0.08);
        }

        .settings-workspace-tabs-shell {
            position: sticky;
            top: 88px;
            z-index: 5;
            margin: -2px 0 12px;
        }

        .settings-workspace-tabs {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            background: transparent;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .settings-workspace-tabs::-webkit-scrollbar {
            display: none;
        }

        .settings-workspace-tab {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            text-align: left;
            min-width: max-content;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: rgba(255, 255, 255, 0.92);
            color: #475569;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
            transition: color 0.2s ease, background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
            transform: none;
            white-space: nowrap;
        }

        .settings-workspace-tab::before {
            content: none;
        }

        .settings-workspace-tab-copy {
            font-size: 0.92rem;
            font-weight: 600;
            line-height: 1;
        }

        .settings-workspace-tab:hover {
            background: rgba(248, 250, 252, 0.98);
            border-color: rgba(37, 99, 235, 0.14);
            color: #0f172a;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
        }

        .settings-workspace-tab.is-active {
            background: color-mix(in srgb, var(--primary) 12%, #ffffff);
            color: var(--primary);
            border-color: color-mix(in srgb, var(--primary) 24%, #ffffff);
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.12);
        }

        .settings-workspace-body {
            display: grid;
            gap: 18px;
            min-width: 0;
        }

        .settings-panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 24px 22px;
            border-radius: 0 24px 24px 24px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.10), transparent 32%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.97));
            box-shadow: 0 20px 42px rgba(15, 23, 42, 0.06);
        }

        .settings-panel-heading-copy {
            display: grid;
            gap: 6px;
        }

        .settings-panel-heading-eyebrow {
            color: #64748b;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .settings-panel-heading strong {
            color: #0f172a;
            font-size: 1.15rem;
            line-height: 1.2;
        }

        .settings-panel-heading-copy p {
            margin: 0;
            color: #64748b;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .settings-panel-heading-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(148, 163, 184, 0.14);
            color: #475569;
            font-size: 0.8rem;
            white-space: nowrap;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }

        .settings-panel {
            border-radius: 24px;
            padding: 22px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96)),
                #ffffff;
            box-shadow:
                0 26px 48px rgba(15, 23, 42, 0.07),
                inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .settings-panel .dashboard-card {
            border-radius: 22px;
            border: 1px solid rgba(148, 163, 184, 0.12);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.94)),
                #ffffff !important;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.06);
        }

        .settings-panel .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 44px rgba(15, 23, 42, 0.08);
        }

        .settings-panel .card-head {
            margin-bottom: 18px !important;
        }

        .settings-panel .form-title {
            font-size: 1.02rem;
        }

        .settings-save-bar {
            margin-top: 6px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.98));
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.05);
        }

        .settings-panel[hidden] {
            display: none !important;
        }

        @media (min-width: 1200px) {
            .settings-workspace-tabs {
                flex-wrap: nowrap;
            }
        }

        @media (max-width: 1199px) {
            .settings-workspace-tabs {
                gap: 8px;
            }
        }

        @media (max-width: 767px) {
            .settings-workspace-tabs-shell {
                position: static;
                margin: 0 0 6px;
            }

            .settings-workspace-tabs {
                padding: 4px 0;
            }

            .settings-workspace-tab {
                padding: 10px 14px;
            }

            .settings-panel-heading {
                align-items: flex-start;
                flex-direction: column;
                border-radius: 18px;
            }

            .settings-panel-heading-badge {
                white-space: normal;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const settingsPanelInput = document.querySelector('#settings-active-panel-input');
            const settingsPanelTitle = document.querySelector('[data-settings-panel-title]');
            const settingsPanelDescription = document.querySelector('[data-settings-panel-description]');
            const settingsTabs = Array.from(document.querySelectorAll('[data-settings-tab]'));
            const settingsPanels = Array.from(document.querySelectorAll('[data-settings-panel]'));
            const defaultPanelId = settingsTabs[0]?.dataset.settingsTab || 'platform-blueprint';

            const activateSettingsPanel = (panelId, persist = true) => {
                const nextPanelId = settingsPanels.some((panel) => panel.dataset.settingsPanel === panelId)
                    ? panelId
                    : defaultPanelId;

                settingsTabs.forEach((tab) => {
                    const isActive = tab.dataset.settingsTab === nextPanelId;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                settingsPanels.forEach((panel) => {
                    panel.hidden = panel.dataset.settingsPanel !== nextPanelId;
                });

                const activePanel = settingsPanels.find((panel) => panel.dataset.settingsPanel === nextPanelId);
                const activeLabel = activePanel?.dataset.settingsPanelLabel || 'Workspace';
                const activeTab = settingsTabs.find((tab) => tab.dataset.settingsTab === nextPanelId);
                const activeDescription = activeTab?.dataset.settingsDescription || '';

                if (settingsPanelTitle) {
                    settingsPanelTitle.textContent = activeLabel;
                }

                if (settingsPanelDescription) {
                    settingsPanelDescription.textContent = activeDescription;
                }

                if (settingsPanelInput) {
                    settingsPanelInput.value = nextPanelId;
                }

                if (persist) {
                    window.localStorage.setItem('central-settings-active-panel', nextPanelId);
                    window.history.replaceState({}, '', `#${nextPanelId}`);
                }
            };

            settingsTabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    activateSettingsPanel(tab.dataset.settingsTab || defaultPanelId);
                });
            });

            const initialPanelFromHash = window.location.hash.replace('#', '').trim();
            const initialPanelFromStorage = window.localStorage.getItem('central-settings-active-panel') || '';
            activateSettingsPanel(initialPanelFromHash || initialPanelFromStorage || defaultPanelId, false);

            const previewRoot = document.querySelector('[data-settings-preview-root]');

            if (!previewRoot) {
                return;
            }

            const valueOf = (selector, fallback = '') => {
                const node = document.querySelector(selector);

                if (!node) {
                    return fallback;
                }

                const value = 'value' in node ? String(node.value || '').trim() : '';

                return value !== '' ? value : fallback;
            };

            const setText = (selector, value) => {
                document.querySelectorAll(selector).forEach((node) => {
                    node.textContent = value;
                });
            };

            const syncPreview = () => {
                const defaultBrand = String(previewRoot.dataset.defaultBrand || 'AirCloud').trim();
                const defaultHeadline = String(previewRoot.dataset.defaultHeadline || 'Landing aktif').trim();
                const brandName = valueOf('#experience-brand-name', defaultBrand);
                const headline = valueOf('#experience-headline', defaultHeadline);
                const pageTitle = headline !== '' ? `${headline} | ${brandName}` : brandName;

                setText('[data-preview-brand-name]', brandName);
                setText('[data-preview-headline]', headline);
                setText('[data-preview-page-title]', pageTitle);
            };

            document.querySelectorAll('#platform-experience input, #platform-experience textarea').forEach((field) => {
                field.addEventListener('input', syncPreview);
                field.addEventListener('change', syncPreview);
            });

            syncPreview();
        });
    </script>
@endpush
