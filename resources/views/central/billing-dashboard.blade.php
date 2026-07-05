@extends('central.layouts.master')

@section('page_title', 'Billing Dashboard')
@section('page_subtitle', 'Ringkasan invoice, outstanding, dan tenant yang butuh perhatian')

@section('content')
    @php
        $currentUser = auth('central')->user();
        $canManageBilling = $currentUser?->canAccessCentral('billing.manage') ?? false;
        $canViewUsers = $currentUser?->canAccessCentral('users.view') ?? false;
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
                <span class="hero-badge"><i class="fas fa-file-invoice-dollar"></i> Billing</span>
                <h2>Billing Control Center</h2>
                <p>Pantau recurring billing, outstanding invoice, access block, dan tenant yang butuh follow up dari satu panel pusat.</p>
            </div>

            <div class="billing-hero-side">
                <div class="hero-meta billing-hero-meta">
                    <div class="hero-meta-card">
                        <span>Platform</span>
                        <strong>{{ ucfirst($platformSaasType) }}</strong>
                    </div>
                    <div class="hero-meta-card">
                        <span>Projected Billing</span>
                        <strong>Rp{{ number_format((int) $billingDashboard['projected_monthly_total'], 0, ',', '.') }}</strong>
                    </div>
                    <div class="hero-meta-card">
                        <span>Outstanding</span>
                        <strong>Rp{{ number_format((int) $billingDashboard['outstanding_total'], 0, ',', '.') }}</strong>
                    </div>
                </div>

                <div class="billing-hero-action-card">
                    <div class="billing-hero-action-copy">
                        <strong>Invoice Generator</strong>
                        <span>
                            @if ($canManageBilling)
                                Generator hanya bikin invoice untuk periode subscription yang sudah due dan belum punya invoice aktif.
                            @else
                                Role ini hanya punya akses baca untuk dashboard billing.
                            @endif
                        </span>
                    </div>

                    <div class="inline-actions billing-hero-actions">
                        @if ($canManageBilling)
                            <form method="POST" action="{{ route('central.super-admin.billing.generate-due-invoices') }}">
                                @csrf
                                <button
                                    class="central-btn"
                                    type="submit"
                                    data-confirm
                                    data-confirm-title="Generate Due Invoices"
                                    data-confirm-message="Generate semua invoice yang sudah jatuh tempo sesuai subscription aktif dan billing cycle package tenant sekarang?"
                                    data-confirm-confirm-label="Ya, generate"
                                >
                                    <i class="fas fa-bolt"></i>
                                    Generate Due Invoices
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Auto Generate Run</h3>
                        <p class="card-subtitle">Jejak run generator invoice otomatis terakhir.</p>
                    </div>
                </div>

                <div class="mini-list">
                    <div class="mini-row">
                        <span>Last Run</span>
                        <strong>{{ $billingAutomation['auto_generate']['ran_at']?->format('d M Y H:i') ?? 'Belum pernah' }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Source</span>
                        <strong>{{ $billingAutomation['auto_generate']['source'] !== '' ? ucfirst($billingAutomation['auto_generate']['source']) : '-' }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Invoice Generated</span>
                        <strong>{{ $billingAutomation['auto_generate']['generated_count'] }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Tenant Affected</span>
                        <strong>{{ $billingAutomation['auto_generate']['tenant_count'] }}</strong>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Reminder Scan Run</h3>
                        <p class="card-subtitle">Ringkasan overdue dan subscription yang perlu follow up.</p>
                    </div>
                </div>

                <div class="mini-list">
                    <div class="mini-row">
                        <span>Last Run</span>
                        <strong>{{ $billingAutomation['reminders']['ran_at']?->format('d M Y H:i') ?? 'Belum pernah' }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Source</span>
                        <strong>{{ $billingAutomation['reminders']['source'] !== '' ? ucfirst($billingAutomation['reminders']['source']) : '-' }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Overdue</span>
                        <strong>{{ $billingAutomation['reminders']['overdue_count'] }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Expiring Soon</span>
                        <strong>{{ $billingAutomation['reminders']['expiring_soon_count'] }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Reminder Window</span>
                        <strong>{{ max((int) ($billingAutomation['reminders']['reminder_days'] ?? 0), 0) ?: 7 }} hari</strong>
                    </div>
                    <div class="mini-row">
                        <span>Telegram</span>
                        <strong>{{ ucfirst((string) data_get($billingAutomation, 'reminders.notification_delivery.telegram.status', 'skipped')) }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>WhatsApp</span>
                        <strong>{{ ucfirst((string) data_get($billingAutomation, 'reminders.notification_delivery.whatsapp.status', 'skipped')) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-rotate"></i></span>
                    <div class="stat-copy">
                        <p>Recurring</p>
                        <strong>Rp{{ number_format((int) $billingDashboard['projected_monthly_total'], 0, ',', '.') }}</strong>
                        <span>Proyeksi tagihan aktif</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-clock"></i></span>
                    <div class="stat-copy">
                        <p>Outstanding</p>
                        <strong>Rp{{ number_format((int) $billingDashboard['outstanding_total'], 0, ',', '.') }}</strong>
                        <span>{{ $billingDashboard['invoice_issued_count'] }} invoice belum clear</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-circle-check"></i></span>
                    <div class="stat-copy">
                        <p>Paid</p>
                        <strong>{{ $billingDashboard['invoice_paid_count'] }}</strong>
                        <span>Rp{{ number_format((int) $billingDashboard['latest_paid_total'], 0, ',', '.') }} terkini</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-ban"></i></span>
                    <div class="stat-copy">
                        <p>Access Block</p>
                        <strong>{{ $billingDashboard['blocked_count'] }}</strong>
                        <span>{{ $billingDashboard['invoice_blocked_count'] }} billing, {{ $billingDashboard['subscription_blocked_count'] }} subscription</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Billing Watchlist</h3>
                        <p class="card-subtitle">Tenant prioritas yang sedang overdue, keblokir, atau butuh tindakan cepat.</p>
                    </div>
                    <div class="inline-actions">
                        @if ($canViewUsers)
                            <a class="central-btn-secondary" href="{{ route('central.super-admin.users.index') }}">Pengguna</a>
                        @endif
                        <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.index') }}">Buka Tenant Panel</a>
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
                                @if ($watchItem['grace_ends_at'])
                                    <span>Grace sampai {{ $watchItem['grace_ends_at']->format('d M Y H:i') }}</span>
                                @endif
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>
                    @empty
                        <div class="quick-item">
                            <div>
                                <strong>Semua aman</strong>
                                <span>Belum ada tenant yang masuk watchlist billing.</span>
                            </div>
                            <i class="fas fa-circle-check muted"></i>
                        </div>
                    @endforelse
                </div>
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Billing Snapshot</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Issued</span>
                            <strong>{{ $billingDashboard['invoice_issued_count'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Overdue</span>
                            <strong>{{ $billingDashboard['invoice_overdue_count'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Expiring Soon</span>
                            <strong>{{ $billingDashboard['expiring_soon_count'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Blocked by Billing</span>
                            <strong>{{ $billingDashboard['invoice_blocked_count'] }}</strong>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Recent Invoices</h3>
                        <p class="card-subtitle">Invoice terbaru lintas tenant dengan status terakhir.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Tenant</th>
                                <th>Periode</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentInvoices as $invoice)
                                @php
                                    $statusClass = match ($invoice['status']) {
                                        'paid' => 'status-active',
                                        'overdue' => 'status-pending',
                                        'void' => 'status-muted',
                                        'draft' => 'status-muted',
                                        default => 'status-pending',
                                    };
                                    $statusLabel = match ($invoice['status']) {
                                        'paid' => 'Paid',
                                        'overdue' => 'Overdue',
                                        'void' => 'Void',
                                        'draft' => 'Draft',
                                        default => 'Issued',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $invoice['invoice_number'] }}</strong><br>
                                        <span class="muted">Due {{ $invoice['due_at']?->format('d M Y') ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ $invoice['detail_url'] }}" style="color: inherit;">
                                            <strong>{{ $invoice['tenant_name'] }}</strong>
                                        </a><br>
                                        <span class="muted">{{ $invoice['tenant_id'] }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $invoice['period_label'] ?: '-' }}</strong><br>
                                        <span class="muted">{{ $invoice['issued_at']?->format('d M Y H:i') ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <strong>Rp{{ number_format((int) $invoice['invoice_total'], 0, ',', '.') }}</strong>
                                    </td>
                                    <td>
                                        <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="muted">Belum ada invoice yang terekam.</td>
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
                            <h3 class="card-title">Overdue Queue</h3>
                        </div>
                    </div>

                    <div class="quick-grid">
                        @forelse ($overdueTenants as $tenantRow)
                            <a class="quick-item" href="{{ $tenantRow['detail_url'] }}">
                                <div>
                                    <strong>{{ $tenantRow['name'] }}</strong>
                                    <span>{{ $tenantRow['invoice_number'] ?: 'Belum ada invoice aktif' }}</span>
                                    <span>Rp{{ number_format((int) $tenantRow['invoice_total'], 0, ',', '.') }}</span>
                                    @if ($tenantRow['grace_ends_at'])
                                        <span>Grace sampai {{ $tenantRow['grace_ends_at']->format('d M Y H:i') }}</span>
                                    @endif
                                </div>
                                <i class="fas fa-arrow-right muted"></i>
                            </a>
                        @empty
                            <div class="quick-item">
                                <div>
                                    <strong>Tidak ada overdue</strong>
                                    <span>Belum ada tenant yang invoice terbarunya overdue.</span>
                                </div>
                                <i class="fas fa-circle-check muted"></i>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Subscription Soon</h3>
                        </div>
                    </div>

                    <div class="quick-grid">
                        @forelse ($expiringSoonTenants as $tenantRow)
                            <a class="quick-item" href="{{ $tenantRow['detail_url'] }}">
                                <div>
                                    <strong>{{ $tenantRow['name'] }}</strong>
                                    <span>{{ ucfirst($tenantRow['subscription_status']) }}</span>
                                    <span>Expire {{ $tenantRow['expires_at']?->format('d M Y H:i') ?? '-' }}</span>
                                </div>
                                <i class="fas fa-arrow-right muted"></i>
                            </a>
                        @empty
                            <div class="quick-item">
                                <div>
                                    <strong>Belum ada yang dekat expiry</strong>
                                    <span>Subscription tenant masih aman untuk 7 hari ke depan.</span>
                                </div>
                                <i class="fas fa-circle-check muted"></i>
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>

        <section class="dashboard-card">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Tenant Billing Matrix</h3>
                    <p class="card-subtitle">Status billing terakhir semua tenant dari panel pusat.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Access</th>
                            <th>Subscription</th>
                            <th>Latest Invoice</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenantRows as $tenantRow)
                            @php
                                $invoiceClass = match ($tenantRow['invoice_status']) {
                                    'paid' => 'status-active',
                                    'overdue' => 'status-pending',
                                    'void' => 'status-muted',
                                    'draft' => 'status-muted',
                                    default => 'status-pending',
                                };
                                $accessClass = $tenantRow['access_label'] === 'Active' ? 'status-active' : 'status-muted';
                                $subscriptionClass = match ($tenantRow['subscription_status']) {
                                    'trial' => 'status-pending',
                                    'grace' => 'status-pending',
                                    'expired' => 'status-muted',
                                    default => 'status-active',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $tenantRow['name'] }}</strong><br>
                                    <span class="muted">{{ $tenantRow['id'] }}</span>
                                </td>
                                <td>
                                    <span class="{{ $accessClass }}">{{ $tenantRow['access_label'] }}</span><br>
                                    <span class="muted">{{ $tenantRow['access_reason'] }}</span>
                                </td>
                                <td>
                                    <span class="{{ $subscriptionClass }}">{{ ucfirst($tenantRow['subscription_status']) }}</span><br>
                                    <span class="muted">{{ $tenantRow['expires_at']?->format('d M Y H:i') ?? '-' }}</span>
                                </td>
                                <td>
                                    <span class="{{ $invoiceClass }}">{{ strtoupper($tenantRow['invoice_status']) }}</span><br>
                                    <span class="muted">{{ $tenantRow['invoice_number'] ?: 'Belum ada invoice' }}</span><br>
                                    <strong>Rp{{ number_format((int) $tenantRow['invoice_total'], 0, ',', '.') }}</strong>
                                </td>
                                <td>
                                    <a class="central-btn-secondary" href="{{ $tenantRow['detail_url'] }}">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="muted">Belum ada tenant yang bisa dianalisis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .billing-hero-side {
            display: grid;
            gap: 14px;
            min-width: min(100%, 360px);
            max-width: 420px;
            width: 100%;
        }

        .billing-hero-meta {
            min-width: 0;
        }

        .billing-hero-action-card {
            display: grid;
            gap: 14px;
            padding: 16px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.12);
        }

        .billing-hero-action-copy strong {
            display: block;
            font-size: 0.9375rem;
            font-weight: 700;
            color: #ffffff;
        }

        .billing-hero-action-copy span {
            display: block;
            margin-top: 6px;
            font-size: 0.8125rem;
            line-height: 1.55;
            color: rgba(255, 255, 255, 0.82);
        }

        .billing-hero-actions {
            width: 100%;
        }

        .billing-hero-actions form {
            width: 100%;
        }

        .billing-hero-actions .central-btn {
            width: 100%;
            justify-content: center;
        }

        @media (max-width: 1080px) {
            .billing-hero-side {
                max-width: none;
            }
        }
    </style>
@endpush
