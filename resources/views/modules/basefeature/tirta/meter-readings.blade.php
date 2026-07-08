@extends('basefeature::layouts.master')

@section('page_title', 'Catat Meter Tirta')
@section('page_subtitle', 'Kelola siklus baca meter, assignment petugas per area atau global, dan input pembacaan per sambungan')

@push('styles')
    <style>
        .meter-grid,
        .meter-stack,
        .period-list,
        .reading-list,
        .summary-list,
        .status-list,
        .timeline-list,
        .assignment-list {
            display: grid;
            gap: 16px;
        }
        .meter-grid {
            grid-template-columns: minmax(360px, 420px) minmax(0, 1fr);
            align-items: start;
        }
        .meter-panel,
        .meter-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
        }
        .meter-panel {
            padding: 20px;
        }
        .meter-card {
            padding: 18px;
        }
        .meter-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 16px;
        }
        .meter-head h3,
        .meter-title {
            margin: 0;
            font-size: 1rem;
        }
        .meter-head p,
        .meter-copy,
        .mini-note {
            margin: 6px 0 0;
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.65;
        }
        .meter-form,
        .meter-form-grid,
        .meter-inline-form,
        .filter-bar {
            display: grid;
            gap: 14px;
        }
        .meter-form-grid.two,
        .filter-bar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .meter-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .meter-status.normal,
        .meter-status.open,
        .meter-status.active {
            color: #166534;
            background: #dcfce7;
            border-color: #bbf7d0;
        }
        .meter-status.warning,
        .meter-status.draft,
        .meter-status.pending {
            color: #9a3412;
            background: #ffedd5;
            border-color: #fdba74;
        }
        .meter-status.invalid,
        .meter-status.closed,
        .meter-status.inactive {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fecaca;
        }
        .meter-status.review {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #bfdbfe;
        }
        .meter-status.failed {
            color: #7c2d12;
            background: #ffedd5;
            border-color: #fdba74;
        }
        .meter-meta,
        .reading-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .meter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #f8fafc;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .reading-card-warning {
            border-color: #fdba74;
            background: #fff7ed;
        }
        .reading-card-invalid {
            border-color: #fecaca;
            background: #fef2f2;
        }
        .reading-card-review {
            border-color: #bfdbfe;
            background: #eff6ff;
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
        .timeline-card {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
        }
        .timeline-card strong {
            display: block;
            color: #1d4ed8;
            font-size: 0.92rem;
        }
        .timeline-card span {
            display: block;
            margin-top: 4px;
            color: #475569;
            font-size: 0.8rem;
            line-height: 1.6;
        }
        .assignment-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 16px;
            background: #ffffff;
        }
        .evidence-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 0.82rem;
            color: #475569;
        }
        .evidence-thumb {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .evidence-preview a {
            color: var(--primary);
            font-weight: 700;
        }
        .location-status {
            margin-top: 8px;
            font-size: 0.78rem;
            color: var(--muted);
        }
        .checkbox-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 0.85rem;
        }
        @media (max-width: 1023px) {
            .meter-grid,
            .meter-form-grid.two,
            .filter-bar {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $selectedPeriodStatus = (string) data_get($selectedPeriod, 'status', 'pending');
        $cyclePenaltyBasisLabel = ((string) data_get($cycleTimeline, 'penalty_basis', 'due_date')) === 'issued_at'
            ? 'tanggal terbit tagihan'
            : 'jatuh tempo';
        $isGlobalMode = $effectiveAssignmentMode === 'global';
        $assignmentModeLabel = $isGlobalMode ? 'Global Semua Sambungan' : 'Per Area / Wilayah';
        $operatorName = trim((string) (auth('tenant')->user()?->name ?? 'Petugas'));
        $operatorTotal = $assignedConnectionRows->count();
        $operatorCompleted = $assignedConnectionRows->filter(fn (array $row): bool => $row['current_reading'] instanceof \App\Models\Tirta\MeterReading)->count();
        $operatorPending = $operatorTotal - $operatorCompleted;
        $operatorWarnings = $assignedConnectionRows->filter(fn (array $row): bool => (bool) ($row['requires_review'] ?? false))->count();
        $operatorVisible = $connectionRows->count();
        $operatorBucket = $filters['status_bucket'] !== '' ? $filters['status_bucket'] : 'all';
        $operatorTabs = [
            'pending' => ['label' => 'Belum Dibaca', 'count' => $operatorPending],
            'warning' => ['label' => 'Perlu Review', 'count' => $operatorWarnings],
            'recorded' => ['label' => 'Sudah Dibaca', 'count' => $operatorCompleted],
            'all' => ['label' => 'Semua', 'count' => $operatorTotal],
        ];
        $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
        $visitStatusLabels = $visitStatusOptions ?? [];
        $followUpLabels = $followUpActionOptions ?? [];
        $reviewStatusLabels = $reviewStatusOptions ?? [];
        $reviewBucket = $filters['review_bucket'] !== '' ? $filters['review_bucket'] : 'all';
        $reviewTabs = [
            'all' => ['label' => 'Semua Queue', 'count' => $verifierStats['need_review'] + ($readingStats['pending'] ?? 0)],
            'need_review' => ['label' => 'Perlu Review', 'count' => $verifierStats['need_review'] ?? 0],
            'need_verification' => ['label' => 'Verifikasi', 'count' => $verifierStats['need_verification'] ?? 0],
            'revisit_required' => ['label' => 'Kunjungan Ulang', 'count' => $verifierStats['revisit_required'] ?? 0],
            'inspection_required' => ['label' => 'Inspeksi', 'count' => $verifierStats['inspection_required'] ?? 0],
            'customer_contact_required' => ['label' => 'Hubungi Pelanggan', 'count' => $verifierStats['customer_contact_required'] ?? 0],
            'notification_pending' => ['label' => 'Notif Pending', 'count' => $verifierStats['notification_pending'] ?? 0],
            'verified' => ['label' => 'Sudah Beres', 'count' => $verifierStats['verified'] ?? 0],
        ];
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
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
                Data operasional dibatasi ke area kerja <strong>{{ $areaScopeLabel }}</strong> dan turunannya.
            </div>
        @endif

        @if ($isMeterReader)
            <section class="hero-card">
                <div>
                    <span class="hero-badge"><i class="fas fa-gauge-high"></i> Tugas Petugas Meter</span>
                    <h2>Halo {{ $operatorName }}, tinggal selesaikan bacaan meter</h2>
                    <p>Buka aplikasi, lihat sambungan jatah Anda, isi angka meter, lalu lanjut ke sambungan berikutnya. Halaman ini sengaja dibikin sesingkat mungkin biar nggak bikin emosi pas lagi capek di lapangan.</p>
                </div>

                <div class="hero-meta">
                    <div class="hero-meta-card">
                        <span>Periode Tugas</span>
                        <strong>{{ $selectedPeriod?->name ?? 'Belum ada periode aktif' }}</strong>
                    </div>
                    <div class="hero-meta-card">
                        <span>Status</span>
                        <strong>{{ $selectedPeriod ? ucfirst($selectedPeriodStatus) : 'Menunggu jadwal' }}</strong>
                    </div>
                    <div class="hero-meta-card">
                        <span>Sambungan Saya</span>
                        <strong>{{ number_format($operatorTotal, 0, ',', '.') }}</strong>
                    </div>
                    <div class="hero-meta-card">
                        <span>Tampilan</span>
                        <strong>{{ $operatorTabs[$operatorBucket]['label'] ?? 'Semua' }}</strong>
                    </div>
                </div>
            </section>

            <section class="stat-grid">
                <div class="stat-card">
                    <div class="stat-inner">
                        <span class="stat-icon"><i class="fas fa-list-check"></i></span>
                        <div class="stat-copy">
                            <p>Target</p>
                            <strong>{{ number_format($operatorTotal, 0, ',', '.') }}</strong>
                            <span>Total sambungan jatah Anda di periode ini</span>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-inner">
                        <span class="stat-icon"><i class="fas fa-clipboard-check"></i></span>
                        <div class="stat-copy">
                            <p>Selesai</p>
                            <strong>{{ $operatorCompleted }}</strong>
                            <span>Pembacaan sudah berhasil dicatat</span>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-inner">
                        <span class="stat-icon"><i class="fas fa-hourglass-half"></i></span>
                        <div class="stat-copy">
                            <p>Belum Dibaca</p>
                            <strong>{{ $operatorPending }}</strong>
                            <span>Masih menunggu input pembacaan</span>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-inner">
                        <span class="stat-icon"><i class="fas fa-triangle-exclamation"></i></span>
                        <div class="stat-copy">
                            <p>Perlu Review</p>
                            <strong>{{ $operatorWarnings }}</strong>
                            <span>Warning atau invalid, cek angkanya lagi</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="meter-grid">
                <div class="meter-stack">
                    <div class="meter-panel">
                        <div class="meter-head">
                            <div>
                                <h3>Jadwal Tugas Anda</h3>
                                <p>Fokus ke sambungan yang belum dibaca dulu. Kalau ada angka aneh atau rumah tutup, cukup catat di notes lapangan lalu lanjut ke tugas berikutnya.</p>
                            </div>
                        </div>

                        <div class="summary-list">
                            <div class="summary-box">
                                <strong>Periode Aktif</strong>
                                <span>{{ $selectedPeriod?->name ?? 'Belum ada periode aktif yang dibuka admin' }}</span>
                            </div>
                            <div class="summary-box">
                                <strong>Jendela Baca Meter</strong>
                                <span>{{ $cycleTimeline['window_start']->format('d M Y') }} - {{ $cycleTimeline['window_end']->format('d M Y') }}</span>
                            </div>
                            <div class="summary-box">
                                <strong>Target Hari Ini</strong>
                                <span>{{ $operatorCompleted }} selesai, {{ $operatorPending }} belum dibaca, {{ $operatorWarnings }} perlu dicek ulang.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="meter-stack">
                    <div class="meter-panel">
                        <div class="meter-head">
                            <div>
                                <h3>Daftar Sambungan Tugas Anda</h3>
                                <p>Tampilan default langsung `Belum Dibaca`. Tinggal pilih tab kalau mau lihat yang sudah selesai atau yang perlu dicek ulang.</p>
                            </div>
                        </div>

                        <div class="inline-actions" style="margin-bottom: 16px; flex-wrap: wrap;">
                            @foreach ($operatorTabs as $bucket => $tab)
                                <a
                                    href="{{ route('tenant.tirta.meter-readings', array_filter(['period' => $selectedPeriod?->id, 'status_bucket' => $bucket])) }}"
                                    class="{{ $operatorBucket === $bucket ? 'tenant-btn' : 'tenant-btn-secondary' }}"
                                >
                                    {{ $tab['label'] }} ({{ $tab['count'] }})
                                </a>
                            @endforeach
                        </div>

                        <div class="summary-box" style="margin-bottom: 16px;">
                            <strong>Tampilan Saat Ini</strong>
                            <span>{{ $operatorTabs[$operatorBucket]['label'] ?? 'Semua' }} menampilkan {{ number_format($operatorVisible, 0, ',', '.') }} sambungan.</span>
                        </div>

                        @if ($connectionRows->isEmpty())
                            <div class="empty-state">
                                @if ($operatorTotal === 0)
                                    Belum ada sambungan yang di-assign ke akun Anda. Hubungi admin untuk set assignment petugas atau petugas global.
                                @else
                                    Tidak ada sambungan pada tab {{ strtolower($operatorTabs[$operatorBucket]['label'] ?? 'ini') }}.
                                @endif
                            </div>
                        @else
                            <div class="reading-list">
                                @foreach ($connectionRows as $row)
                                    @php
                                        $connection = $row['connection'];
                                        $currentReading = $row['current_reading'];
                                        $previousReading = $row['previous_reading'];
                                        $readingStatus = (string) $row['reading_status'];
                                        $visitStatus = (string) ($row['visit_status'] ?? 'pending');
                                        $reviewStatus = (string) ($row['review_status'] ?? 'pending');
                                        $requiresReview = (bool) ($row['requires_review'] ?? false);
                                        $readingCardClass = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                            ? 'reading-card-invalid'
                                            : ($requiresReview ? 'reading-card-review' : ($readingStatus === 'invalid' ? 'reading-card-invalid' : ($readingStatus === 'warning' ? 'reading-card-warning' : '')));
                                        $statusClass = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                            ? 'failed'
                                            : ($requiresReview ? 'review' : (in_array($readingStatus, ['normal', 'open'], true) ? 'normal' : (in_array($readingStatus, ['warning', 'draft', 'pending'], true) ? 'warning' : 'invalid')));
                                        $statusLabel = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                            ? ($visitStatusLabels[$visitStatus] ?? ucfirst(str_replace('_', ' ', $visitStatus)))
                                            : ($requiresReview ? 'Perlu Review' : ucfirst($readingStatus));
                                    @endphp
                                    <div class="meter-card {{ $readingCardClass }}">
                                        <div class="meter-head">
                                            <div>
                                                <h4 class="meter-title">{{ $connection->service_number }} - {{ $connection->customer?->name ?? 'Tanpa pelanggan' }}</h4>
                                                <p class="meter-copy">{{ $row['service_area_label'] }} @if ($connection->service_address) · {{ $connection->service_address }} @endif</p>
                                            </div>
                                            <span class="meter-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                        </div>

                                        <div class="reading-meta">
                                            <span class="meter-pill">Baseline: {{ number_format((int) $row['baseline_reading'], 0, ',', '.') }}</span>
                                            <span class="meter-pill">Sekarang: {{ number_format((int) ($currentReading?->current_reading ?? 0), 0, ',', '.') }}</span>
                                            <span class="meter-pill">Pemakaian: {{ number_format((int) ($row['usage_volume'] ?? 0), 0, ',', '.') }} m3</span>
                                            @if ($currentReading)
                                                <span class="meter-pill">Kunjungan: {{ $visitStatusLabels[$visitStatus] ?? ucfirst(str_replace('_', ' ', $visitStatus)) }}</span>
                                                <span class="meter-pill">Review: {{ $reviewStatusLabels[$reviewStatus] ?? ucfirst(str_replace('_', ' ', $reviewStatus)) }}</span>
                                                @if (filled($row['follow_up_action']))
                                                    <span class="meter-pill">Tindak lanjut: {{ $followUpLabels[$row['follow_up_action']] ?? ucfirst(str_replace('_', ' ', $row['follow_up_action'])) }}</span>
                                                @endif
                                            @endif
                                        </div>

                                        <div class="meter-copy">
                                            @if ($currentReading)
                                                Terakhir dicatat {{ $currentReading->recorded_at?->format('d M Y H:i') ?? '-' }}
                                                @if ($currentReading->reader_name)
                                                    oleh {{ $currentReading->reader_name }}
                                                @endif
                                            @else
                                                Belum ada pembacaan untuk sambungan ini di periode aktif.
                                            @endif

                                            @if ($previousReading?->period)
                                                <br>Periode sebelumnya: {{ $previousReading->period->name }}
                                            @endif

                                            @if (filled($currentReading?->anomaly_notes))
                                                <br>{{ $currentReading->anomaly_notes }}
                                            @endif

                                            @if ($currentReading && ! empty($currentReading->review_flags))
                                                <br>Flag review: {{ implode(' | ', $currentReading->review_flags) }}
                                            @endif

                                            @if ($currentReading && $currentReading->customer_notification_status !== 'not_applicable')
                                                <br>Notifikasi pelanggan: {{ ucfirst(str_replace('_', ' ', $currentReading->customer_notification_status)) }}
                                                @if (! empty($currentReading->customer_notification_channels))
                                                    via {{ implode(', ', $currentReading->customer_notification_channels) }}
                                                @endif
                                            @endif
                                        </div>

                                        @if ($currentReading && ($currentReading->evidence_photo_path || ($currentReading->recorded_latitude && $currentReading->recorded_longitude)))
                                            <div class="evidence-preview">
                                                <i class="fas fa-camera"></i>
                                                <div>
                                                    @if ($currentReading->evidence_photo_path)
                                                        <div>
                                                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($currentReading->evidence_photo_path) }}" target="_blank" rel="noopener">
                                                                Lihat foto meter
                                                            </a>
                                                        </div>
                                                    @endif
                                                    @if ($currentReading->recorded_latitude && $currentReading->recorded_longitude)
                                                        <div>
                                                            Titik:
                                                            <a href="https://www.google.com/maps?q={{ $currentReading->recorded_latitude }},{{ $currentReading->recorded_longitude }}" target="_blank" rel="noopener">
                                                                {{ $currentReading->recorded_latitude }}, {{ $currentReading->recorded_longitude }}
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif

                                        @if ($selectedPeriod)
                                            @if (! $currentReading || $requiresReview)
                                                <form method="POST" action="{{ $currentReading ? route('tenant.tirta.meter-readings.update', $currentReading->id) : route('tenant.tirta.meter-readings.store') }}" class="meter-inline-form" style="margin-top: 14px;" enctype="multipart/form-data" data-meter-evidence-form>
                                                    @csrf
                                                    @if ($currentReading)
                                                        @method('PATCH')
                                                    @else
                                                        <input type="hidden" name="meter_reading_period_id" value="{{ $selectedPeriod->id }}">
                                                        <input type="hidden" name="service_connection_id" value="{{ $connection->id }}">
                                                    @endif
                                                    <input type="hidden" name="recorded_latitude" value="{{ old('service_connection_id') === $connection->id ? old('recorded_latitude') : $currentReading?->recorded_latitude }}" data-meter-latitude>
                                                    <input type="hidden" name="recorded_longitude" value="{{ old('service_connection_id') === $connection->id ? old('recorded_longitude') : $currentReading?->recorded_longitude }}" data-meter-longitude>
                                                    <input type="hidden" name="recorded_accuracy_meters" value="{{ old('service_connection_id') === $connection->id ? old('recorded_accuracy_meters') : $currentReading?->recorded_accuracy_meters }}" data-meter-accuracy>

                                                    <div class="meter-form-grid two">
                                                        <div>
                                                            <label class="field-label">Status Kunjungan</label>
                                                            <select name="visit_status">
                                                                @foreach ($visitStatusOptions as $visitValue => $visitLabel)
                                                                    <option value="{{ $visitValue }}" @selected((old('service_connection_id') === $connection->id ? old('visit_status', $currentReading?->visit_status ?? 'read') : ($currentReading?->visit_status ?? 'read')) === $visitValue)>
                                                                        {{ $visitLabel }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            <div class="mini-note">Pilih `Berhasil Dibaca` kalau angka meter valid. Status lain otomatis masuk antrean review.</div>
                                                        </div>
                                                        <div>
                                                            <label class="field-label">Angka Meter Sekarang</label>
                                                            <input class="field" type="number" min="0" name="current_reading" value="{{ old('service_connection_id') === $connection->id ? old('current_reading') : $currentReading?->current_reading }}" placeholder="Masukkan angka meter">
                                                            <div class="mini-note">Boleh dikosongkan kalau kunjungan gagal baca.</div>
                                                        </div>
                                                    </div>
                                                    <div class="meter-form-grid two">
                                                        <div>
                                                            <label class="field-label">Nama Petugas</label>
                                                            <input class="field" type="text" name="reader_name" value="{{ old('service_connection_id') === $connection->id ? old('reader_name', $operatorName) : ($currentReading?->reader_name ?? $operatorName) }}">
                                                        </div>
                                                        <div></div>
                                                    </div>

                                                    <div>
                                                        <label class="field-label">Waktu Catat</label>
                                                        <input class="field" type="datetime-local" name="recorded_at" value="{{ old('service_connection_id') === $connection->id ? old('recorded_at', now()->format('Y-m-d\TH:i')) : ($currentReading?->recorded_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}">
                                                    </div>

                                                    <div class="meter-form-grid two">
                                                        <div>
                                                            <label class="field-label">Foto Evidence Meter</label>
                                                            <input class="field" type="file" name="evidence_photo" accept="image/*" capture="environment" data-evidence-input @required($isMeterReader && !($currentReading?->evidence_photo_path))>
                                                            <div class="mini-note">{{ $isMeterReader ? 'Wajib. ' : '' }}Ambil foto meter atau kondisi lapangan. Maks 5MB.</div>
                                                            <div class="evidence-preview" data-evidence-preview style="display: none;">
                                                                <img class="evidence-thumb" alt="Preview foto meter" data-evidence-thumb>
                                                                <div>
                                                                    <div><strong>Preview</strong></div>
                                                                    <div class="mini-note" style="margin-top: 2px;" data-evidence-name></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label class="field-label">Koordinat Lokasi</label>
                                                            <button class="tenant-btn-secondary" type="button" data-capture-location>Ambil Lokasi Saya</button>
                                                            <div class="location-status" data-location-status>
                                                                @if (($currentReading?->recorded_latitude) && ($currentReading?->recorded_longitude))
                                                                    Koordinat tersimpan: {{ $currentReading->recorded_latitude }}, {{ $currentReading->recorded_longitude }}
                                                                @else
                                                                    Lokasi belum diambil.
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="field-label">Catatan Lapangan</label>
                                                        <textarea name="notes" rows="3">{{ old('service_connection_id') === $connection->id ? old('notes') : $currentReading?->notes }}</textarea>
                                                        <div class="mini-note">Wajib diisi untuk rumah kosong, pagar dikunci, akses ditolak, meter rusak, atau kendala lain.</div>
                                                    </div>

                                                    <button class="{{ $currentReading ? 'tenant-btn-secondary' : 'tenant-btn' }}" type="submit">{{ $currentReading ? 'Update Pembacaan' : 'Simpan Pembacaan' }}</button>
                                                </form>
                                            @else
                                                <details class="record-edit">
                                                    <summary>Perbarui Pembacaan</summary>
                                                    <form method="POST" action="{{ route('tenant.tirta.meter-readings.update', $currentReading->id) }}" class="meter-inline-form" style="margin-top: 14px;" enctype="multipart/form-data" data-meter-evidence-form>
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="recorded_latitude" value="{{ $currentReading->recorded_latitude }}" data-meter-latitude>
                                                        <input type="hidden" name="recorded_longitude" value="{{ $currentReading->recorded_longitude }}" data-meter-longitude>
                                                        <input type="hidden" name="recorded_accuracy_meters" value="{{ $currentReading->recorded_accuracy_meters }}" data-meter-accuracy>

                                                        <div class="meter-form-grid two">
                                                            <div>
                                                                <label class="field-label">Status Kunjungan</label>
                                                                <select name="visit_status">
                                                                    @foreach ($visitStatusOptions as $visitValue => $visitLabel)
                                                                        <option value="{{ $visitValue }}" @selected(($currentReading?->visit_status ?? 'read') === $visitValue)>
                                                                            {{ $visitLabel }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="field-label">Angka Meter Sekarang</label>
                                                                <input class="field" type="number" min="0" name="current_reading" value="{{ $currentReading->current_reading }}">
                                                                <div class="mini-note">Kosongkan kalau kunjungan ulang tetap gagal baca.</div>
                                                            </div>
                                                        </div>
                                                        <div class="meter-form-grid two">
                                                            <div>
                                                                <label class="field-label">Nama Petugas</label>
                                                                <input class="field" type="text" name="reader_name" value="{{ $currentReading->reader_name ?? $operatorName }}">
                                                            </div>
                                                            <div></div>
                                                        </div>

                                                        <div>
                                                            <label class="field-label">Waktu Catat</label>
                                                            <input class="field" type="datetime-local" name="recorded_at" value="{{ $currentReading->recorded_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i') }}">
                                                        </div>

                                                        <div class="meter-form-grid two">
                                                            <div>
                                                                <label class="field-label">Foto Evidence Meter</label>
                                                                <input class="field" type="file" name="evidence_photo" accept="image/*" capture="environment" data-evidence-input @required($isMeterReader && !($currentReading?->evidence_photo_path))>
                                                                <div class="mini-note">
                                                                    @if ($currentReading->evidence_photo_path)
                                                                        Foto lama tetap dipakai kalau tidak upload ulang.
                                                                    @else
                                                                        {{ $isMeterReader ? 'Wajib upload foto evidence.' : 'Belum ada foto evidence.' }}
                                                                    @endif
                                                                </div>
                                                                <div class="evidence-preview" data-evidence-preview style="display: none;">
                                                                    <img class="evidence-thumb" alt="Preview foto meter" data-evidence-thumb>
                                                                    <div>
                                                                        <div><strong>Preview</strong></div>
                                                                        <div class="mini-note" style="margin-top: 2px;" data-evidence-name></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="field-label">Koordinat Lokasi</label>
                                                                <button class="tenant-btn-secondary" type="button" data-capture-location>Ambil Lokasi Saya</button>
                                                                <div class="location-status" data-location-status>
                                                                    @if ($currentReading->recorded_latitude && $currentReading->recorded_longitude)
                                                                        Koordinat tersimpan: {{ $currentReading->recorded_latitude }}, {{ $currentReading->recorded_longitude }}
                                                                    @else
                                                                        Lokasi belum diambil.
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <label class="field-label">Catatan Lapangan</label>
                                                            <textarea name="notes" rows="3">{{ $currentReading->notes }}</textarea>
                                                            <div class="mini-note">Jelaskan evidence lapangan, alasan gagal baca, atau kenapa perlu inspeksi lanjutan.</div>
                                                        </div>

                                                        <button class="tenant-btn-secondary" type="submit">Update Pembacaan</button>
                                                    </form>
                                                </details>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @else

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-gauge-high"></i> TirtaCatatMeter</span>
                <h2>Skema Baca Meter Sampai Billing</h2>
                <p>Halaman ini sekarang jadi panel operasional untuk atur jadwal baca meter, tentukan petugas per area atau global, lalu teruskan alurnya ke terbit tagihan, jatuh tempo, dan awal denda.</p>
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
                    <span>Input Tercatat</span>
                    <strong>{{ $readingStats['recorded'] }} / {{ $readingStats['filtered_connections'] }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Mode Petugas</span>
                    <strong>{{ $assignmentModeLabel }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-calendar-alt"></i></span>
                    <div class="stat-copy">
                        <p>Periode</p>
                        <strong>{{ $readingStats['periods'] }}</strong>
                        <span>Periode baca tersimpan</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-faucet-drip"></i></span>
                    <div class="stat-copy">
                        <p>Sambungan</p>
                        <strong>{{ $readingStats['connections'] }}</strong>
                        <span>{{ $readingStats['filtered_connections'] }} tampil di queue</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-clipboard-check"></i></span>
                    <div class="stat-copy">
                        <p>Tercatat</p>
                        <strong>{{ $readingStats['recorded'] }}</strong>
                        <span>{{ $readingStats['pending'] }} masih pending</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-triangle-exclamation"></i></span>
                    <div class="stat-copy">
                        <p>Warning</p>
                        <strong>{{ $readingStats['warnings'] }}</strong>
                        <span>Butuh review operator</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-map-location-dot"></i></span>
                    <div class="stat-copy">
                        <p>Area Terpasang</p>
                        <strong>{{ $assignmentStats['assigned_areas'] }}</strong>
                        <span>{{ $assignmentStats['unassigned_areas'] }} belum punya petugas area</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-users"></i></span>
                    <div class="stat-copy">
                        <p>Petugas Aktif</p>
                        <strong>{{ $assignmentStats['active_readers'] }}</strong>
                        <span>{{ $assignmentStats['global_assignment_active'] ? 'Petugas global tanpa area aktif' : 'User aktif yang memang boleh masuk workflow catat meter' }}</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-lock"></i></span>
                    <div class="stat-copy">
                        <p>Workflow Lock</p>
                        <strong>{{ $workflowStats['billing_locked_periods'] }}</strong>
                        <span>{{ $workflowStats['draft_billing_periods'] }} periode sudah punya draft billing</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="meter-grid">
            <div class="meter-panel">
                <div class="meter-head">
                    <div>
                        <h3>Workspace Verifikator</h3>
                        <p>Panel ini fokus ke antrian pembacaan yang butuh keputusan operasional. Tinggal pilih bucket review lalu tindak lanjuti dari kartu sambungan di bawah.</p>
                    </div>
                </div>

                <div class="inline-actions" style="margin-bottom: 16px; flex-wrap: wrap;">
                    @foreach ($reviewTabs as $bucket => $tab)
                        <a
                            href="{{ route('tenant.tirta.meter-readings', array_filter(['period' => $selectedPeriod?->id, 'review_bucket' => $bucket !== 'all' ? $bucket : null, 'service_area_id' => $filters['service_area_id'] !== '' ? $filters['service_area_id'] : null, 'user_id' => $filters['user_id'] !== '' ? $filters['user_id'] : null])) }}"
                            class="{{ $reviewBucket === $bucket ? 'tenant-btn' : 'tenant-btn-secondary' }}"
                        >
                            {{ $tab['label'] }} ({{ $tab['count'] }})
                        </a>
                    @endforeach
                </div>

                <div class="summary-list">
                    <div class="summary-box">
                        <strong>{{ $verifierStats['need_review'] ?? 0 }} perlu review</strong>
                        <span>Mencakup bacaan anomali, gagal baca, dan kasus yang belum punya keputusan verifikator.</span>
                    </div>
                    <div class="summary-box">
                        <strong>{{ $verifierStats['notification_pending'] ?? 0 }} notif pending</strong>
                        <span>Email pelanggan belum terkirim atau gagal kirim dan bisa diproses ulang dari queue.</span>
                    </div>
                    <div class="summary-box">
                        <strong>{{ $verifierStats['verified'] ?? 0 }} sudah clear</strong>
                        <span>Kasus yang sudah disetujui verifikator atau dinyatakan selesai ditindaklanjuti.</span>
                    </div>
                </div>
            </div>

            <aside class="meter-stack">
                <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Distribusi Tindak Lanjut</h3>
                            <p>Snapshot cepat buat ngelihat bucket kerja verifikator periode aktif.</p>
                        </div>
                    </div>

                    <div class="summary-list">
                        <div class="summary-box">
                            <strong>{{ $verifierStats['need_verification'] ?? 0 }} tunggu verifikasi</strong>
                            <span>Bacaan perlu dicek ulang tapi belum diputuskan.</span>
                        </div>
                        <div class="summary-box">
                            <strong>{{ $verifierStats['revisit_required'] ?? 0 }} kunjungan ulang</strong>
                            <span>Petugas harus balik lagi ke lapangan.</span>
                        </div>
                        <div class="summary-box">
                            <strong>{{ $verifierStats['inspection_required'] ?? 0 }} inspeksi teknis</strong>
                            <span>Indikasi meter bermasalah atau perlu pemeriksaan lebih dalam.</span>
                        </div>
                        <div class="summary-box">
                            <strong>{{ $verifierStats['customer_contact_required'] ?? 0 }} hubungi pelanggan</strong>
                            <span>Kasus akses meter, pagar dikunci, atau perlu konfirmasi pelanggan.</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="meter-grid">
            <div class="meter-stack">
                @if (! $isMeterReader)
                    <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Pengaturan Siklus Operasional</h3>
                            <p>Atur jendela baca meter, jeda terbit tagihan, jatuh tempo default, titik awal munculnya denda, dan petugas global kalau tenant tidak pakai area.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('tenant.tirta.meter-settings.cycle') }}" class="meter-form">
                        @csrf
                        <input type="hidden" name="period" value="{{ $selectedPeriod?->id }}">
                        <input type="hidden" name="service_area_id_filter" value="{{ $filters['service_area_id'] }}">
                        <input type="hidden" name="user_id_filter" value="{{ $filters['user_id'] }}">

                        <div class="meter-form-grid two">
                            <div>
                                <label class="field-label">Mulai Baca Meter</label>
                                <input class="field" type="number" min="1" max="31" name="meter_reading_window_start_day" value="{{ old('meter_reading_window_start_day', $cycleSettings['meter_reading_window_start_day']) }}">
                            </div>
                            <div>
                                <label class="field-label">Akhir Baca Meter</label>
                                <input class="field" type="number" min="1" max="31" name="meter_reading_window_end_day" value="{{ old('meter_reading_window_end_day', $cycleSettings['meter_reading_window_end_day']) }}">
                            </div>
                        </div>

                        <div class="meter-form-grid two">
                            <div>
                                <label class="field-label">Terbit Tagihan +H</label>
                                <input class="field" type="number" min="0" max="60" name="billing_publish_offset_days" value="{{ old('billing_publish_offset_days', $cycleSettings['billing_publish_offset_days']) }}">
                            </div>
                            <div>
                                <label class="field-label">Jatuh Tempo +H</label>
                                <input class="field" type="number" min="1" max="90" name="billing_due_offset_days" value="{{ old('billing_due_offset_days', $cycleSettings['billing_due_offset_days']) }}">
                            </div>
                        </div>

                        <div class="meter-form-grid two">
                            <div>
                                <label class="field-label">Mode Distribusi Baca Meter</label>
                                <select name="meter_assignment_mode">
                                    @foreach (['global' => 'Global Semua Sambungan', 'per_area' => 'Per Area / Wilayah'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('meter_assignment_mode', $meterAssignmentMode) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="mini-note">
                                    @if ($usesServiceAreas)
                                        Mode `Per Area / Wilayah` pakai assignment area. Mode `Global` mengabaikan pembagian area untuk operasional baca meter.
                                    @else
                                        Tenant ini belum punya area aktif, jadi mode efektif sekarang tetap `Global Semua Sambungan`.
                                    @endif
                                </div>
                            </div>
                            <div>
                                <label class="field-label">Awal Denda Dari</label>
                                <select name="billing_penalty_start_basis">
                                    @foreach (['due_date' => 'Jatuh Tempo', 'issued_at' => 'Tanggal Terbit Tagihan'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billing_penalty_start_basis', $cycleSettings['billing_penalty_start_basis']) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Grace Denda</label>
                                <input class="field" type="number" min="0" max="365" name="billing_penalty_grace_days" value="{{ old('billing_penalty_grace_days', $cycleSettings['billing_penalty_grace_days']) }}">
                            </div>
                        </div>

                        <div>
                            <label class="field-label">Petugas Global Tanpa Area</label>
                            <select name="default_meter_reader_user_id">
                                <option value="">Belum diatur</option>
                                @foreach ($meterReaders as $reader)
                                    <option value="{{ $reader->id }}" @selected(old('default_meter_reader_user_id', $defaultMeterReader?->id) === $reader->id)>
                                        {{ $reader->name }} · {{ ucfirst((string) ($reader->roleSlug() ?? 'staff')) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mini-note">Dipakai untuk semua sambungan tanpa area. Kalau mode `Global` aktif, petugas ini dianggap pegang semua sambungan tenant.</div>
                        </div>

                        <div class="mini-note">Contoh: kalau baca meter selesai tanggal 30, `Terbit Tagihan +1` berarti invoice disarankan terbit tanggal 31 atau 1 bulan berikutnya sesuai kalender. `Jatuh Tempo +10` berarti due date 10 hari setelah tanggal terbit.</div>

                        <button class="tenant-btn" type="submit">Simpan Siklus</button>
                    </form>
                    </div>

                    <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Buka Periode Baca Meter</h3>
                            <p>Buat periode kerja bulanan dulu. Jadwal di samping jadi acuan kapan petugas mulai membaca dan kapan hasilnya lanjut ke billing.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('tenant.tirta.meter-reading-periods.store') }}" class="meter-form">
                        @csrf

                        <div>
                            <label class="field-label" for="period-name">Nama Periode</label>
                            <input id="period-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Baca Meter Juli 2026">
                        </div>

                        <div class="meter-form-grid two">
                            <div>
                                <label class="field-label" for="period-start">Mulai Periode</label>
                                <input id="period-start" class="field" type="date" name="period_start" value="{{ old('period_start') }}">
                            </div>
                            <div>
                                <label class="field-label" for="period-end">Akhir Periode</label>
                                <input id="period-end" class="field" type="date" name="period_end" value="{{ old('period_end') }}">
                            </div>
                        </div>

                        <div>
                            <label class="field-label" for="period-status">Status</label>
                            <select id="period-status" name="status">
                                @foreach (['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="field-label" for="period-notes">Catatan</label>
                            <textarea id="period-notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                        </div>

                        <button class="tenant-btn" type="submit">Simpan Periode</button>
                    </form>
                    </div>
                @endif
            </div>

            <aside class="meter-stack">
                <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Preview Skema Baca → Tagih → Bayar</h3>
                            <p>{{ $cycleTimeline['source'] }}: {{ $cycleTimeline['reference'] }}</p>
                        </div>
                    </div>

                    <div class="timeline-list">
                        <div class="timeline-card">
                            <strong>1. Baca Meter</strong>
                            <span>{{ $cycleTimeline['window_start']->format('d M Y') }} - {{ $cycleTimeline['window_end']->format('d M Y') }}</span>
                        </div>
                        <div class="timeline-card">
                            <strong>2. Terbit Tagihan</strong>
                            <span>{{ $cycleTimeline['publish_date']->format('d M Y') }} berdasarkan offset {{ $cycleSettings['billing_publish_offset_days'] }} hari setelah akhir baca meter.</span>
                        </div>
                        <div class="timeline-card">
                            <strong>3. Batas Pembayaran</strong>
                            <span>{{ $cycleTimeline['due_date']->format('d M Y') }} berdasarkan offset {{ $cycleSettings['billing_due_offset_days'] }} hari dari tanggal terbit.</span>
                        </div>
                        <div class="timeline-card">
                            <strong>4. Denda Mulai Jalan</strong>
                            <span>{{ $cycleTimeline['penalty_start_date']->format('d M Y') }} karena basisnya {{ $cyclePenaltyBasisLabel }} dengan grace {{ $cycleTimeline['penalty_grace_days'] }} hari.</span>
                        </div>
                    </div>
                </div>

                <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Ringkasan Periode Aktif</h3>
                            <p>Snapshot cepat untuk cek progres baca meter sebelum lanjut generate billing.</p>
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
                                <span>Periode closed akan mengunci update pembacaan sampai status dibuka lagi.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $readingStats['recorded'] }} sambungan sudah terbaca</strong>
                                <span>{{ $readingStats['pending'] }} sambungan masih pending sesuai filter aktif.</span>
                            </div>
                            <div class="summary-box">
                                <strong>{{ $readingStats['warnings'] }} butuh review</strong>
                                <span>Warning muncul saat pemakaian melonjak tajam atau angka meter tidak valid.</span>
                            </div>
                        </div>
                    @else
                        <div class="empty-state">Pilih atau buat periode dulu supaya panel input pembacaan meter bisa aktif.</div>
                    @endif
                </div>

                <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Aturan Validasi</h3>
                            <p>Guard awal untuk bantu operator menandai bacaan yang patut dicek ulang.</p>
                        </div>
                    </div>

                    <div class="status-list">
                        <div class="summary-box">
                            <strong>Status Normal</strong>
                            <span>Angka meter naik wajar dan tidak melewati ambang review.</span>
                        </div>
                        <div class="summary-box">
                            <strong>Status Warning</strong>
                            <span>Muncul kalau pemakaian lebih dari dua kali periode sebelumnya atau >= 100 m3.</span>
                        </div>
                        <div class="summary-box">
                            <strong>Status Invalid</strong>
                            <span>Angka meter sekarang lebih kecil dari baseline periode sebelumnya.</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="meter-grid">
            <div class="meter-stack">
                @if (! $isMeterReader)
                    @if (! $isGlobalMode)
                        <div class="meter-panel">
                        <div class="meter-head">
                            <div>
                                <h3>Assignment Petugas Baca Meter</h3>
                                <p>Kalau petugas banyak, area bisa dibagi per cabang, unit, atau rayon. Sambungan tanpa area tetap bisa jatuh ke petugas global.</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('tenant.tirta.meter-reader-assignments.store') }}" class="meter-form">
                            @csrf
                            <input type="hidden" name="period" value="{{ $selectedPeriod?->id }}">
                            <input type="hidden" name="service_area_id_filter" value="{{ $filters['service_area_id'] }}">
                            <input type="hidden" name="user_id_filter" value="{{ $filters['user_id'] }}">

                            <div class="meter-form-grid two">
                                <div>
                                    <label class="field-label">Area / Wilayah</label>
                                    <select name="service_area_id">
                                        <option value="">Pilih area</option>
                                        @foreach ($serviceAreas as $area)
                                            <option value="{{ $area->id }}" @selected(old('service_area_id') === $area->id)>
                                                {{ $serviceAreaOptions->get($area->id, $area->name) }} · {{ $area->areaTypeLabel() }} · {{ $area->connections_count }} sambungan
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mini-note">Assignment di area induk otomatis berlaku ke turunan selama area turunannya belum punya assignment aktif sendiri.</p>
                                </div>
                                <div>
                                    <label class="field-label">Petugas</label>
                                    <select name="user_id">
                                        <option value="">Pilih petugas</option>
                                        @foreach ($meterReaders as $reader)
                                            <option value="{{ $reader->id }}" @selected(old('user_id') === $reader->id)>
                                                {{ $reader->name }} · {{ ucfirst((string) ($reader->roleSlug() ?? 'staff')) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <label class="checkbox-row">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) style="margin-top: 3px;">
                                <span>
                                    <strong style="display: block;">Assignment aktif</strong>
                                    <span class="mini-note" style="margin-top: 2px;">Kalau dimatikan, area tetap tersimpan tapi tidak dianggap punya petugas aktif.</span>
                                </span>
                            </label>

                            <div>
                                <label class="field-label">Catatan</label>
                                <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
                            </div>

                            <button class="tenant-btn" type="submit">Simpan Assignment</button>
                        </form>
                        </div>
                    @else
                        <div class="meter-panel">
                        <div class="meter-head">
                            <div>
                                <h3>Mode Global Aktif</h3>
                                <p>Pembagian petugas per area disembunyikan. Semua sambungan operasional dibaca sebagai satu pool dan mengikuti petugas global kalau sudah dipilih.</p>
                            </div>
                        </div>

                        <div class="summary-list">
                            <div class="summary-box">
                                <strong>Mode {{ $assignmentModeLabel }}</strong>
                                <span>Dipakai untuk tenant kecil atau tenant yang belum mau ribet dengan pembagian area.</span>
                            </div>
                            <div class="summary-box">
                                <strong>Petugas Global</strong>
                                <span>{{ $defaultMeterReader?->name ?? 'Belum diatur' }}</span>
                            </div>
                            <div class="summary-box">
                                <strong>Total Sambungan</strong>
                                <span>{{ number_format($readingStats['connections'], 0, ',', '.') }} sambungan ikut satu queue kerja.</span>
                            </div>
                        </div>
                        </div>
                    @endif
                @endif

                <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Daftar Periode</h3>
                            <p>Pilih periode yang mau dikerjakan atau tutup periode kalau semua sambungan sudah selesai dibaca.</p>
                        </div>
                    </div>

                    <div class="period-list">
                        @forelse ($periods as $period)
                            <div class="meter-card">
                                <div class="meter-head">
                                    <div>
                                        <h4 class="meter-title">{{ $period->name }}</h4>
                                        <p class="meter-copy">{{ $period->period_start?->format('d M Y') }} - {{ $period->period_end?->format('d M Y') }}</p>
                                    </div>
                                    <span class="meter-status {{ $period->status }}">{{ ucfirst($period->status) }}</span>
                                </div>

                                <div class="meter-meta">
                                    <span class="meter-pill">{{ $period->readings_count }} pembacaan</span>
                                    @if ($period->billingPeriod)
                                        <span class="meter-pill">Billing {{ ucfirst($period->billingPeriod->status) }}</span>
                                    @endif
                                    @if ($selectedPeriod?->id === $period->id)
                                        <span class="meter-pill">Periode aktif</span>
                                    @endif
                                </div>

                                @if (filled($period->notes))
                                    <div class="meter-copy">{{ $period->notes }}</div>
                                @endif

                                <div class="inline-actions" style="margin-top: 14px;">
                                    <a class="period-link" href="{{ route('tenant.tirta.meter-readings', ['period' => $period->id]) }}">
                                        <i class="fas fa-arrow-right"></i> Buka Periode
                                    </a>
                                </div>

                                @if (in_array((string) ($period->billingPeriod?->status ?? ''), ['generated', 'closed'], true))
                                    <div class="summary-box" style="margin-top: 14px;">
                                        <strong>Periode Terkunci</strong>
                                        <span>Periode ini sudah masuk workflow billing {{ $period->billingPeriod?->status }} sehingga tanggal dan status meter tidak bisa diubah lagi.</span>
                                    </div>
                                @elseif (! $isMeterReader)
                                    <details class="record-edit">
                                        <summary>Edit Periode</summary>
                                        <form method="POST" action="{{ route('tenant.tirta.meter-reading-periods.update', $period->id) }}" class="meter-inline-form" style="margin-top: 14px;">
                                            @csrf
                                            @method('PATCH')
                                            <div>
                                                <label class="field-label">Nama</label>
                                                <input class="field" type="text" name="name" value="{{ $period->name }}">
                                            </div>
                                            <div class="meter-form-grid two">
                                                <div>
                                                    <label class="field-label">Mulai</label>
                                                    <input class="field" type="date" name="period_start" value="{{ $period->period_start?->format('Y-m-d') }}">
                                                </div>
                                                <div>
                                                    <label class="field-label">Selesai</label>
                                                    <input class="field" type="date" name="period_end" value="{{ $period->period_end?->format('Y-m-d') }}">
                                                </div>
                                            </div>
                                            <div>
                                                <label class="field-label">Status</label>
                                                <select name="status">
                                                    @foreach (['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed'] as $value => $label)
                                                        <option value="{{ $value }}" @selected($period->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="field-label">Catatan</label>
                                                <textarea name="notes" rows="3">{{ $period->notes }}</textarea>
                                            </div>
                                            <button class="tenant-btn-secondary" type="submit">Update Periode</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <div class="empty-state">Belum ada periode baca meter. Mulai dari buat periode kerja dulu supaya petugas punya target input yang jelas.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <aside class="meter-stack">
                @if (! $isMeterReader && ! $isGlobalMode)
                    <div class="meter-panel">
                        <div class="meter-head">
                            <div>
                                <h3>Assignment Aktif per Area</h3>
                                <p>Supervisor bisa review area mana yang sudah punya petugas, mana yang masih kosong, dan area induk mana yang sedang menanggung turunannya.</p>
                            </div>
                        </div>

                        @if ($assignmentStats['connections_without_area'] > 0)
                            <div class="summary-box" style="margin-bottom: 16px;">
                                <strong>{{ $usesServiceAreas ? 'Tanpa Area' : 'Semua Sambungan' }}</strong>
                                <span>
                                    {{ $assignmentStats['connections_without_area'] }} sambungan tidak terikat area.
                                    @if ($defaultMeterReader)
                                        Petugas global saat ini: {{ $defaultMeterReader->name }}.
                                    @else
                                        Belum ada petugas global.
                                    @endif
                                </span>
                            </div>
                        @endif

                        <div class="assignment-list">
                            @forelse ($readerAssignments as $assignment)
                                <div class="assignment-card">
                                    <div class="meter-head" style="margin-bottom: 10px;">
                                        <div>
                                            <h4 class="meter-title">{{ $assignment->serviceArea ? $serviceAreaOptions->get($assignment->serviceArea->id, $assignment->serviceArea->name) : 'Area terhapus' }}</h4>
                                            <p class="meter-copy">{{ $assignment->user?->name ?? 'Petugas terhapus' }} · {{ ucfirst((string) ($assignment->user?->roleSlug() ?? 'staff')) }}</p>
                                        </div>
                                        <span class="meter-status {{ $assignment->is_active ? 'active' : 'inactive' }}">{{ $assignment->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    </div>

                                    @if ($assignment->serviceArea)
                                        <div class="reading-meta" style="margin-top: 0; margin-bottom: 10px;">
                                            <span class="meter-pill">{{ $assignment->serviceArea->areaTypeLabel() }}</span>
                                            <span class="meter-pill">Berlaku untuk {{ $serviceAreaOptions->get($assignment->serviceArea->id, $assignment->serviceArea->name) }} dan turunannya</span>
                                        </div>
                                    @endif

                                    @if (filled($assignment->notes))
                                        <div class="meter-copy">{{ $assignment->notes }}</div>
                                    @endif

                                    <details class="record-edit">
                                        <summary>Edit Assignment</summary>
                                        <form method="POST" action="{{ route('tenant.tirta.meter-reader-assignments.update', $assignment->id) }}" class="meter-inline-form" style="margin-top: 14px;">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="period" value="{{ $selectedPeriod?->id }}">
                                            <input type="hidden" name="service_area_id_filter" value="{{ $filters['service_area_id'] }}">
                                            <input type="hidden" name="user_id_filter" value="{{ $filters['user_id'] }}">

                                            <div class="meter-form-grid two">
                                                <div>
                                                    <label class="field-label">Area / Wilayah</label>
                                                    <select name="service_area_id">
                                                        @foreach ($serviceAreas as $area)
                                                            <option value="{{ $area->id }}" @selected($assignment->service_area_id === $area->id)>{{ $serviceAreaOptions->get($area->id, $area->name) }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="field-label">Petugas</label>
                                                    <select name="user_id">
                                                        @foreach ($meterReaders as $reader)
                                                            <option value="{{ $reader->id }}" @selected($assignment->user_id === $reader->id)>
                                                                {{ $reader->name }} · {{ ucfirst((string) ($reader->roleSlug() ?? 'staff')) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <label class="checkbox-row">
                                                <input type="checkbox" name="is_active" value="1" @checked($assignment->is_active) style="margin-top: 3px;">
                                                <span>
                                                    <strong style="display: block;">Assignment aktif</strong>
                                                    <span class="mini-note" style="margin-top: 2px;">Nonaktifkan kalau area sementara belum dipegang petugas mana pun.</span>
                                                </span>
                                            </label>

                                            <div>
                                                <label class="field-label">Catatan</label>
                                                <textarea name="notes" rows="3">{{ $assignment->notes }}</textarea>
                                            </div>

                                            <button class="tenant-btn-secondary" type="submit">Update Assignment</button>
                                        </form>
                                    </details>
                                </div>
                            @empty
                                <div class="empty-state">Belum ada assignment petugas. Pilih area dan user aktif dulu supaya pembacaan meter bisa dibagi per area kerja.</div>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="meter-panel">
                        <div class="meter-head">
                            <div>
                                <h3>Ringkasan Operasional Global</h3>
                                <p>Mode global aktif, jadi panel assignment per area disembunyikan dan semua sambungan dianggap satu antrean kerja.</p>
                            </div>
                        </div>

                        <div class="summary-list">
                            <div class="summary-box">
                                <strong>Petugas Global</strong>
                                <span>{{ $defaultMeterReader?->name ?? 'Belum diatur' }}</span>
                            </div>
                            <div class="summary-box">
                                <strong>Total Sambungan</strong>
                                <span>{{ number_format($readingStats['connections'], 0, ',', '.') }} sambungan akan tampil sebagai `Semua Sambungan`.</span>
                            </div>
                            <div class="summary-box">
                                <strong>Area Master</strong>
                                <span>{{ $usesServiceAreas ? 'Data area tetap boleh ada di master, tapi diabaikan untuk distribusi kerja baca meter.' : 'Belum ada area, cocok untuk tenant kecil yang satu tim kerja.' }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </aside>
        </section>

        <section class="meter-grid">
            <div class="meter-panel">
                <div class="meter-head">
                    <div>
                        <h3>Input Pembacaan Meter</h3>
                        <p>Pilih sambungan aktif, masukkan angka meter sekarang, lalu sistem akan hitung pemakaian dan menandai warning kalau ada lonjakan.</p>
                    </div>
                </div>

                @if ($selectedPeriod)
                    <form method="POST" action="{{ route('tenant.tirta.meter-readings.store') }}" class="meter-form">
                        @csrf
                        <input type="hidden" name="meter_reading_period_id" value="{{ $selectedPeriod->id }}">

                        <div>
                            <label class="field-label" for="reading-connection">Sambungan</label>
                            <select id="reading-connection" name="service_connection_id">
                                <option value="">Pilih sambungan</option>
                                @if ($isMeterReader)
                                    @foreach ($connectionRows as $row)
                                        @php
                                            $connection = $row['connection'];
                                        @endphp
                                        <option value="{{ $connection->id }}" @selected(old('service_connection_id') === $connection->id)>
                                            {{ $connection->service_number }} - {{ $connection->customer?->name ?? 'Tanpa pelanggan' }}
                                        </option>
                                    @endforeach
                                @else
                                    @foreach ($connections as $connection)
                                        <option value="{{ $connection->id }}" @selected(old('service_connection_id') === $connection->id)>
                                            {{ $connection->service_number }} - {{ $connection->customer?->name ?? 'Tanpa pelanggan' }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="meter-form-grid two">
                            <div>
                                <label class="field-label" for="visit-status">Status Kunjungan</label>
                                <select id="visit-status" name="visit_status">
                                    @foreach ($visitStatusOptions as $visitValue => $visitLabel)
                                        <option value="{{ $visitValue }}" @selected(old('visit_status', 'read') === $visitValue)>{{ $visitLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="mini-note">Status selain `Berhasil Dibaca` otomatis masuk queue review dan diskip saat generate billing.</div>
                            </div>
                            <div>
                                <label class="field-label" for="current-reading">Angka Meter Sekarang</label>
                                <input id="current-reading" class="field" type="number" min="0" name="current_reading" value="{{ old('current_reading') }}" placeholder="Contoh: 1845">
                                <div class="mini-note">Boleh dikosongkan untuk kasus rumah kosong, pagar dikunci, meter rusak, dan kendala akses.</div>
                            </div>
                        </div>

                        <div class="meter-form-grid two">
                            <div>
                                <label class="field-label" for="reader-name">Nama Petugas</label>
                                <input id="reader-name" class="field" type="text" name="reader_name" value="{{ old('reader_name') }}" placeholder="Kosongkan untuk pakai user yang sedang login">
                            </div>
                            <div></div>
                        </div>

                        <div>
                            <label class="field-label" for="recorded-at">Waktu Catat</label>
                            <input id="recorded-at" class="field" type="datetime-local" name="recorded_at" value="{{ old('recorded_at', now()->format('Y-m-d\TH:i')) }}">
                        </div>

                        <div>
                            <label class="field-label" for="reading-notes">Catatan</label>
                            <textarea id="reading-notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            <div class="mini-note">Wajib diisi kalau hasil kunjungan bukan `Berhasil Dibaca`.</div>
                        </div>

                        <button class="tenant-btn" type="submit">Simpan Pembacaan</button>
                    </form>
                @else
                    <div class="empty-state">Belum ada periode aktif. Buat atau pilih periode baca meter dulu supaya form input bisa dipakai.</div>
                @endif
            </div>

            <div class="meter-stack">
                <div class="meter-panel">
                    <div class="meter-head">
                        <div>
                            <h3>Queue Sambungan</h3>
                            <p>Queue ini bisa difilter per area dan petugas. Kalau pilih cabang atau unit, semua turunan area ikut masuk otomatis. Kalau tenant belum pakai area, pilih `Semua Sambungan / Tanpa Area` atau biarkan tampil semua.</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('tenant.tirta.meter-readings') }}" class="filter-bar" style="margin-bottom: 16px;">
                        @if ($selectedPeriod)
                            <input type="hidden" name="period" value="{{ $selectedPeriod->id }}">
                        @endif
                        <div>
                            <label class="field-label">Filter Area</label>
                            <select name="service_area_id">
                                @if ($isGlobalMode)
                                    <option value="">Semua Sambungan</option>
                                @else
                                    <option value="">Semua area</option>
                                    <option value="__global__" @selected($filters['service_area_id'] === '__global__')>{{ $usesServiceAreas ? 'Tanpa Area' : 'Semua Sambungan' }}</option>
                                    @foreach ($serviceAreas as $area)
                                        <option value="{{ $area->id }}" @selected($filters['service_area_id'] === $area->id)>{{ $serviceAreaOptions->get($area->id, $area->name) }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        @if (! $isMeterReader)
                            <div>
                                <label class="field-label">Filter Petugas</label>
                                <select name="user_id">
                                    <option value="">Semua petugas</option>
                                    @foreach ($meterReaders as $reader)
                                        <option value="{{ $reader->id }}" @selected($filters['user_id'] === $reader->id)>{{ $reader->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Bucket Verifikator</label>
                                <select name="review_bucket">
                                    <option value="">Semua bucket</option>
                                    @foreach ($reviewTabs as $bucket => $tab)
                                        @if ($bucket !== 'all')
                                            <option value="{{ $bucket }}" @selected($filters['review_bucket'] === $bucket)>{{ $tab['label'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
                            <input type="hidden" name="review_bucket" value="{{ $filters['review_bucket'] }}">
                            <div class="summary-box" style="align-self: end;">
                                <strong>Petugas</strong>
                                <span>{{ auth('tenant')->user()?->name ?? '-' }}</span>
                            </div>
                        @endif
                        <div class="inline-actions" style="grid-column: 1 / -1;">
                            <button class="tenant-btn-secondary" type="submit">Terapkan Filter</button>
                            <a class="period-link" href="{{ route('tenant.tirta.meter-readings', array_filter(['period' => $selectedPeriod?->id])) }}">
                                <i class="fas fa-rotate-left"></i> Reset Filter
                            </a>
                        </div>
                    </form>

                    <div class="reading-list">
                        @forelse ($connectionRows as $row)
                            @php
                                $connection = $row['connection'];
                                $assignment = $row['assignment'];
                                $assignedReader = $row['assigned_reader'];
                                $currentReading = $row['current_reading'];
                                $previousReading = $row['previous_reading'];
                                $readingStatus = (string) $row['reading_status'];
                                $visitStatus = (string) ($row['visit_status'] ?? 'pending');
                                $reviewStatus = (string) ($row['review_status'] ?? 'pending');
                                $requiresReview = (bool) ($row['requires_review'] ?? false);
                                $readingCardClass = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                    ? 'reading-card-invalid'
                                    : ($requiresReview ? 'reading-card-review' : ($readingStatus === 'invalid' ? 'reading-card-invalid' : ($readingStatus === 'warning' ? 'reading-card-warning' : '')));
                                $statusClass = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                    ? 'failed'
                                    : ($requiresReview ? 'review' : (in_array($readingStatus, ['normal', 'open'], true) ? 'normal' : (in_array($readingStatus, ['warning', 'draft', 'pending'], true) ? 'warning' : 'invalid')));
                                $statusLabel = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                    ? ($visitStatusLabels[$visitStatus] ?? ucfirst(str_replace('_', ' ', $visitStatus)))
                                    : ($requiresReview ? 'Perlu Review' : ucfirst($readingStatus));
                            @endphp
                            <div class="meter-card {{ $readingCardClass }}">
                                <div class="meter-head">
                                    <div>
                                        <h4 class="meter-title">{{ $connection->service_number }} - {{ $connection->customer?->name ?? 'Tanpa pelanggan' }}</h4>
                                        <p class="meter-copy">
                                            {{ $row['service_area_label'] }}
                                            @if ($connection->meter_number)
                                                · Meter {{ $connection->meter_number }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="meter-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                </div>

                                <div class="reading-meta">
                                    <span class="meter-pill">Baseline: {{ number_format((int) $row['baseline_reading'], 0, ',', '.') }}</span>
                                    <span class="meter-pill">Sekarang: {{ number_format((int) ($currentReading?->current_reading ?? 0), 0, ',', '.') }}</span>
                                    <span class="meter-pill">Pemakaian: {{ number_format((int) ($row['usage_volume'] ?? 0), 0, ',', '.') }} m3</span>
                                    @if ($currentReading)
                                        <span class="meter-pill">Kunjungan: {{ $visitStatusLabels[$visitStatus] ?? ucfirst(str_replace('_', ' ', $visitStatus)) }}</span>
                                        <span class="meter-pill">Review: {{ $reviewStatusLabels[$reviewStatus] ?? ucfirst(str_replace('_', ' ', $reviewStatus)) }}</span>
                                        @if (filled($row['follow_up_action']))
                                            <span class="meter-pill">Tindak lanjut: {{ $followUpLabels[$row['follow_up_action']] ?? ucfirst(str_replace('_', ' ', $row['follow_up_action'])) }}</span>
                                        @endif
                                    @endif
                                    <span class="meter-pill">
                                        Petugas:
                                        {{ $assignedReader?->name ?? 'Belum diassign' }}
                                        @if ($row['assignment_scope'] === 'global')
                                            · Global
                                        @elseif ($row['assignment_scope'] === 'area')
                                            · Area Langsung
                                        @elseif ($row['assignment_scope'] === 'ancestor')
                                            · Turunan dari {{ $row['assignment_area_label'] }}
                                        @endif
                                    </span>
                                </div>

                                <div class="meter-copy">
                                    @if ($currentReading)
                                        Dicatat {{ $currentReading->recorded_at?->format('d M Y H:i') ?? '-' }}
                                        @if ($currentReading->reader_name)
                                            oleh {{ $currentReading->reader_name }}
                                        @endif
                                    @else
                                        Belum ada pembacaan untuk periode ini.
                                    @endif

                                    @if ($assignment && filled($assignment->notes))
                                        <br>Catatan assignment: {{ $assignment->notes }}
                                    @endif

                                    @if ($previousReading?->period)
                                        <br>Periode sebelumnya: {{ $previousReading->period->name }}
                                    @endif

                                    @if (filled($currentReading?->anomaly_notes))
                                        <br>{{ $currentReading->anomaly_notes }}
                                    @endif

                                    @if ($currentReading && ! empty($currentReading->review_flags))
                                        <br>Flag review: {{ implode(' | ', $currentReading->review_flags) }}
                                    @endif

                                    @if ($currentReading && $currentReading->customer_notification_status !== 'not_applicable')
                                        <br>Notifikasi pelanggan: {{ ucfirst(str_replace('_', ' ', $currentReading->customer_notification_status)) }}
                                        @if (! empty($currentReading->customer_notification_channels))
                                            via {{ implode(', ', $currentReading->customer_notification_channels) }}
                                        @endif
                                    @endif
                                </div>

                                @if ($selectedPeriod)
                                    @if ($currentReading && ($requiresReview || in_array($currentReading->customer_notification_status, ['pending', 'failed'], true)))
                                        <div class="inline-actions" style="margin-top: 14px; flex-wrap: wrap;">
                                            @if ($requiresReview && $visitStatus === 'read' && $readingStatus !== 'invalid')
                                                <form method="POST" action="{{ route('tenant.tirta.meter-readings.review', $currentReading->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="approve">
                                                    <button class="tenant-btn-secondary" type="submit">Approve Verifikator</button>
                                                </form>
                                            @endif

                                            @if ($currentReading->visit_status !== 'read')
                                                <form method="POST" action="{{ route('tenant.tirta.meter-readings.review', $currentReading->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="revisit">
                                                    <button class="tenant-btn-secondary" type="submit">Jadwalkan Ulang</button>
                                                </form>

                                                <form method="POST" action="{{ route('tenant.tirta.meter-readings.review', $currentReading->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="inspection">
                                                    <button class="tenant-btn-secondary" type="submit">Minta Inspeksi</button>
                                                </form>

                                                <form method="POST" action="{{ route('tenant.tirta.meter-readings.review', $currentReading->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="contact_customer">
                                                    <button class="tenant-btn-secondary" type="submit">Hubungi Pelanggan</button>
                                                </form>
                                            @endif

                                            @if (in_array($currentReading->customer_notification_status, ['pending', 'failed'], true))
                                                <form method="POST" action="{{ route('tenant.tirta.meter-readings.review', $currentReading->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="action" value="send_notification">
                                                    <button class="tenant-btn-secondary" type="submit">Kirim Ulang Email</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif

                                    <details class="record-edit">
                                        <summary>{{ $currentReading ? 'Edit Pembacaan' : 'Input Cepat' }}</summary>
                                        <form method="POST" action="{{ $currentReading ? route('tenant.tirta.meter-readings.update', $currentReading->id) : route('tenant.tirta.meter-readings.store') }}" class="meter-inline-form" style="margin-top: 14px;" enctype="multipart/form-data" data-meter-evidence-form>
                                            @csrf
                                            @if ($currentReading)
                                                @method('PATCH')
                                            @else
                                                <input type="hidden" name="meter_reading_period_id" value="{{ $selectedPeriod->id }}">
                                                <input type="hidden" name="service_connection_id" value="{{ $connection->id }}">
                                            @endif
                                            <input type="hidden" name="recorded_latitude" value="{{ $currentReading?->recorded_latitude }}" data-meter-latitude>
                                            <input type="hidden" name="recorded_longitude" value="{{ $currentReading?->recorded_longitude }}" data-meter-longitude>
                                            <input type="hidden" name="recorded_accuracy_meters" value="{{ $currentReading?->recorded_accuracy_meters }}" data-meter-accuracy>

                                            <div class="meter-form-grid two">
                                                <div>
                                                    <label class="field-label">Status Kunjungan</label>
                                                    <select name="visit_status">
                                                        @foreach ($visitStatusOptions as $visitValue => $visitLabel)
                                                            <option value="{{ $visitValue }}" @selected(($currentReading?->visit_status ?? 'read') === $visitValue)>
                                                                {{ $visitLabel }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="field-label">Angka Meter Sekarang</label>
                                                    <input class="field" type="number" min="0" name="current_reading" value="{{ $currentReading?->current_reading }}">
                                                </div>
                                            </div>

                                            <div class="meter-form-grid two">
                                                <div>
                                                    <label class="field-label">Nama Petugas</label>
                                                    <input class="field" type="text" name="reader_name" value="{{ $currentReading?->reader_name ?? $assignedReader?->name }}">
                                                </div>
                                                <div></div>
                                            </div>

                                            <div>
                                                <label class="field-label">Waktu Catat</label>
                                                <input class="field" type="datetime-local" name="recorded_at" value="{{ $currentReading?->recorded_at?->format('Y-m-d\TH:i') }}">
                                            </div>

                                            <div class="meter-form-grid two">
                                                <div>
                                                    <label class="field-label">Foto Evidence Meter</label>
                                                    <input class="field" type="file" name="evidence_photo" accept="image/*" capture="environment" data-evidence-input>
                                                    <div class="mini-note">Bisa dipakai admin/verifikator untuk upload evidence baru kalau perlu.</div>
                                                </div>
                                                <div>
                                                    <label class="field-label">Koordinat Lokasi</label>
                                                    <button class="tenant-btn-secondary" type="button" data-capture-location>Ambil Lokasi Saya</button>
                                                    <div class="location-status" data-location-status>
                                                        @if ($currentReading?->recorded_latitude && $currentReading?->recorded_longitude)
                                                            Koordinat tersimpan: {{ $currentReading->recorded_latitude }}, {{ $currentReading->recorded_longitude }}
                                                        @else
                                                            Lokasi belum diambil.
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="field-label">Catatan</label>
                                                <textarea name="notes" rows="3">{{ $currentReading?->notes }}</textarea>
                                                <div class="mini-note">Jelaskan kalau pembacaan dipindah ke rumah kosong, pagar dikunci, inspeksi meter, atau perlu hubungi pelanggan.</div>
                                            </div>

                                            <button class="tenant-btn-secondary" type="submit">{{ $currentReading ? 'Update Pembacaan' : 'Simpan Pembacaan' }}</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <div class="empty-state">Tidak ada sambungan yang cocok dengan filter aktif. Coba reset filter atau lengkapi assignment area lebih dulu.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const forms = Array.from(document.querySelectorAll('[data-meter-evidence-form]'));

            if (!forms.length || !navigator.geolocation) {
                return;
            }

            const setLocationState = (message) => {
                forms.forEach((form) => {
                    const status = form.querySelector('[data-location-status]');

                    if (status) {
                        status.textContent = message;
                    }
                });
            };

            const writeCoordinates = (latitude, longitude) => {
                forms.forEach((form) => {
                    const latitudeInput = form.querySelector('[data-meter-latitude]');
                    const longitudeInput = form.querySelector('[data-meter-longitude]');
                    const accuracyInput = form.querySelector('[data-meter-accuracy]');
                    const status = form.querySelector('[data-location-status]');

                    if (latitudeInput) {
                        latitudeInput.value = latitude;
                    }

                    if (longitudeInput) {
                        longitudeInput.value = longitude;
                    }

                    if (accuracyInput && accuracyInput.dataset.pendingAccuracy) {
                        accuracyInput.value = accuracyInput.dataset.pendingAccuracy;
                        delete accuracyInput.dataset.pendingAccuracy;
                    }

                    if (status) {
                        const accuracy = accuracyInput && accuracyInput.value ? ` (±${accuracyInput.value}m)` : '';
                        status.textContent = `Koordinat siap: ${latitude}, ${longitude}${accuracy}`;
                    }
                });
            };

            const captureLocation = () => {
                setLocationState('Mengambil lokasi...');

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        forms.forEach((form) => {
                            const accuracyInput = form.querySelector('[data-meter-accuracy]');
                            if (accuracyInput) {
                                accuracyInput.dataset.pendingAccuracy = position.coords.accuracy.toFixed(2);
                            }
                        });
                        writeCoordinates(
                            position.coords.latitude.toFixed(7),
                            position.coords.longitude.toFixed(7)
                        );
                    },
                    () => {
                        setLocationState('Lokasi gagal diambil. Izinkan GPS lalu coba lagi.');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0,
                    }
                );
            };

            forms.forEach((form) => {
                const button = form.querySelector('[data-capture-location]');
                const evidenceInput = form.querySelector('[data-evidence-input]');
                const preview = form.querySelector('[data-evidence-preview]');
                const previewThumb = form.querySelector('[data-evidence-thumb]');
                const previewName = form.querySelector('[data-evidence-name]');

                if (button) {
                    button.addEventListener('click', captureLocation);
                }

                if (evidenceInput && preview && previewThumb && previewName) {
                    const loadImage = (file) => new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => {
                            const img = new Image();
                            img.onload = () => resolve(img);
                            img.onerror = () => reject(new Error('invalid_image'));
                            img.src = reader.result;
                        };
                        reader.onerror = () => reject(new Error('read_error'));
                        reader.readAsDataURL(file);
                    });

                    const compressSquareJpeg = (img, size, quality) => new Promise((resolve) => {
                        const canvas = document.createElement('canvas');
                        canvas.width = size;
                        canvas.height = size;
                        const ctx = canvas.getContext('2d');

                        if (!ctx) {
                            resolve(null);
                            return;
                        }

                        const square = Math.min(img.width, img.height);
                        const sourceX = Math.floor((img.width - square) / 2);
                        const sourceY = Math.floor((img.height - square) / 2);

                        ctx.drawImage(img, sourceX, sourceY, square, square, 0, 0, size, size);

                        if (!canvas.toBlob) {
                            resolve(null);
                            return;
                        }

                        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
                    });

                    const replaceFile = (input, file) => {
                        if (!window.DataTransfer) {
                            return false;
                        }

                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(file);
                        input.files = dataTransfer.files;
                        return true;
                    };

                    evidenceInput.addEventListener('change', async () => {
                        const file = evidenceInput.files && evidenceInput.files[0] ? evidenceInput.files[0] : null;

                        if (!file) {
                            preview.style.display = 'none';
                            previewThumb.src = '';
                            previewName.textContent = '';
                            return;
                        }

                        const originalSizeKb = Math.round(file.size / 1024);

                        preview.style.display = 'flex';
                        previewName.textContent = `${file.name} (${originalSizeKb} KB)`;
                        previewThumb.src = URL.createObjectURL(file);

                        if (evidenceInput.dataset.compressed === '1') {
                            return;
                        }

                        try {
                            const img = await loadImage(file);
                            const targetSize = Math.min(768, Math.min(img.width, img.height));
                            const blob = await compressSquareJpeg(img, targetSize, 0.75);

                            if (!blob) {
                                return;
                            }

                            const compressedFile = new File(
                                [blob],
                                `meter_evidence_${Date.now()}.jpg`,
                                { type: 'image/jpeg' }
                            );

                            if (!replaceFile(evidenceInput, compressedFile)) {
                                return;
                            }

                            evidenceInput.dataset.compressed = '1';

                            const compressedSizeKb = Math.round(compressedFile.size / 1024);
                            previewName.textContent = `Dikompres: ${compressedSizeKb} KB (asal ${originalSizeKb} KB)`;
                            previewThumb.src = URL.createObjectURL(compressedFile);
                        } catch (e) {
                            return;
                        }
                    });
                }
            });

            captureLocation();
        })();
    </script>
@endpush
