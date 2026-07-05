@extends('central.layouts.master')

@section('page_title', 'Tenant Panel')
@section('page_subtitle', 'Overview of tenant workspaces')

@section('content')
    @php
        $currentUser = auth('central')->user();
        $canManageTenants = $currentUser?->canAccessCentral('tenants.manage') ?? false;
        $canViewUsers = $currentUser?->canAccessCentral('users.view') ?? false;
        $canViewSettings = $currentUser?->canAccessCentral('settings.view') ?? false;
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-layer-group"></i> Central</span>
                <h2>Tenant Control Center</h2>
                <p>Manage tenant workspace, platform mapping, and runtime SaaS direction from one panel.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Platform</span>
                    <strong>{{ ucfirst($platformSaasType) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Tenants</span>
                    <strong>{{ $tenantTotals['all'] }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-sliders"></i></span>
                    <div class="stat-copy">
                        <p>Platform</p>
                        <strong>{{ ucfirst($platformSaasType) }}</strong>
                        <span>Default blueprint</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-building"></i></span>
                    <div class="stat-copy">
                        <p>Tenant Total</p>
                        <strong>{{ $tenantTotals['all'] }}</strong>
                        <span>Registered workspace</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-shapes"></i></span>
                    <div class="stat-copy">
                        <p>Mode Tersedia</p>
                        <strong>{{ count($availableSaasTypes) }}</strong>
                        <span>Preset options</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-sitemap"></i></span>
                    <div class="stat-copy">
                        <p>Belum Sinkron</p>
                        <strong>{{ $tenantTotals['mismatch'] }}</strong>
                        <span>Butuh arah pusat</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                    <div class="stat-copy">
                        <p>Projected Billing</p>
                        <strong>Rp{{ number_format((int) $billingDashboard['projected_monthly_total'], 0, ',', '.') }}</strong>
                        <span>Recurring bulan ini</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    <div class="stat-copy">
                        <p>Outstanding</p>
                        <strong>Rp{{ number_format((int) $billingDashboard['outstanding_total'], 0, ',', '.') }}</strong>
                        <span>{{ $billingDashboard['invoice_overdue_count'] }} invoice overdue</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-ban"></i></span>
                    <div class="stat-copy">
                        <p>Access Block</p>
                        <strong>{{ $billingDashboard['blocked_count'] }}</strong>
                        <span>{{ $billingDashboard['invoice_blocked_count'] }} karena billing</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Recent Tenants</h3>
                        <p class="card-subtitle">Cari, filter, dan sinkron tenant dari panel pusat.</p>
                    </div>
                    <div class="inline-actions">
                        @if ($canViewSettings)
                            <a class="central-btn-secondary" href="{{ route('central.super-admin.settings.edit') }}">Platform Settings</a>
                        @endif
                    </div>
                </div>

                <div class="form-stack" style="margin-bottom: 18px;">
                    <form method="GET" action="{{ route('central.super-admin.tenants.index') }}" class="inline-actions">
                        <input
                            class="field"
                            type="text"
                            name="q"
                            value="{{ $filters['q'] }}"
                            placeholder="Cari tenant id atau nama"
                            style="flex: 1 1 220px;"
                        >
                        <select name="saas_type" style="flex: 0 1 200px;">
                            <option value="">Semua mode</option>
                            @foreach ($availableSaasTypes as $saasType)
                                <option value="{{ $saasType }}" @selected($filters['saas_type'] === $saasType)>
                                    {{ ucfirst($saasType) }}
                                </option>
                            @endforeach
                        </select>
                        <button class="central-btn-secondary" type="submit">Filter</button>
                        @if ($filters['q'] !== '' || $filters['saas_type'] !== '')
                            <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.index') }}">Reset</a>
                        @endif
                    </form>

                    @if ($canManageTenants)
                        <form method="POST" action="{{ route('central.super-admin.tenants.sync-platform') }}" class="form-stack">
                            @csrf
                            <label class="checkbox-row">
                                <input type="checkbox" name="sync_branding" value="1" checked style="margin-top: 3px;">
                                <span>
                                    <strong style="display: block;">Sinkron juga tema & deskripsi tenant</strong>
                                    <span class="muted">Brand name tenant tetap dipertahankan.</span>
                                </span>
                            </label>

                            <div class="inline-actions">
                                <button
                                    class="central-btn"
                                    type="submit"
                                    data-confirm
                                    data-confirm-variant="danger"
                                    data-confirm-title="Sinkron Tenant Lama"
                                    data-confirm-message="Sinkronkan tenant lama ke mode {{ ucfirst($platformSaasType) }} sekarang? Tenant yang belum sesuai akan diubah mengikuti blueprint pusat."
                                    data-confirm-confirm-label="Ya, sinkronkan"
                                >
                                    Sinkron Tenant Lama
                                </button>
                                <span class="status-muted">{{ $tenantTotals['aligned'] }} sudah sesuai, {{ $tenantTotals['mismatch'] }} belum sinkron</span>
                            </div>
                        </form>
                    @else
                        <div class="status-muted">Role ini hanya bisa lihat tenant. Aksi sinkron tenant lama dibatasi untuk role manage.</div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Name</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tenants as $tenant)
                                @php
                                    $tenantPackageCode = $tenant->packageCode() ?: $defaultPackageCode;
                                    $tenantPackage = $packageCatalog[$tenantPackageCode] ?? null;
                                    $billingSummary = $tenantBillingSummaries[$tenant->id] ?? null;
                                    $subscriptionStatus = (string) data_get($billingSummary, 'status', 'active');
                                    $subscriptionLabel = match ($subscriptionStatus) {
                                        'trial' => 'Trial',
                                        'grace' => 'Grace',
                                        'expired' => 'Expired',
                                        'suspended' => 'Suspended',
                                        default => 'Active',
                                    };
                                    $subscriptionClass = match ($subscriptionStatus) {
                                        'expired', 'suspended' => 'status-muted',
                                        'grace' => 'status-pending',
                                        default => 'status-active',
                                    };
                                    $latestInvoice = data_get($billingSummary, 'latest_invoice');
                                    $latestInvoiceStatus = (string) data_get($latestInvoice, 'status', 'issued');
                                    $latestInvoiceLabel = match ($latestInvoiceStatus) {
                                        'paid' => 'Paid',
                                        'overdue' => 'Overdue',
                                        'void' => 'Void',
                                        'draft' => 'Draft',
                                        default => 'Issued',
                                    };
                                    $latestInvoiceClass = match ($latestInvoiceStatus) {
                                        'paid' => 'status-active',
                                        'overdue' => 'status-pending',
                                        'void' => 'status-muted',
                                        default => 'status-pending',
                                    };
                                    $accessBlock = data_get($billingSummary, 'access_block', []);
                                    $accessReason = (string) data_get($accessBlock, 'reason', '');
                                    $accessLabel = (string) data_get($accessBlock, 'label', ($tenant->isSuspended() ? 'Suspended Manual' : 'Active'));
                                    $accessClass = match ($accessReason) {
                                        'manual_suspend', 'invoice_overdue', 'subscription_expired' => 'status-muted',
                                        default => 'status-active',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $tenant->id }}</strong><br>
                                        <span class="muted">{{ $tenant->id }}.{{ config('tenancy.central_domains.0') }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $tenant->name }}</strong><br>
                                        <span class="muted">{{ $tenantPackage['label'] ?? ucfirst($tenantPackageCode) }} Package</span>
                                        @if ($billingSummary)
                                            <br><span class="muted">Invoice: Rp{{ number_format((int) data_get($billingSummary, 'invoice.invoice_total', 0), 0, ',', '.') }}</span>
                                            @if ($latestInvoice)
                                                <br><span class="{{ $latestInvoiceClass }}">{{ $latestInvoiceLabel }} · {{ $latestInvoice['invoice_number'] }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        <div class="form-stack" style="gap: 8px;">
                                            <span class="status-pending">{{ ucfirst((string) ($tenant->saas_type ?? 'universal')) }}</span>
                                            <span class="{{ (($tenant->saas_type ?? 'universal') === $platformSaasType) ? 'status-active' : 'status-muted' }}">
                                                {{ (($tenant->saas_type ?? 'universal') === $platformSaasType) ? 'Aligned' : 'Mismatch' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-stack" style="gap: 8px;">
                                            <span class="{{ $accessClass }}">
                                                {{ $accessLabel }}
                                            </span>
                                            <span class="{{ $subscriptionClass }}">{{ $subscriptionLabel }}</span>
                                            @if (data_get($accessBlock, 'grace_ends_at'))
                                                <span class="muted">Grace sampai {{ data_get($accessBlock, 'grace_ends_at')->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-stack" style="gap: 10px;">
                                            <div class="inline-actions">
                                                <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.show', $tenant->id) }}">Detail</a>

                                                @if ($canManageTenants)
                                                    @if ($tenant->isSuspended())
                                                        <form method="POST" action="{{ route('central.super-admin.tenants.activate', $tenant->id) }}">
                                                            @csrf
                                                            <button class="central-btn" type="submit">Activate</button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('central.super-admin.tenants.suspend', $tenant->id) }}">
                                                            @csrf
                                                            <button
                                                                class="central-btn-secondary"
                                                                type="submit"
                                                                data-confirm
                                                                data-confirm-variant="danger"
                                                                data-confirm-title="Suspend Tenant"
                                                                data-confirm-message="Suspend tenant {{ $tenant->id }} sekarang? Landing, login, dan dashboard tenant akan ditahan sementara."
                                                                data-confirm-confirm-label="Ya, suspend"
                                                            >
                                                                Suspend
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </div>

                                            @if ($canManageTenants)
                                                <form method="POST" action="{{ route('central.super-admin.tenants.switch-saas', $tenant->id) }}" class="table-form">
                                                    @csrf
                                                    <select name="saas_type">
                                                        @foreach ($availableSaasTypes as $saasType)
                                                            <option value="{{ $saasType }}" @selected(($tenant->saas_type ?? 'universal') === $saasType)>
                                                                {{ ucfirst($saasType) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button
                                                        class="central-btn"
                                                        type="submit"
                                                        data-confirm
                                                        data-confirm-title="Update Mode Tenant"
                                                        data-confirm-message="Ubah mode tenant {{ $tenant->id }} sekarang? Tenant ini akan mengikuti mode yang dipilih pada form."
                                                        data-confirm-confirm-label="Ya, update"
                                                    >
                                                        Update
                                                    </button>
                                                </form>

                                                <form method="POST" action="{{ route('central.super-admin.tenants.assign-package', $tenant->id) }}" class="table-form">
                                                    @csrf
                                                    <select name="package_code">
                                                        @foreach ($packageCatalog as $packageCode => $package)
                                                            <option value="{{ $packageCode }}" @selected($tenantPackageCode === $packageCode)>
                                                                {{ $package['label'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button
                                                        class="central-btn-secondary"
                                                        type="submit"
                                                        data-confirm
                                                        data-confirm-title="Update Package Tenant"
                                                        data-confirm-message="Ubah package tenant {{ $tenant->id }} sekarang? Limit fitur dan modul tenant akan mengikuti package yang dipilih."
                                                        data-confirm-confirm-label="Ya, update"
                                                    >
                                                        Package
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="muted">Belum ada tenant terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                    </div>

                    <div class="quick-grid">
                        @if ($canViewUsers)
                            <a class="quick-item" href="{{ route('central.super-admin.users.index') }}">
                                <div>
                                    <strong>Pengguna</strong>
                                    <span>Central access</span>
                                </div>
                                <i class="fas fa-arrow-right muted"></i>
                            </a>
                        @endif
                        @if ($canViewSettings)
                            <a class="quick-item" href="{{ route('central.super-admin.settings.edit') }}">
                                <div>
                                    <strong>Platform Settings</strong>
                                    <span>Blueprint & modules</span>
                                </div>
                                <i class="fas fa-arrow-right muted"></i>
                            </a>
                        @endif
                        <a class="quick-item" href="{{ route('central.home') }}">
                            <div>
                                <strong>Landing Central</strong>
                                <span>Public view</span>
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Platform Snapshot</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Current Mode</span>
                            <strong>{{ ucfirst($platformSaasType) }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Tenant Count</span>
                            <strong>{{ $tenantTotals['all'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Aligned</span>
                            <strong>{{ $tenantTotals['aligned'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Mismatch</span>
                            <strong>{{ $tenantTotals['mismatch'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Suspended</span>
                            <strong>{{ $tenantTotals['suspended'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Blocked by Billing</span>
                            <strong>{{ $billingDashboard['invoice_blocked_count'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Subscription Expiring</span>
                            <strong>{{ $billingDashboard['expiring_soon_count'] }}</strong>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Billing Watchlist</h3>
                        </div>
                    </div>

                    <div class="quick-grid">
                        @forelse ($billingDashboard['watchlist'] as $watchItem)
                            <a class="quick-item" href="{{ $watchItem['detail_url'] }}">
                                <div>
                                    <strong>{{ $watchItem['tenant_name'] }}</strong>
                                    <span>{{ $watchItem['block_label'] }}</span>
                                    @if ($watchItem['invoice_number'] !== '')
                                        <span>{{ $watchItem['invoice_number'] }} · Rp{{ number_format((int) $watchItem['invoice_total'], 0, ',', '.') }}</span>
                                    @endif
                                </div>
                                <i class="fas fa-arrow-right muted"></i>
                            </a>
                        @empty
                            <div class="quick-item">
                                <div>
                                    <strong>Billing aman</strong>
                                    <span>Belum ada tenant yang masuk watchlist.</span>
                                </div>
                                <i class="fas fa-circle-check muted"></i>
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>
    </div>
@endsection
