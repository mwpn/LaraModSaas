@extends('central.layouts.master')

@section('page_title', 'Package Settings')
@section('page_subtitle', 'Kelola pricing, limit, fitur, dan modul per paket tenant')

@section('content')
    @php
        $billingCycleLabels = [
            'monthly' => 'Bulanan',
            'quarterly' => 'Per 3 Bulan',
            'yearly' => 'Tahunan',
        ];

        $enabledPackages = collect($packages)->where('enabled', true)->count();
        $highlightPackages = collect($packages)->where('highlight', true)->count();
        $canManagePackages = auth('central')->user()?->canAccessCentral('packages.manage') ?? false;
    @endphp

    <style>
        .package-page {
            display: grid;
            gap: 20px;
        }

        .package-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .package-table-shell {
            display: grid;
            gap: 14px;
        }

        .package-list-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #ffffff;
            box-shadow: var(--shadow-sm);
        }

        .package-main {
            display: grid;
            gap: 14px;
            flex: 1;
        }

        .package-headline {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .package-title h3 {
            margin: 0;
            font-size: 1.05rem;
        }

        .package-title p {
            margin: 6px 0 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.875rem;
        }

        .package-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .package-metrics {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .package-metric {
            padding: 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid var(--border);
        }

        .package-metric span,
        .package-metric strong {
            display: block;
        }

        .package-metric span {
            color: var(--muted);
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .package-metric strong {
            margin-top: 8px;
            color: var(--text);
            font-size: 0.98rem;
        }

        .package-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 240px;
        }

        .package-empty {
            padding: 32px 24px;
            border-radius: 18px;
            border: 1px dashed var(--border);
            background: #ffffff;
            text-align: center;
        }

        .package-empty strong,
        .package-empty span {
            display: block;
        }

        .package-empty span {
            margin-top: 8px;
            color: var(--muted);
        }

        @media (max-width: 1279px) {
            .package-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 1023px) {
            .package-list-card,
            .package-modal-body {
                grid-template-columns: 1fr;
            }

            .package-list-card {
                align-items: stretch;
            }

            .package-actions {
                justify-content: flex-start;
                min-width: 0;
            }
        }

        @media (max-width: 767px) {
            .package-toolbar,
            .package-headline {
                flex-direction: column;
                align-items: stretch;
            }

            .package-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>

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
                <span class="hero-badge"><i class="fas fa-wallet"></i> Packages</span>
                <h2>Pricing & Package Control</h2>
                <p>Atur paket tenant dari pusat: harga, limit operasional, fitur premium, modul yang diizinkan, dan default package untuk tenant baru.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Platform</span>
                    <strong>{{ ucfirst($platformType) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Packages</span>
                    <strong>{{ count($packages) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Default</span>
                    <strong>{{ ucfirst($defaultPackageCode) }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-tags"></i></span>
                    <div class="stat-copy">
                        <p>Default Package</p>
                        <strong>{{ ucfirst($defaultPackageCode) }}</strong>
                        <span>Tenant baru ikut preset ini</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-badge-check"></i></span>
                    <div class="stat-copy">
                        <p>Enabled Packages</p>
                        <strong>{{ $enabledPackages }}</strong>
                        <span>Aktif untuk provisioning</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-crown"></i></span>
                    <div class="stat-copy">
                        <p>Highlight Plans</p>
                        <strong>{{ $highlightPackages }}</strong>
                        <span>Paket unggulan aktif</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-cubes"></i></span>
                    <div class="stat-copy">
                        <p>Module Catalog</p>
                        <strong>{{ count($moduleCatalog) }}</strong>
                        <span>Siap dipetakan ke paket</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="package-page">
                <div class="package-toolbar">
                    <div>
                        <h3 class="card-title">Daftar Package</h3>
                        <p class="card-subtitle">Semua package tenant dikelola dari halaman ini. Tambah dan edit sekarang pindah ke halaman kerja terpisah.</p>
                    </div>
                    <div class="inline-actions">
                        <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.index') }}">Tenant Panel</a>
                        @if ($canManagePackages)
                            <a class="central-btn" href="{{ route('central.super-admin.packages.create') }}">Tambah Package</a>
                        @endif
                    </div>
                </div>

                <div class="package-table-shell">
                    @forelse ($packages as $package)
                        @php
                            $enabledFeaturesCount = collect($package['features'])->filter()->count();
                            $packageModulesCount = count($package['modules']);
                            $billingPreview = $package['billing_preview'] ?? ['monthly_total' => 0, 'first_invoice_total' => 0, 'setup_fee' => 0];
                            $activeBillingRules = collect($package['billing_components'] ?? [])->filter(fn (array $component) => (bool) ($component['enabled'] ?? false))->count();
                        @endphp

                        <article class="package-list-card">
                            <div class="package-main">
                                <div class="package-headline">
                                    <div class="package-title">
                                        <h3>{{ $package['label'] }}</h3>
                                        <p>{{ $package['description'] ?: 'Belum ada deskripsi package.' }}</p>
                                    </div>

                                    <div class="package-badges">
                                        @if ($package['is_default'])
                                            <span class="status-active">Default</span>
                                        @endif
                                        <span class="{{ $package['enabled'] ? 'status-active' : 'status-muted' }}">
                                            {{ $package['enabled'] ? 'Active' : 'Inactive' }}
                                        </span>
                                        @if ($package['highlight'])
                                            <span class="status-pending">Highlight</span>
                                        @endif
                                        <span class="status-muted">{{ $package['code'] }}</span>
                                    </div>
                                </div>

                                <div class="package-metrics">
                                    <div class="package-metric">
                                        <span>Harga</span>
                                        <strong>Rp{{ number_format((int) $package['price_monthly'], 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="package-metric">
                                        <span>Billing</span>
                                        <strong>{{ $billingCycleLabels[$package['billing_cycle']] ?? ucfirst($package['billing_cycle']) }}</strong>
                                    </div>
                                    <div class="package-metric">
                                        <span>Features</span>
                                        <strong>{{ $enabledFeaturesCount }} Aktif</strong>
                                    </div>
                                    <div class="package-metric">
                                        <span>Modules</span>
                                        <strong>{{ $packageModulesCount }} Modul</strong>
                                    </div>
                                    <div class="package-metric">
                                        <span>Billing / Bulan</span>
                                        <strong>Rp{{ number_format((int) ($billingPreview['monthly_total'] ?? 0), 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="package-metric">
                                        <span>Invoice Awal</span>
                                        <strong>Rp{{ number_format((int) ($billingPreview['first_invoice_total'] ?? 0), 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="package-metric">
                                        <span>Setup Fee</span>
                                        <strong>Rp{{ number_format((int) ($billingPreview['setup_fee'] ?? 0), 0, ',', '.') }}</strong>
                                    </div>
                                    <div class="package-metric">
                                        <span>Billing Rules</span>
                                        <strong>{{ $activeBillingRules }} Aktif</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="package-actions">
                                @if ($canManagePackages)
                                    <a class="central-btn-secondary" href="{{ route('central.super-admin.packages.edit', $package['code']) }}">
                                        Edit
                                    </a>

                                    @unless ($package['is_default'])
                                        <form method="POST" action="{{ route('central.super-admin.packages.set-default', $package['code']) }}">
                                            @csrf
                                            <button
                                                class="central-btn-secondary"
                                                type="submit"
                                                data-confirm
                                                data-confirm-title="Jadikan Default Package"
                                                data-confirm-message="Jadikan {{ $package['label'] }} sebagai package default tenant baru?"
                                                data-confirm-confirm-label="Ya, jadikan default"
                                            >
                                                Set Default
                                            </button>
                                        </form>
                                    @endunless

                                    <form method="POST" action="{{ route('central.super-admin.packages.destroy', $package['code']) }}">
                                        @csrf
                                        <button
                                            class="central-btn-secondary"
                                            type="submit"
                                            data-confirm
                                            data-confirm-variant="danger"
                                            data-confirm-title="Hapus Package"
                                            data-confirm-message="Hapus package {{ $package['label'] }} sekarang? Tenant lama tetap menyimpan code package lama sampai di-assign ulang."
                                            data-confirm-confirm-label="Ya, hapus"
                                        >
                                            Hapus
                                        </button>
                                    </form>
                                @else
                                    <span class="status-muted">Read only</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="package-empty">
                            <strong>Belum ada package</strong>
                            <span>Mulai dengan klik tombol `Tambah Package` untuk bikin katalog package tenant pertama.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
