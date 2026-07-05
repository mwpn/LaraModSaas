@extends('central.layouts.master')

@section('page_title', $pageHeading)
@section('page_subtitle', $pageDescription)

@section('content')
    @php
        $billingCycleLabels = [
            'monthly' => 'Bulanan',
            'quarterly' => 'Per 3 Bulan',
            'yearly' => 'Tahunan',
        ];
    @endphp

    <style>
        .package-editor-shell {
            display: grid;
            gap: 20px;
        }

        .package-editor-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .package-editor-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.6fr);
            gap: 20px;
            align-items: start;
        }

        .package-editor-main,
        .package-editor-aside {
            display: grid;
            gap: 16px;
            align-content: start;
        }

        .package-tab-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: #ffffff;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 96px;
            z-index: 1;
        }

        .package-tab-btn {
            border: 0;
            padding: 10px 14px;
            border-radius: 12px;
            background: transparent;
            color: var(--muted);
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }

        .package-tab-btn.is-active {
            background: color-mix(in srgb, var(--primary) 10%, #ffffff);
            color: var(--text);
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary) 18%, var(--border));
        }

        .package-tab-panels {
            display: grid;
            gap: 16px;
        }

        .package-tab-panel[hidden] {
            display: none;
        }

        .package-form-card {
            background: #ffffff;
            box-shadow: var(--shadow-sm);
        }

        .package-section-intro {
            margin-bottom: 14px;
        }

        .package-section-intro p {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1.65;
        }

        .package-field-note {
            display: block;
            margin-top: 8px;
            font-size: 0.78rem;
            color: var(--muted);
        }

        .package-form-grid {
            display: grid;
            gap: 14px;
        }

        .package-form-grid.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .package-check-grid {
            display: grid;
            gap: 10px;
        }

        .package-billing-stack {
            display: grid;
            gap: 14px;
        }

        .package-billing-card {
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #f8fafc;
        }

        .package-billing-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .package-billing-head strong,
        .package-billing-head span {
            display: block;
        }

        .package-billing-head span {
            margin-top: 6px;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.65;
        }

        .switch-row {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            white-space: nowrap;
        }

        .package-summary-card {
            padding: 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: #ffffff;
            box-shadow: var(--shadow-sm);
        }

        .package-summary-card.muted {
            background: #f8fafc;
        }

        .package-summary-label,
        .package-summary-card strong,
        .package-summary-card p {
            display: block;
        }

        .package-summary-label {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .package-summary-card strong {
            margin-top: 10px;
            font-size: 1.05rem;
            color: var(--text);
        }

        .package-summary-card p {
            margin: 10px 0 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.875rem;
        }

        .package-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .package-summary-metric {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #ffffff;
            box-shadow: var(--shadow-sm);
        }

        .package-summary-metric span,
        .package-summary-metric strong {
            display: block;
        }

        .package-summary-metric span {
            font-size: 0.72rem;
            color: var(--muted);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .package-summary-metric strong {
            margin-top: 8px;
            color: var(--text);
            font-size: 0.98rem;
        }

        .package-summary-list {
            margin: 12px 0 0;
            padding-left: 18px;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.7;
        }

        .package-editor-savebar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            position: sticky;
            bottom: 12px;
            padding: 18px;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(8px);
        }

        @media (max-width: 1100px) {
            .package-editor-layout {
                grid-template-columns: 1fr;
            }

            .package-summary-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .package-tab-nav {
                position: static;
            }
        }

        @media (max-width: 767px) {
            .package-editor-topbar,
            .package-editor-savebar {
                flex-direction: column;
                align-items: stretch;
            }

            .package-form-grid.two-col,
            .package-summary-grid {
                grid-template-columns: 1fr;
            }

            .package-tab-nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
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
                <span class="hero-badge"><i class="fas fa-wallet"></i> Package Workspace</span>
                <h2>{{ $pageHeading }}</h2>
                <p>{{ $pageDescription }}</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Platform</span>
                    <strong>{{ ucfirst($platformType) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Total Package</span>
                    <strong>{{ count($packages) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Default</span>
                    <strong>{{ $defaultPackageCode ? ucfirst($defaultPackageCode) : '-' }}</strong>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ $formAction }}" class="package-editor-shell">
            @csrf

            <div class="package-editor-topbar">
                <div>
                    <h3 class="card-title">{{ $pageMode === 'create' ? 'Konfigurasi Package Baru' : 'Konfigurasi Package' }}</h3>
                    <p class="card-subtitle">Form ini sekarang punya halaman sendiri biar lebih enak dikerjain dan nggak sumpek.</p>
                </div>
                <div class="inline-actions">
                    <a class="central-btn-secondary" href="{{ route('central.super-admin.packages.index') }}">Kembali ke Daftar</a>
                    <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.index') }}">Tenant Panel</a>
                </div>
            </div>

            <div class="package-editor-layout">
                @include('central.partials.package-form-fields', [
                    'formId' => 'package-editor-' . $pageMode . '-' . ($package['code'] ?: 'new'),
                    'namePrefix' => '',
                    'package' => $package,
                    'showCodeField' => $showCodeField,
                    'featureCatalog' => $featureCatalog,
                    'moduleCatalog' => $moduleCatalog,
                    'billingCycleLabels' => $billingCycleLabels,
                    'platformType' => $platformType,
                ])
            </div>

            <div class="package-editor-savebar">
                <div>
                    <strong style="display: block; color: var(--text);">{{ $pageMode === 'create' ? 'Simpan package baru' : 'Simpan perubahan package' }}</strong>
                    <span class="status-muted">
                        @if ($pageMode === 'create')
                            Kalau dicentang default, tenant baru langsung pakai package ini.
                        @else
                            Kode package tetap dipertahankan untuk menjaga mapping tenant yang sudah ada.
                        @endif
                    </span>
                </div>
                <div class="inline-actions">
                    <a class="central-btn-secondary" href="{{ route('central.super-admin.packages.index') }}">Batal</a>
                    <button class="central-btn" type="submit">{{ $submitLabel }}</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const formatCurrency = (value) => `Rp${new Intl.NumberFormat('id-ID').format(Math.max(Number(value) || 0, 0))}`;

            const activateTab = (root, tabName = 'general') => {
                if (!root) {
                    return;
                }

                root.querySelectorAll('[data-package-tab-trigger]').forEach((button) => {
                    const isActive = button.getAttribute('data-package-tab-trigger') === tabName;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                root.querySelectorAll('[data-package-tab-panel]').forEach((panel) => {
                    const isActive = panel.getAttribute('data-package-tab-panel') === tabName;
                    panel.hidden = !isActive;
                    panel.classList.toggle('is-active', isActive);
                });
            };

            const refreshPreview = () => {
                const form = document.querySelector('.package-editor-shell');

                if (!form) {
                    return;
                }

                const labelInput = form.querySelector('[data-package-preview-label]');
                const descriptionInput = form.querySelector('[data-package-preview-description]');
                const legacyPriceInput = form.querySelector('[data-package-preview-legacy-price]');
                const labelNode = document.querySelector('[data-package-preview-label-node]');
                const descriptionNode = document.querySelector('[data-package-preview-description-node]');
                const legacyPriceNode = document.querySelector('[data-package-preview-legacy-price-node]');

                if (labelNode) {
                    labelNode.textContent = (labelInput?.value || '').trim() || 'Package Baru';
                }

                if (descriptionNode) {
                    descriptionNode.textContent = (descriptionInput?.value || '').trim() || 'Belum ada deskripsi package.';
                }

                if (legacyPriceNode) {
                    legacyPriceNode.textContent = formatCurrency(legacyPriceInput?.value || 0);
                }

                const setupEnabled = form.querySelector('[data-billing-enabled="setup_fee"]')?.checked;
                const monthlyEnabled = form.querySelector('[data-billing-enabled="monthly_base"]')?.checked;
                const perCustomerEnabled = form.querySelector('[data-billing-enabled="per_customer"]')?.checked;
                const perSuccessEnabled = form.querySelector('[data-billing-enabled="per_success_transaction"]')?.checked;
                const perCheckoutEnabled = form.querySelector('[data-billing-enabled="per_checkout"]')?.checked;
                const percentageEnabled = form.querySelector('[data-billing-enabled="transaction_percentage"]')?.checked;

                const setupFee = setupEnabled ? Number(form.querySelector('[data-billing-amount="setup_fee"]')?.value || 0) : 0;
                let monthlyBill = monthlyEnabled ? Number(form.querySelector('[data-billing-amount="monthly_base"]')?.value || 0) : 0;

                if (perCustomerEnabled) {
                    monthlyBill += Number(form.querySelector('[data-billing-amount="per_customer"]')?.value || 0)
                        * Number(form.querySelector('[data-billing-sample-qty="per_customer"]')?.value || 0);
                }

                if (perSuccessEnabled) {
                    monthlyBill += Number(form.querySelector('[data-billing-amount="per_success_transaction"]')?.value || 0)
                        * Number(form.querySelector('[data-billing-sample-qty="per_success_transaction"]')?.value || 0);
                }

                if (perCheckoutEnabled) {
                    monthlyBill += Number(form.querySelector('[data-billing-amount="per_checkout"]')?.value || 0)
                        * Number(form.querySelector('[data-billing-sample-qty="per_checkout"]')?.value || 0);
                }

                if (percentageEnabled) {
                    monthlyBill += Math.round(
                        (Number(form.querySelector('[data-billing-sample-amount="transaction_percentage"]')?.value || 0)
                            * Number(form.querySelector('[data-billing-rate="transaction_percentage"]')?.value || 0)) / 100
                    );
                }

                const activeRules = [
                    setupEnabled,
                    monthlyEnabled,
                    perCustomerEnabled,
                    perSuccessEnabled,
                    perCheckoutEnabled,
                    percentageEnabled,
                ].filter(Boolean).length;

                const setupNode = document.querySelector('[data-billing-summary-setup]');
                const monthlyNode = document.querySelector('[data-billing-summary-monthly]');
                const firstNode = document.querySelector('[data-billing-summary-first]');
                const rulesNode = document.querySelector('[data-billing-summary-active-rules]');

                if (setupNode) {
                    setupNode.textContent = formatCurrency(setupFee);
                }

                if (monthlyNode) {
                    monthlyNode.textContent = formatCurrency(monthlyBill);
                }

                if (firstNode) {
                    firstNode.textContent = formatCurrency(setupFee + monthlyBill);
                }

                if (rulesNode) {
                    rulesNode.textContent = String(activeRules);
                }
            };

            document.addEventListener('click', (event) => {
                const tabTrigger = event.target.closest('[data-package-tab-trigger]');

                if (!tabTrigger) {
                    return;
                }

                event.preventDefault();

                activateTab(
                    tabTrigger.closest('[data-package-tab-root]'),
                    tabTrigger.getAttribute('data-package-tab-trigger') || 'general'
                );
            });

            document.addEventListener('input', refreshPreview);
            document.addEventListener('change', refreshPreview);
            refreshPreview();
        })();
    </script>
@endsection
