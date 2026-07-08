@extends('basefeature::layouts.master')

@section('page_title', 'Billing Tirta')
@section('page_subtitle', 'Buka periode billing, generate invoice dari baca meter, dan review status tagihan pelanggan')

@php
    $canManageBilling = (bool) ($canManageBilling ?? false);
    $canRecordPayment = (bool) ($canRecordPayment ?? false);
    $isPaymentOperator = $canRecordPayment && ! $canManageBilling;
@endphp

@push('styles')
    <style>
        .billing-grid,
        .billing-stack,
        .period-list,
        .invoice-list,
        .summary-list,
        .line-list,
        .payment-list,
        .rule-list {
            display: grid;
            gap: 16px;
        }
        .billing-grid {
            grid-template-columns: minmax(360px, 420px) minmax(0, 1fr);
            align-items: start;
        }
        .billing-panel,
        .billing-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
        }
        .billing-panel {
            padding: 20px;
        }
        .billing-card {
            padding: 18px;
        }
        .billing-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 16px;
        }
        .billing-head h3,
        .billing-title {
            margin: 0;
            font-size: 1rem;
        }
        .billing-head p,
        .billing-copy {
            margin: 6px 0 0;
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.65;
        }
        .billing-form,
        .billing-form-grid,
        .billing-inline-form {
            display: grid;
            gap: 14px;
        }
        .billing-form-grid.two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .billing-status,
        .billing-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .billing-status.draft {
            color: #9a3412;
            background: #ffedd5;
            border-color: #fdba74;
        }
        .billing-status.generated,
        .billing-status.issued {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #bfdbfe;
        }
        .billing-status.paid {
            color: #166534;
            background: #dcfce7;
            border-color: #bbf7d0;
        }
        .billing-status.closed,
        .billing-status.cancelled {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fecaca;
        }
        .billing-pill {
            background: #f8fafc;
            color: #334155;
            border-color: #e2e8f0;
        }
        .billing-meta,
        .invoice-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .summary-box {
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .summary-box strong {
            display: block;
            color: var(--text);
            font-size: 0.95rem;
        }
        .summary-box span {
            display: block;
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .period-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--primary);
        }
        .record-edit {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--border);
        }
        .record-edit summary {
            cursor: pointer;
            list-style: none;
            font-weight: 700;
            color: var(--primary);
        }
        .record-edit summary::-webkit-details-marker {
            display: none;
        }
        .empty-state {
            padding: 24px;
            border-radius: 16px;
            border: 1px dashed var(--border);
            background: #ffffff;
            color: var(--muted);
            font-size: 0.875rem;
            line-height: 1.7;
        }
        .filter-bar {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
            padding: 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .invoice-card-paid {
            border-color: #bbf7d0;
            background: #f0fdf4;
        }
        .invoice-card-cancelled {
            border-color: #fecaca;
            background: #fef2f2;
        }
        .invoice-card-overdue {
            border-color: #fdba74;
            background: #fff7ed;
        }
        .line-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .line-item strong,
        .line-item span {
            display: block;
        }
        .line-item span {
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--muted);
        }
        .line-total {
            text-align: right;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text);
        }
        .invoice-breakdown {
            display: grid;
            margin-top: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            overflow: hidden;
        }
        .invoice-breakdown-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .invoice-breakdown-head strong {
            display: block;
            font-size: 0.94rem;
            color: var(--text);
        }
        .invoice-breakdown-head span {
            display: block;
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--muted);
        }
        .invoice-breakdown-grid {
            display: grid;
            grid-template-columns: minmax(280px, 0.95fr) minmax(320px, 1.05fr);
        }
        .invoice-breakdown-panel {
            padding: 16px;
        }
        .invoice-breakdown-panel + .invoice-breakdown-panel {
            border-left: 1px solid #e2e8f0;
            background: #fcfcfd;
        }
        .invoice-breakdown-title {
            margin: 0 0 12px;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .invoice-breakdown-row {
            display: grid;
            grid-template-columns: minmax(130px, 180px) minmax(0, 1fr);
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .invoice-breakdown-row:last-child {
            border-bottom: 0;
        }
        .invoice-breakdown-label {
            font-size: 0.8rem;
            color: var(--muted);
        }
        .invoice-breakdown-value {
            font-size: 0.92rem;
            color: var(--text);
            line-height: 1.6;
            font-weight: 700;
        }
        .invoice-breakdown-row.total {
            margin-top: 4px;
            padding-top: 14px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 0;
        }
        .invoice-breakdown-row.total .invoice-breakdown-label,
        .invoice-breakdown-row.total .invoice-breakdown-value {
            color: #0f172a;
            font-weight: 800;
            font-size: 1rem;
        }
        .invoice-breakdown-note {
            margin-top: 10px;
            font-size: 0.78rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .payment-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .payment-item strong,
        .payment-item span {
            display: block;
        }
        .payment-item span {
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--muted);
        }
        .payment-total {
            text-align: right;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text);
        }
        .payment-form {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--border);
        }
        @media (max-width: 1023px) {
            .billing-grid,
            .billing-form-grid.two,
            .filter-bar,
            .invoice-breakdown-grid {
                grid-template-columns: 1fr;
            }
            .invoice-breakdown-panel + .invoice-breakdown-panel {
                border-left: 0;
                border-top: 1px solid #e2e8f0;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $selectedPeriodStatus = (string) data_get($selectedPeriod, 'status', 'draft');
        $today = now()->startOfDay();
        $penaltyEnabled = (bool) ($tenantSetting->getAttribute('billing_penalty_enabled') ?? false);
        $penaltyType = (string) ($tenantSetting->getAttribute('billing_penalty_type') ?? 'fixed');
        $penaltyBase = (string) ($tenantSetting->getAttribute('billing_penalty_base') ?? 'outstanding_total');
        $penaltyStartBasis = (string) ($tenantSetting->getAttribute('billing_penalty_start_basis') ?? 'due_date');
        $penaltyValue = (string) ($tenantSetting->getAttribute('billing_penalty_value') ?? '0');
        $penaltyGraceDays = (int) ($tenantSetting->getAttribute('billing_penalty_grace_days') ?? 0);
        $penaltyMaxAmount = $tenantSetting->getAttribute('billing_penalty_max_amount');
        $penaltyAutoPostOnPayment = (bool) ($tenantSetting->getAttribute('billing_penalty_auto_post_on_payment') ?? true);
        $disconnectAfterMonths = (int) ($tenantSetting->getAttribute('billing_disconnect_after_months') ?? 3);
        $reactivationFeeAmount = (int) ($tenantSetting->getAttribute('billing_reactivation_fee_amount') ?? 0);
        $reactivationDefaultAllowInstallment = (bool) ($tenantSetting->getAttribute('billing_reactivation_default_allow_installment') ?? true);
        $installationFeeAmount = (int) ($tenantSetting->getAttribute('billing_installation_fee_amount') ?? 0);
        $installationAllowInstallment = (bool) ($tenantSetting->getAttribute('billing_installation_allow_installment') ?? false);
        $installationDefaultInstallmentMonths = (int) ($tenantSetting->getAttribute('billing_installation_default_installment_months') ?? 3);
        $installationPromoEnabled = (bool) ($tenantSetting->getAttribute('billing_installation_promo_enabled') ?? false);
        $installationPromoDiscountAmount = (int) ($tenantSetting->getAttribute('billing_installation_promo_discount_amount') ?? 0);
        $installationPromoStartDate = $tenantSetting->getAttribute('billing_installation_promo_start_date');
        $installationPromoEndDate = $tenantSetting->getAttribute('billing_installation_promo_end_date');
        $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if (($autoDraftedPeriods ?? collect())->isNotEmpty())
            <div class="alert alert-success">
                Draft billing otomatis disiapkan untuk {{ $autoDraftedPeriods->count() }} periode baca meter yang sudah closed dan belum punya billing period.
            </div>
        @endif

        @if ((int) data_get($disconnectionReport ?? [], 'disconnected', 0) > 0)
            <div class="alert alert-danger">
                Ada {{ (int) data_get($disconnectionReport, 'disconnected', 0) }} sambungan yang otomatis berubah status menjadi cabut karena tunggakan melewati {{ (int) data_get($disconnectionReport, 'threshold_months', 3) }} bulan.
            </div>
        @endif

        @if ($viewErrors->any())
            <div class="alert alert-danger">
                @foreach ($viewErrors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if (! empty($areaScopeLabel))
            <div class="alert alert-info">
                Data billing dibatasi ke area kerja <strong>{{ $areaScopeLabel }}</strong> dan turunannya.
            </div>
        @endif

        @if ($isPaymentOperator)
            <div class="alert alert-info">
                Akun ini fokus di koleksi pembayaran. Periode, generate invoice, denda, dan pengaturan billing disembunyikan karena butuh otorisasi owner/admin/keuangan.
            </div>
        @endif

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-file-invoice-dollar"></i> TirtaBilling</span>
                <h2>Billing Operasional Tirta</h2>
                <p>Modul ini narik hasil baca meter jadi invoice per sambungan, bantu review tagihan, dan nyiapin jalur pembayaran pelanggan di sprint berikutnya.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Periode Aktif</span>
                    <strong>{{ $selectedPeriod?->name ?? 'Belum dipilih' }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Status</span>
                    <strong>{{ $selectedPeriod ? ucfirst($selectedPeriodStatus) : 'Belum ada' }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Invoice</span>
                    <strong>{{ $selectedPeriod ? $invoices->count() : 0 }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-calendar-days"></i></span>
                    <div class="stat-copy">
                        <p>Billing Period</p>
                        <strong>{{ $invoiceStats['periods'] }}</strong>
                        <span>Periode billing tersimpan</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-file-invoice"></i></span>
                    <div class="stat-copy">
                        <p>Issued</p>
                        <strong>{{ $invoiceStats['issued'] }}</strong>
                        <span>Invoice aktif di periode terpilih</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-circle-check"></i></span>
                    <div class="stat-copy">
                        <p>Paid</p>
                        <strong>{{ $invoiceStats['paid'] }}</strong>
                        <span>Tagihan lunas</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-wallet"></i></span>
                    <div class="stat-copy">
                        <p>Collected</p>
                        <strong>Rp {{ number_format($invoiceStats['collected_total'], 0, ',', '.') }}</strong>
                        <span>Pembayaran tercatat</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-hourglass-half"></i></span>
                    <div class="stat-copy">
                        <p>Sisa Piutang</p>
                        <strong>Rp {{ number_format($invoiceStats['outstanding_total'], 0, ',', '.') }}</strong>
                        <span>Sisa tagihan belum lunas</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-percent"></i></span>
                    <div class="stat-copy">
                        <p>Estimasi Denda</p>
                        <strong>Rp {{ number_format($invoiceStats['estimated_penalty_total'], 0, ',', '.') }}</strong>
                        <span>{{ $penaltyEnabled ? 'Akumulasi dinamis hari ini' : 'Denda tenant belum aktif' }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="billing-grid">
            <div class="billing-stack">
                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Buka Periode Billing</h3>
                            <p>Pilih periode baca meter yang sudah siap, kasih tenggat bayar, lalu periode ini bisa dipakai untuk generate invoice massal. Periode baca meter yang ditutup otomatis disiapkan draft billing-nya.</p>
                        </div>
                    </div>

                    @if ($canManageBilling)
                        <form method="POST" action="{{ route('tenant.tirta.billing-periods.store') }}" class="billing-form">
                            @csrf

                            <div>
                                <label class="field-label" for="billing-period-id">Periode Baca Meter</label>
                                <select id="billing-period-id" name="meter_reading_period_id">
                                    <option value="">Pilih periode baca meter</option>
                                    @foreach ($meterReadingPeriods as $period)
                                        <option value="{{ $period->id }}" @selected(old('meter_reading_period_id') === $period->id)>
                                            {{ $period->name }} · {{ $period->period_start?->format('d M Y') }} - {{ $period->period_end?->format('d M Y') }}
                                            @if ($period->billingPeriod)
                                                · Sudah dipakai
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="field-label" for="billing-name">Nama Billing</label>
                                <input id="billing-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Tagihan Air Juli 2026">
                            </div>

                            <div class="billing-form-grid two">
                                <div>
                                    <label class="field-label" for="billing-start">Mulai Periode</label>
                                    <input id="billing-start" class="field" type="date" name="period_start" value="{{ old('period_start') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="billing-end">Akhir Periode</label>
                                    <input id="billing-end" class="field" type="date" name="period_end" value="{{ old('period_end') }}">
                                </div>
                            </div>

                            <div class="billing-form-grid two">
                                <div>
                                    <label class="field-label" for="billing-due">Jatuh Tempo</label>
                                    <input id="billing-due" class="field" type="date" name="due_date" value="{{ old('due_date') }}">
                                </div>
                                <div>
                                    <label class="field-label" for="billing-status">Status</label>
                                    <select id="billing-status" name="status">
                                        @foreach (['draft' => 'Draft', 'generated' => 'Generated', 'closed' => 'Closed'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="field-label" for="billing-notes">Catatan</label>
                                <textarea id="billing-notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            </div>

                            <button class="tenant-btn" type="submit">Simpan Billing Period</button>
                        </form>
                    @else
                        <div class="summary-box">
                            <strong>Akses terbatas</strong>
                            <span>Pembukaan/ubah periode billing hanya untuk owner/admin/keuangan. Akun ini tetap bisa cek tagihan dan catat pembayaran.</span>
                        </div>
                    @endif
                </div>

                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Daftar Billing Period</h3>
                            <p>Pilih periode aktif untuk review invoice. Generate ulang aman selama periodenya belum ditutup.</p>
                        </div>
                    </div>

                    <div class="period-list">
                        @forelse ($billingPeriods as $period)
                            <div class="billing-card">
                                <div class="billing-head">
                                    <div>
                                        <h4 class="billing-title">{{ $period->name }}</h4>
                                        <p class="billing-copy">
                                            {{ $period->period_start?->format('d M Y') }} - {{ $period->period_end?->format('d M Y') }}
                                            @if ($period->due_date)
                                                · Jatuh tempo {{ $period->due_date->format('d M Y') }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="billing-status {{ $period->status }}">{{ ucfirst($period->status) }}</span>
                                </div>

                                <div class="billing-meta">
                                    <span class="billing-pill">{{ $period->invoices_count }} invoice</span>
                                    <span class="billing-pill">{{ $period->meterReadingPeriod?->readings_count ?? 0 }} bacaan meter</span>
                                    @if ($selectedPeriod?->id === $period->id)
                                        <span class="billing-pill">Periode aktif</span>
                                    @endif
                                </div>

                                @if (filled($period->notes))
                                    <div class="billing-copy">{{ $period->notes }}</div>
                                @endif

                                <div class="inline-actions" style="margin-top: 14px; gap: 10px;">
                                    <a class="period-link" href="{{ route('tenant.tirta.billing', ['period' => $period->id]) }}">
                                        <i class="fas fa-arrow-right"></i> Buka Periode
                                    </a>

                                    @if ($canManageBilling && $period->status !== 'closed')
                                        <form method="POST" action="{{ route('tenant.tirta.billing-periods.generate', $period->id) }}">
                                            @csrf
                                            <button class="tenant-btn-secondary" type="submit">Generate Invoice</button>
                                        </form>
                                    @endif

                                    @if (
                                        $canManageBilling
                                        && $selectedPeriod?->id === $period->id
                                        && $period->status !== 'closed'
                                        && $penaltyEnabled
                                        && ($bulkPenaltySummary['eligible_count'] ?? 0) > 0
                                    )
                                        <form method="POST" action="{{ route('tenant.tirta.billing-periods.penalties.post', $period->id) }}">
                                            @csrf
                                            <button class="tenant-btn-secondary" type="submit">
                                                Posting Denda Massal
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                @if ($period->status === 'closed')
                                    <div class="summary-box" style="margin-top: 14px;">
                                        <strong>Billing Period Closed</strong>
                                        <span>Periode ini sudah ditutup dan tidak bisa diubah lagi.</span>
                                    </div>
                                @elseif ($canManageBilling)
                                    <details class="record-edit">
                                        <summary>Edit Billing Period</summary>
                                        <form method="POST" action="{{ route('tenant.tirta.billing-periods.update', $period->id) }}" class="billing-inline-form" style="margin-top: 14px;">
                                            @csrf
                                            @method('PATCH')

                                            <div>
                                                <label class="field-label">Periode Baca Meter</label>
                                                @if ($period->status === 'generated')
                                                    <input type="hidden" name="meter_reading_period_id" value="{{ $period->meter_reading_period_id }}">
                                                @endif
                                                <select name="meter_reading_period_id" @disabled($period->status === 'generated')>
                                                    @foreach ($meterReadingPeriods as $readingPeriod)
                                                        <option value="{{ $readingPeriod->id }}" @selected($period->meter_reading_period_id === $readingPeriod->id)>
                                                            {{ $readingPeriod->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @if ($period->status === 'generated')
                                                    <div class="billing-copy">Periode meter sumber terkunci setelah invoice digenerate.</div>
                                                @endif
                                            </div>

                                            <div>
                                                <label class="field-label">Nama</label>
                                                <input class="field" type="text" name="name" value="{{ $period->name }}">
                                            </div>

                                            <div class="billing-form-grid two">
                                                <div>
                                                    <label class="field-label">Mulai</label>
                                                    @if ($period->status === 'generated')
                                                        <input type="hidden" name="period_start" value="{{ $period->period_start?->format('Y-m-d') }}">
                                                    @endif
                                                    <input class="field" type="date" name="period_start" value="{{ $period->period_start?->format('Y-m-d') }}" @disabled($period->status === 'generated')>
                                                </div>
                                                <div>
                                                    <label class="field-label">Selesai</label>
                                                    @if ($period->status === 'generated')
                                                        <input type="hidden" name="period_end" value="{{ $period->period_end?->format('Y-m-d') }}">
                                                    @endif
                                                    <input class="field" type="date" name="period_end" value="{{ $period->period_end?->format('Y-m-d') }}" @disabled($period->status === 'generated')>
                                                </div>
                                            </div>

                                            <div class="billing-form-grid two">
                                                <div>
                                                    <label class="field-label">Jatuh Tempo</label>
                                                    <input class="field" type="date" name="due_date" value="{{ $period->due_date?->format('Y-m-d') }}">
                                                </div>
                                                <div>
                                                    <label class="field-label">Status</label>
                                                    <select name="status">
                                                        @foreach (['draft' => 'Draft', 'generated' => 'Generated', 'closed' => 'Closed'] as $value => $label)
                                                            @continue($period->status === 'generated' && $value === 'draft')
                                                            <option value="{{ $value }}" @selected($period->status === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="field-label">Catatan</label>
                                                <textarea name="notes" rows="3">{{ $period->notes }}</textarea>
                                            </div>

                                            <button class="tenant-btn-secondary" type="submit">Update Billing Period</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <div class="empty-state">Belum ada billing period. Pilih periode baca meter yang sudah siap dulu supaya invoice bisa digenerate per sambungan.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <aside class="billing-stack">
                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Pengaturan Denda</h3>
                            <p>Tenant bisa atur denda harian fixed atau persentase. Kalkulasi di bawah ini dipakai sebagai estimasi berjalan per invoice overdue.</p>
                        </div>
                    </div>

                    @if ($canManageBilling)
                        <form method="POST" action="{{ route('tenant.tirta.billing.settings.penalty') }}" class="billing-form">
                            @csrf

                        <label class="checkbox-row">
                            <input type="checkbox" name="billing_penalty_enabled" value="1" @checked(old('billing_penalty_enabled', $penaltyEnabled)) style="margin-top: 3px;">
                            <span>
                                <strong style="display: block;">Aktifkan denda otomatis</strong>
                                <span class="muted">Denda dihitung harian dari tanggal terbit atau jatuh tempo sesuai policy tenant.</span>
                            </span>
                        </label>

                        <label class="checkbox-row">
                            <input type="checkbox" name="billing_penalty_auto_post_on_payment" value="1" @checked(old('billing_penalty_auto_post_on_payment', $penaltyAutoPostOnPayment)) style="margin-top: 3px;">
                            <span>
                                <strong style="display: block;">Auto-post denda saat catat pembayaran</strong>
                                <span class="muted">Kalau pelanggan bayar setelah jatuh tempo, sistem otomatis posting denda (sesuai tanggal bayar) sebelum pembayaran dicatat.</span>
                            </span>
                        </label>

                        <div class="billing-form-grid two">
                            <div>
                                <label class="field-label">Tipe Denda</label>
                                <select name="billing_penalty_type">
                                    @foreach (['fixed' => 'Fixed Cost', 'percentage' => 'Persentase'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billing_penalty_type', $penaltyType) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Basis Hitung</label>
                                <select name="billing_penalty_base">
                                    @foreach (['outstanding_total' => 'Sisa Tagihan', 'invoice_total' => 'Total Invoice'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billing_penalty_base', $penaltyBase) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="billing-form-grid two">
                            <div>
                                <label class="field-label">Mulai Hitung Denda Dari</label>
                                <select name="billing_penalty_start_basis">
                                    @foreach (['due_date' => 'Jatuh Tempo', 'issued_at' => 'Tanggal Terbit'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billing_penalty_start_basis', $penaltyStartBasis) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Grace Days</label>
                                <input class="field" type="number" min="0" max="365" name="billing_penalty_grace_days" value="{{ old('billing_penalty_grace_days', $penaltyGraceDays) }}">
                            </div>
                        </div>

                        <div class="billing-form-grid two">
                            <div>
                                <label class="field-label">Nilai Denda Harian</label>
                                <input class="field" type="number" step="0.0001" min="0" name="billing_penalty_value" value="{{ old('billing_penalty_value', $penaltyValue) }}" placeholder="Contoh: 2000 atau 0.5">
                            </div>
                            <div>
                                <label class="field-label">Maksimal Denda</label>
                                <input class="field" type="number" min="0" name="billing_penalty_max_amount" value="{{ old('billing_penalty_max_amount', $penaltyMaxAmount) }}" placeholder="Kosongkan kalau tanpa batas">
                            </div>
                        </div>

                        <div class="billing-copy">Mode saat ini: denda dihitung `harian`. Bisa diposting per invoice, massal per billing period, atau otomatis saat pencatatan pembayaran.</div>

                            <button class="tenant-btn" type="submit">Simpan Pengaturan Denda</button>
                        </form>
                    @else
                        <div class="summary-box">
                            <strong>Akses terbatas</strong>
                            <span>Pengaturan denda hanya untuk owner/admin/keuangan.</span>
                        </div>
                    @endif
                </div>

                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Cabut & Reaktivasi</h3>
                            <p>Rule operasional untuk sambungan yang menunggak. Setelah melewati ambang bulan tunggakan, status sambungan jadi cabut. Aktif lagi wajib bayar aktivasi.</p>
                        </div>
                    </div>

                    @if ($canManageBilling)
                        <form method="POST" action="{{ route('tenant.tirta.billing.settings.lifecycle') }}" class="billing-form">
                            @csrf

                        <div class="billing-form-grid two">
                            <div>
                                <label class="field-label">Cabut Otomatis (bulan)</label>
                                <input class="field" type="number" min="1" max="24" name="billing_disconnect_after_months" value="{{ old('billing_disconnect_after_months', $disconnectAfterMonths) }}">
                            </div>
                            <div>
                                <label class="field-label">Biaya Aktivasi</label>
                                <input class="field" type="number" min="0" name="billing_reactivation_fee_amount" value="{{ old('billing_reactivation_fee_amount', $reactivationFeeAmount) }}">
                            </div>
                        </div>

                        <label class="checkbox-row">
                            <input type="checkbox" name="billing_reactivation_default_allow_installment" value="1" @checked(old('billing_reactivation_default_allow_installment', $reactivationDefaultAllowInstallment)) style="margin-top: 3px;">
                            <span>
                                <strong style="display: block;">Default boleh cicil tunggakan</strong>
                                <span class="muted">Default ini dipakai saat operator bikin invoice aktivasi dari kartu invoice.</span>
                            </span>
                        </label>

                        <div class="summary-list">
                            <div class="summary-box">
                                <strong>Efek Cabut</strong>
                                <span>Sambungan cabut tidak bisa input baca meter dan tidak ikut generate billing.</span>
                            </div>
                            <div class="summary-box">
                                <strong>Efek Reaktivasi</strong>
                                <span>Wajib bayar aktivasi. Jika cicilan boleh, sambungan aktif setelah aktivasi lunas; jika tidak, wajib lunasi semua tunggakan dulu.</span>
                            </div>
                        </div>

                            <button class="tenant-btn" type="submit">Simpan Pengaturan Cabut</button>
                        </form>
                    @else
                        <div class="summary-box">
                            <strong>Akses terbatas</strong>
                            <span>Pengaturan cabut & reaktivasi hanya untuk owner/admin/keuangan.</span>
                        </div>
                    @endif
                </div>

                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Pasang Baru</h3>
                            <p>Biaya pasang baru adalah invoice non-air. Tenant bisa set tunai atau cicilan, dan aktifkan promo diskon pada periode tertentu.</p>
                        </div>
                    </div>

                    @if ($canManageBilling)
                        <form method="POST" action="{{ route('tenant.tirta.billing.settings.installation') }}" class="billing-form">
                            @csrf

                        <div class="billing-form-grid two">
                            <div>
                                <label class="field-label">Biaya Pasang Baru</label>
                                <input class="field" type="number" min="0" name="billing_installation_fee_amount" value="{{ old('billing_installation_fee_amount', $installationFeeAmount) }}">
                            </div>
                            <div>
                                <label class="field-label">Default Bulan Cicilan</label>
                                <input class="field" type="number" min="2" max="24" name="billing_installation_default_installment_months" value="{{ old('billing_installation_default_installment_months', $installationDefaultInstallmentMonths) }}">
                            </div>
                        </div>

                        <label class="checkbox-row">
                            <input type="checkbox" name="billing_installation_allow_installment" value="1" @checked(old('billing_installation_allow_installment', $installationAllowInstallment)) style="margin-top: 3px;">
                            <span>
                                <strong style="display: block;">Boleh cicilan pasang baru</strong>
                                <span class="muted">Kalau aktif, operator bisa bikin invoice pasang baru dengan opsi cicilan. Sambungan aktif setelah cicilan pertama lunas.</span>
                            </span>
                        </label>

                        <label class="checkbox-row">
                            <input type="checkbox" name="billing_installation_promo_enabled" value="1" @checked(old('billing_installation_promo_enabled', $installationPromoEnabled)) style="margin-top: 3px;">
                            <span>
                                <strong style="display: block;">Aktifkan promo diskon</strong>
                                <span class="muted">Diskon potong langsung dari biaya pasang baru. Bisa dibatasi tanggal mulai dan selesai.</span>
                            </span>
                        </label>

                        <div class="billing-form-grid two">
                            <div>
                                <label class="field-label">Nominal Diskon</label>
                                <input class="field" type="number" min="0" name="billing_installation_promo_discount_amount" value="{{ old('billing_installation_promo_discount_amount', $installationPromoDiscountAmount) }}">
                            </div>
                            <div>
                                <label class="field-label">Catatan</label>
                                <div class="billing-copy">Kalau tanggal promo kosong, promo dianggap selalu aktif selama toggle promo dinyalakan.</div>
                            </div>
                        </div>

                        <div class="billing-form-grid two">
                            <div>
                                <label class="field-label">Mulai Promo</label>
                                <input class="field" type="date" name="billing_installation_promo_start_date" value="{{ old('billing_installation_promo_start_date', $installationPromoStartDate) }}">
                            </div>
                            <div>
                                <label class="field-label">Selesai Promo</label>
                                <input class="field" type="date" name="billing_installation_promo_end_date" value="{{ old('billing_installation_promo_end_date', $installationPromoEndDate) }}">
                            </div>
                        </div>

                            <button class="tenant-btn" type="submit">Simpan Pengaturan Pasang Baru</button>
                        </form>
                    @else
                        <div class="summary-box">
                            <strong>Akses terbatas</strong>
                            <span>Pengaturan pasang baru hanya untuk owner/admin/keuangan.</span>
                        </div>
                    @endif
                </div>

                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Ringkasan Periode Aktif</h3>
                            <p>Snapshot cepat sebelum operator generate ulang atau update status invoice satu-satu.</p>
                        </div>
                    </div>

                    @if ($selectedPeriod)
                        <div class="summary-list">
                            <div class="summary-box">
                                <strong>{{ $selectedPeriod->name }}</strong>
                                <span>{{ $selectedPeriod->period_start?->format('d M Y') }} - {{ $selectedPeriod->period_end?->format('d M Y') }}</span>
                            </div>
                            <div class="summary-box">
                                <strong>Status {{ ucfirst($selectedPeriod->status) }}</strong>
                                <span>{{ $selectedPeriod->generated_at ? 'Terakhir digenerate ' . $selectedPeriod->generated_at->format('d M Y H:i') : 'Belum pernah generate invoice.' }}</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $invoices->count() }} invoice tersedia</strong>
                                <span>{{ $invoiceStats['paid'] }} sudah lunas, {{ $invoiceStats['issued'] }} masih issued, {{ $invoiceStats['cancelled'] }} dibatalkan.</span>
                            </div>
                            <div class="summary-box">
                                <strong>Nilai tagihan Rp {{ number_format($invoiceStats['invoice_total'], 0, ',', '.') }}</strong>
                                <span>Total nominal invoice periode aktif sebelum payment collection.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $bulkPenaltySummary['eligible_count'] ?? 0 }} invoice siap posting denda</strong>
                                <span>
                                    Total kandidat Rp {{ number_format((int) ($bulkPenaltySummary['eligible_total'] ?? 0), 0, ',', '.') }}.
                                    {{ (int) ($bulkPenaltySummary['already_posted_count'] ?? 0) }} sudah ter-post, {{ (int) ($bulkPenaltySummary['blocked_by_payment_count'] ?? 0) }} tertahan karena sudah ada pembayaran.
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="empty-state">Pilih atau buat billing period dulu supaya invoice dan status tagihan bisa direview dari panel ini.</div>
                    @endif
                </div>

                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Summary Baca Meter</h3>
                            <p>Validasi cepat apakah periode sumber sudah cukup aman buat ditagihkan.</p>
                        </div>
                    </div>

                    @if ($readingSummary)
                        <div class="summary-list">
                            <div class="summary-box">
                                <strong>{{ $readingSummary['total_readings'] }} pembacaan</strong>
                                <span>{{ $readingSummary['valid_readings'] }} valid untuk proses billing.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $readingSummary['warning_readings'] }} warning</strong>
                                <span>Bacaan warning tetap bisa digenerate, tapi sebaiknya direview operator.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $readingSummary['invalid_readings'] }} invalid</strong>
                                <span>Bacaan invalid otomatis dilewati saat generate invoice.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ number_format($readingSummary['usage_volume'], 0, ',', '.') }} m3</strong>
                                <span>Total volume pemakaian periode baca meter yang terhubung.</span>
                            </div>
                        </div>
                    @else
                        <div class="empty-state">Belum ada meter reading period yang terhubung ke billing period aktif.</div>
                    @endif
                </div>

                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Ringkasan Piutang</h3>
                            <p>Snapshot nilai tagihan yang masih harus ditagih operator untuk periode aktif.</p>
                        </div>
                    </div>

                    @if ($selectedPeriod)
                        <div class="summary-list">
                            <div class="summary-box">
                                <strong>{{ $receivableStats['open_count'] }} invoice outstanding</strong>
                                <span>Total piutang aktif Rp {{ number_format($receivableStats['open_total'], 0, ',', '.') }}.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $receivableStats['overdue_count'] }} overdue</strong>
                                <span>Nilai overdue Rp {{ number_format($receivableStats['overdue_total'], 0, ',', '.') }} yang perlu follow up duluan.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $receivableStats['due_today_count'] }} jatuh tempo hari ini</strong>
                                <span>{{ $receivableStats['upcoming_count'] }} invoice lain masih upcoming.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $receivableStats['filtered_count'] }} invoice tampil</strong>
                                <span>Angka ini ngikut filter status dan bucket piutang yang dipilih di list review.</span>
                            </div>
                            <div class="summary-box">
                                <strong>Estimasi denda Rp {{ number_format($receivableStats['penalty_total'], 0, ',', '.') }}</strong>
                                <span>{{ $penaltyEnabled ? 'Akumulasi estimasi denda berjalan untuk invoice overdue tenant ini.' : 'Denda tenant belum diaktifkan.' }}</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $bulkPenaltySummary['eligible_count'] ?? 0 }} invoice eligible bulk</strong>
                                <span>
                                    Kandidat bulk posting Rp {{ number_format((int) ($bulkPenaltySummary['eligible_total'] ?? 0), 0, ',', '.') }} untuk invoice issued yang belum ada pembayaran.
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="empty-state">Ringkasan piutang akan muncul setelah billing period dipilih dan invoice tersedia.</div>
                    @endif
                </div>

                <div class="billing-panel">
                    <div class="billing-head">
                        <div>
                            <h3>Aturan Operasional</h3>
                            <p>Guard sprint ini supaya generate billing tetap konsisten dan aman.</p>
                        </div>
                    </div>

                    <div class="rule-list">
                        <div class="summary-box">
                            <strong>Generate pakai hasil baca meter</strong>
                            <span>Invoice ditarik dari `meter_readings`, tarif sambungan, beban minimum, dan beban tetap / abudemen tenant.</span>
                        </div>
                        <div class="summary-box">
                            <strong>Invalid otomatis diskip</strong>
                            <span>Pembacaan meter invalid atau sambungan tanpa tarif tidak akan masuk invoice.</span>
                        </div>
                        <div class="summary-box">
                            <strong>Period closed mengunci update</strong>
                            <span>Kalau billing period ditutup, status invoice di dalamnya tidak bisa diubah lagi.</span>
                        </div>
                        <div class="summary-box">
                            <strong>Draft billing otomatis</strong>
                            <span>Saat periode baca meter ditutup, sistem otomatis menyiapkan draft billing supaya operator tinggal review lalu generate.</span>
                        </div>
                        <div class="summary-box">
                            <strong>Bulk posting denda aman</strong>
                            <span>Denda massal hanya diposting ke invoice `issued` yang belum punya pembayaran, supaya total tagihan tetap konsisten.</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="billing-panel">
            <div class="billing-head">
                <div>
                    <h3>Review Invoice</h3>
                    <p>List invoice per sambungan untuk periode terpilih. Operator bisa filter piutang aktif, cek detail line item, dan update status tagihan langsung dari sini.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('tenant.tirta.billing') }}" class="filter-bar">
                <input type="hidden" name="period" value="{{ $selectedPeriod?->id }}">

                <div>
                    <label class="field-label" for="filter-status">Status Invoice</label>
                    <select id="filter-status" name="status">
                        @foreach (['all' => 'Semua', 'issued' => 'Issued', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="field-label" for="filter-bucket">Bucket Piutang</label>
                    <select id="filter-bucket" name="bucket">
                        @foreach ([
                            'all' => 'Semua bucket',
                            'open' => 'Outstanding',
                            'overdue' => 'Overdue',
                            'due_today' => 'Jatuh tempo hari ini',
                            'upcoming' => 'Belum jatuh tempo',
                            'undated' => 'Tanpa jatuh tempo',
                            'closed_only' => 'Paid atau cancelled',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['bucket'] ?? 'all') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; align-items: end; gap: 10px;">
                    <button class="tenant-btn" type="submit">Terapkan Filter</button>
                    <a class="tenant-btn-secondary" href="{{ route('tenant.tirta.billing', ['period' => $selectedPeriod?->id]) }}">Reset</a>
                </div>
            </form>

            <div class="invoice-list">
                @forelse ($invoices as $invoice)
                    @php
                        $paymentSummary = $paymentSummaries[$invoice->id] ?? [
                            'payments_count' => 0,
                            'paid_total' => 0,
                            'outstanding_total' => (int) $invoice->invoice_total,
                            'is_paid' => false,
                            'last_paid_at' => null,
                        ];
                        $penaltySummary = $penaltySummaries[$invoice->id] ?? [
                            'enabled' => false,
                            'penalty_amount' => 0,
                            'daily_penalty_amount' => 0,
                            'effective_late_days' => 0,
                            'label' => '',
                        ];
                        $payments = $invoice->payments ?? collect();
                        $connectionStatus = (string) ($invoice->connection?->status ?? '');
                        $isDisconnected = $connectionStatus === 'disconnected';
                        $isOverdue = $invoice->status === 'issued' && $invoice->due_date && $invoice->due_date->copy()->startOfDay()->lt($today);
                        $invoiceCardClass = $invoice->status === 'paid'
                            ? 'invoice-card-paid'
                            : ($invoice->status === 'cancelled' ? 'invoice-card-cancelled' : ($isOverdue ? 'invoice-card-overdue' : ''));
                        $snapshot = (array) ($invoice->calculation_snapshot ?? []);
                        $invoiceType = (string) data_get($snapshot, 'invoice_type', 'billing');
                        $paymentScheme = (string) data_get($snapshot, 'payment_scheme', 'cash');
                        $installmentIndex = (int) data_get($snapshot, 'installment_index', 0);
                        $installmentMonths = (int) data_get($snapshot, 'installment_months', 0);
                        $installationLine = $invoice->lines->firstWhere('line_type', 'installation_fee');
                        $customerNumber = $invoice->connection?->service_number ?: '-';
                        $customerName = $invoice->customer?->name ?? 'Tanpa pelanggan';
                        $customerAddress = $invoice->connection?->service_address ?: ($invoice->customer?->address ?? '-');
                        $previousStand = $invoice->meterReading?->previous_reading;
                        $currentStand = $invoice->meterReading?->current_reading;
                        $showMinimumCharge = (int) $invoice->minimum_charge_applied > 0;
                        $showInstallationInstallment = $invoiceType === 'installation' && $paymentScheme === 'installment' && $installationLine;
                    @endphp
                    <div class="billing-card {{ $invoiceCardClass }}">
                        <div class="billing-head">
                            <div>
                                <h4 class="billing-title">{{ $invoice->invoice_number }}</h4>
                                <p class="billing-copy">
                                    {{ $invoice->customer?->name ?? 'Tanpa pelanggan' }} · {{ $invoice->connection?->service_number ?? '-' }}
                                    @if ($invoice->connection?->serviceArea?->name)
                                        · {{ $invoice->connection->serviceArea->name }}
                                    @endif
                                </p>
                            </div>
                            <span class="billing-status {{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                        </div>

                        <div class="invoice-meta">
                            <span class="billing-pill">Pemakaian {{ number_format($invoice->usage_volume, 0, ',', '.') }} m3</span>
                            <span class="billing-pill">Air Rp {{ number_format($invoice->water_charge_total, 0, ',', '.') }}</span>
                            <span class="billing-pill">Beban Tetap Rp {{ number_format($invoice->admin_fee_total, 0, ',', '.') }}</span>
                            @if ((int) ($invoice->penalty_total ?? 0) > 0)
                                <span class="billing-pill">Denda Rp {{ number_format((int) $invoice->penalty_total, 0, ',', '.') }}</span>
                            @endif
                            <span class="billing-pill">Total Rp {{ number_format($invoice->invoice_total, 0, ',', '.') }}</span>
                            <span class="billing-pill">Terbayar Rp {{ number_format((int) $paymentSummary['paid_total'], 0, ',', '.') }}</span>
                            <span class="billing-pill">Sisa Rp {{ number_format((int) $paymentSummary['outstanding_total'], 0, ',', '.') }}</span>
                            <span class="billing-pill">{{ (int) $paymentSummary['payments_count'] }} pembayaran</span>
                            @if ((int) $penaltySummary['penalty_amount'] > 0)
                                <span class="billing-pill">Estimasi Denda Rp {{ number_format((int) $penaltySummary['penalty_amount'], 0, ',', '.') }}</span>
                            @endif
                            @if ($isOverdue)
                                <span class="billing-pill">Overdue</span>
                            @elseif ($invoice->status === 'issued' && $invoice->due_date && $invoice->due_date->isSameDay($today))
                                <span class="billing-pill">Jatuh tempo hari ini</span>
                            @endif
                            @if ($isDisconnected)
                                <span class="billing-pill">Cabut</span>
                            @endif
                        </div>

                        <div class="billing-copy">
                            @if ($invoice->tariffScheme?->name)
                                Tarif {{ $invoice->tariffScheme->name }}
                            @endif
                            @if ($invoice->due_date)
                                <br>Jatuh tempo {{ $invoice->due_date->format('d M Y') }}
                            @endif
                            @if ($invoice->meterReading?->recorded_at)
                                <br>Baca meter {{ $invoice->meterReading->recorded_at->format('d M Y H:i') }}
                            @endif
                            @if ($invoice->paid_at)
                                <br>Lunas {{ $invoice->paid_at->format('d M Y H:i') }}
                            @endif
                            @if (filled($invoice->notes))
                                <br>{{ $invoice->notes }}
                            @endif
                            @if ((int) $penaltySummary['penalty_amount'] > 0)
                                <br>Denda berjalan: {{ $penaltySummary['label'] }}
                                <br>Akumulasi aktif {{ (int) $penaltySummary['effective_late_days'] }} hari · Rp {{ number_format((int) $penaltySummary['daily_penalty_amount'], 0, ',', '.') }}/hari
                            @endif
                            @if ($isDisconnected)
                                <br><strong>Sambungan cabut.</strong> Tidak bisa input baca meter atau generate tagihan sampai reaktivasi.
                            @endif
                        </div>

                        <div class="invoice-breakdown">
                            <div class="invoice-breakdown-head">
                                <div>
                                    <strong>Lembar Tagihan</strong>
                                    <span>Ringkasan siap baca untuk loket, admin, atau pengecekan cepat di workspace.</span>
                                </div>
                                @if ($invoice->due_date)
                                    <div style="text-align: right;">
                                        <strong>Jatuh Tempo</strong>
                                        <span>{{ $invoice->due_date->format('d M Y') }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="invoice-breakdown-grid">
                                <div class="invoice-breakdown-panel">
                                    <h5 class="invoice-breakdown-title">Identitas Pelanggan</h5>

                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Nomor Pelanggan</div>
                                        <div class="invoice-breakdown-value">{{ $customerNumber }}</div>
                                    </div>
                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Nama</div>
                                        <div class="invoice-breakdown-value">{{ $customerName }}</div>
                                    </div>
                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Alamat</div>
                                        <div class="invoice-breakdown-value">{{ $customerAddress }}</div>
                                    </div>
                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Stand Lalu</div>
                                        <div class="invoice-breakdown-value">{{ $previousStand !== null ? number_format((int) $previousStand, 0, ',', '.') : '-' }}</div>
                                    </div>
                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Stand Kini</div>
                                        <div class="invoice-breakdown-value">{{ $currentStand !== null ? number_format((int) $currentStand, 0, ',', '.') : '-' }}</div>
                                    </div>
                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Pemakaian</div>
                                        <div class="invoice-breakdown-value">{{ number_format((int) $invoice->usage_volume, 0, ',', '.') }} m3</div>
                                    </div>

                                    @if ($invoice->meterReading?->recorded_at)
                                        <div class="invoice-breakdown-note">
                                            Tercatat pada {{ $invoice->meterReading->recorded_at->format('d M Y H:i') }}.
                                        </div>
                                    @endif
                                </div>

                                <div class="invoice-breakdown-panel">
                                    <h5 class="invoice-breakdown-title">Rincian Tagihan</h5>

                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Biaya Air</div>
                                        <div class="invoice-breakdown-value">Rp {{ number_format((int) $invoice->water_charge_total, 0, ',', '.') }}</div>
                                    </div>
                                    @if ($showMinimumCharge)
                                        <div class="invoice-breakdown-row">
                                            <div class="invoice-breakdown-label">Beban Minimum</div>
                                            <div class="invoice-breakdown-value">Rp {{ number_format((int) $invoice->minimum_charge_applied, 0, ',', '.') }}</div>
                                        </div>
                                    @endif
                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Beban Tetap</div>
                                        <div class="invoice-breakdown-value">Rp {{ number_format((int) $invoice->admin_fee_total, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="invoice-breakdown-row">
                                        <div class="invoice-breakdown-label">Denda</div>
                                        <div class="invoice-breakdown-value">Rp {{ number_format((int) ($invoice->penalty_total ?? 0), 0, ',', '.') }}</div>
                                    </div>
                                    @if ($showInstallationInstallment)
                                        <div class="invoice-breakdown-row">
                                            <div class="invoice-breakdown-label">
                                                Cicilan Biaya Pasang
                                                @if ($installmentIndex > 0 && $installmentMonths > 0)
                                                    ({{ $installmentIndex }}/{{ $installmentMonths }})
                                                @endif
                                            </div>
                                            <div class="invoice-breakdown-value">Rp {{ number_format((int) $installationLine->line_total, 0, ',', '.') }}</div>
                                        </div>
                                    @endif
                                    <div class="invoice-breakdown-row total">
                                        <div class="invoice-breakdown-label">Total Tagihan</div>
                                        <div class="invoice-breakdown-value">Rp {{ number_format((int) $invoice->invoice_total, 0, ',', '.') }}</div>
                                    </div>

                                    @if ($paymentSummary['paid_total'] > 0 || $paymentSummary['outstanding_total'] >= 0)
                                        <div class="invoice-breakdown-note">
                                            Terbayar Rp {{ number_format((int) $paymentSummary['paid_total'], 0, ',', '.') }}
                                            · Sisa Rp {{ number_format((int) $paymentSummary['outstanding_total'], 0, ',', '.') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="line-list" style="margin-top: 14px;">
                            @foreach ($invoice->lines as $line)
                                <div class="line-item">
                                    <div>
                                        <strong>{{ $line->label }}</strong>
                                        <span>
                                            Qty {{ number_format((float) $line->quantity, 2, ',', '.') }}
                                            @if ($line->unit_price > 0)
                                                · Rp {{ number_format($line->unit_price, 0, ',', '.') }}
                                            @endif
                                        </span>
                                    </div>
                                    <div class="line-total">Rp {{ number_format($line->line_total, 0, ',', '.') }}</div>
                                </div>
                            @endforeach
                        </div>

                        @if (
                            $canManageBilling
                            && $invoice->status === 'issued'
                            && $selectedPeriod?->status !== 'closed'
                            && (int) ($penaltySummary['penalty_amount'] ?? 0) > 0
                            && (int) ($paymentSummary['payments_count'] ?? 0) === 0
                            && (int) ($invoice->penalty_total ?? 0) !== (int) ($penaltySummary['penalty_amount'] ?? 0)
                        )
                            <form method="POST" action="{{ route('tenant.tirta.billing-invoices.penalty.post', $invoice->id) }}" style="margin-top: 14px;">
                                @csrf
                                <button class="tenant-btn-secondary" type="submit">
                                    Posting Denda Hari Ini (Rp {{ number_format((int) $penaltySummary['penalty_amount'], 0, ',', '.') }})
                                </button>
                                <div class="billing-copy" style="margin-top: 8px;">
                                    Tombol ini akan menambahkan line item denda ke invoice dan menaikkan total tagihan. Dibatasi hanya untuk invoice yang belum punya pembayaran.
                                </div>
                            </form>
                        @endif

                        @if ($canRecordPayment && $isDisconnected && $invoice->connection)
                            <details class="record-edit">
                                <summary>Reaktivasi Sambungan</summary>
                                <form method="POST" action="{{ route('tenant.tirta.service-connections.reactivate', $invoice->connection->id) }}" class="billing-inline-form" style="margin-top: 14px;">
                                    @csrf
                                    <label class="checkbox-row">
                                        <input type="checkbox" name="allow_installment" value="1" @checked(old('allow_installment', $reactivationDefaultAllowInstallment)) style="margin-top: 3px;">
                                        <span>
                                            <strong style="display: block;">Boleh cicil tunggakan setelah aktivasi</strong>
                                            <span class="muted">Jika dicentang, setelah invoice aktivasi dibayar, sambungan bisa aktif walau tunggakan lama masih dicicil.</span>
                                        </span>
                                    </label>
                                    <button class="tenant-btn-secondary" type="submit">Buat Invoice Aktivasi</button>
                                </form>
                            </details>
                        @endif

                        <div class="payment-form">
                            <div class="billing-head" style="margin-bottom: 12px;">
                                <div>
                                    <h4 class="billing-title">Riwayat Pembayaran</h4>
                                    <p class="billing-copy">Catatan pembayaran per invoice. Status lunas otomatis ngikut akumulasi pembayaran yang masuk.</p>
                                </div>
                            </div>

                            <div class="payment-list">
                                @forelse ($payments as $payment)
                                    <div class="payment-item">
                                        <div>
                                            <strong>{{ strtoupper((string) $payment->payment_method) }}</strong>
                                            <span>
                                                {{ $payment->paid_at?->format('d M Y H:i') ?? '-' }}
                                                @if ($payment->received_by)
                                                    · diterima {{ $payment->received_by }}
                                                @endif
                                            </span>
                                            @if ($payment->reference_number)
                                                <span>Ref: {{ $payment->reference_number }}</span>
                                            @endif
                                            @if (filled($payment->notes))
                                                <span>{{ $payment->notes }}</span>
                                            @endif
                                        </div>
                                        <div class="payment-total">Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}</div>
                                    </div>
                                @empty
                                    <div class="empty-state" style="padding: 16px 18px;">Belum ada pembayaran tercatat untuk invoice ini.</div>
                                @endforelse
                            </div>

                            @if ($canRecordPayment && $invoice->status !== 'cancelled' && (int) $paymentSummary['outstanding_total'] > 0)
                                <details class="record-edit">
                                    <summary>Catat Pembayaran</summary>
                                    <form method="POST" action="{{ route('tenant.tirta.billing-invoices.payments.store', $invoice->id) }}" class="billing-inline-form" style="margin-top: 14px;">
                                        @csrf

                                        <div class="billing-form-grid two">
                                            <div>
                                                <label class="field-label">Metode</label>
                                                <select name="payment_method">
                                                    @foreach (['cash' => 'Cash', 'transfer' => 'Transfer', 'qris' => 'QRIS', 'loket' => 'Loket', 'adjustment' => 'Adjustment'] as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="field-label">Nominal</label>
                                                <input class="field" type="number" min="1" max="{{ (int) $paymentSummary['outstanding_total'] }}" name="amount" value="{{ old('amount', (int) $paymentSummary['outstanding_total']) }}">
                                            </div>
                                        </div>

                                        <div class="billing-form-grid two">
                                            <div>
                                                <label class="field-label">Waktu Bayar</label>
                                                <input class="field" type="datetime-local" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}">
                                            </div>
                                            <div>
                                                <label class="field-label">Diterima Oleh</label>
                                                <input class="field" type="text" name="received_by" value="{{ old('received_by') }}" placeholder="Opsional">
                                            </div>
                                        </div>

                                        <div class="billing-form-grid two">
                                            <div>
                                                <label class="field-label">Nomor Referensi</label>
                                                <input class="field" type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Opsional">
                                            </div>
                                            <div>
                                                <label class="field-label">Sisa Setelah Bayar</label>
                                                <input class="field" type="text" value="Rp {{ number_format((int) $paymentSummary['outstanding_total'], 0, ',', '.') }}" disabled>
                                            </div>
                                        </div>

                                @if ((int) $penaltySummary['penalty_amount'] > 0)
                                    @if ((int) ($invoice->penalty_total ?? 0) > 0)
                                        <div class="billing-copy">Denda sudah diposting Rp {{ number_format((int) $invoice->penalty_total, 0, ',', '.') }} dan sudah masuk total tagihan.</div>
                                    @else
                                        <div class="billing-copy">Estimasi denda hari ini Rp {{ number_format((int) $penaltySummary['penalty_amount'], 0, ',', '.') }}. Kalau mau denda ikut tertagih, posting dulu supaya masuk ke total invoice.</div>
                                    @endif
                                @endif

                                        <div>
                                            <label class="field-label">Catatan Pembayaran</label>
                                            <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
                                        </div>

                                        <button class="tenant-btn" type="submit">Simpan Pembayaran</button>
                                    </form>
                                </details>
                            @endif
                        </div>

                        @if ($canManageBilling)
                            <details class="record-edit">
                                <summary>Update Status Invoice</summary>
                                <form method="POST" action="{{ route('tenant.tirta.billing-invoices.update', $invoice->id) }}" class="billing-inline-form" style="margin-top: 14px;">
                                    @csrf
                                    @method('PATCH')

                                    <div class="billing-form-grid two">
                                        <div>
                                            <label class="field-label">Status</label>
                                            <select name="status">
                                                @foreach (['issued' => 'Issued', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                                                    <option value="{{ $value }}" @selected($invoice->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="field-label">Catatan</label>
                                            <input class="field" type="text" name="notes" value="{{ $invoice->notes }}" placeholder="Opsional">
                                        </div>
                                    </div>

                                    <div class="billing-copy">Status `paid` normalnya ngikut akumulasi pembayaran. Gunakan form pembayaran di atas supaya histori kas tetap rapi.</div>

                                    <button class="tenant-btn-secondary" type="submit">Simpan Status</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">
                        @if ($selectedPeriod)
                            Tidak ada invoice yang cocok dengan filter aktif. Coba ubah filter status atau bucket piutang.
                        @else
                            Belum ada invoice pada periode ini. Jalankan generate invoice setelah periode billing dipilih dan baca meter sudah tersedia.
                        @endif
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
