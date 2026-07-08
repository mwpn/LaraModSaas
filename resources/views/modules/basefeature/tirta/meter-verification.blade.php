@extends('basefeature::layouts.master')

@section('page_title', 'Dashboard Verifikator')
@section('page_subtitle', 'Queue verifikasi baca meter, tindak lanjut lapangan, dan notifikasi pelanggan')

@push('styles')
    <style>
        .verify-grid,
        .verify-stack,
        .verify-stats,
        .verify-list,
        .verify-summary,
        .verify-meta {
            display: grid;
            gap: 16px;
        }
        .verify-grid {
            grid-template-columns: minmax(0, 1.7fr) minmax(300px, 0.9fr);
        }
        .verify-stats {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .verify-card,
        .verify-stat,
        .verify-summary-card,
        .verify-queue-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
        }
        .verify-card,
        .verify-queue-card {
            padding: 18px;
        }
        .verify-stat,
        .verify-summary-card {
            padding: 16px;
        }
        .verify-card-head,
        .verify-queue-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .verify-card-head h3,
        .verify-queue-head h3 {
            margin: 0;
            font-size: 1rem;
        }
        .verify-card-head p,
        .verify-queue-head p,
        .verify-summary-card span,
        .verify-stat span {
            margin: 6px 0 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: 0.84rem;
        }
        .verify-stat strong,
        .verify-summary-card strong {
            display: block;
            font-size: 1rem;
            color: var(--text);
        }
        .verify-pill,
        .verify-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .verify-pill {
            background: color-mix(in srgb, var(--primary) 10%, #ffffff);
            color: var(--primary);
        }
        .verify-status.normal {
            color: #166534;
            background: #dcfce7;
            border-color: #bbf7d0;
        }
        .verify-status.warning {
            color: #92400e;
            background: #fef3c7;
            border-color: #fcd34d;
        }
        .verify-status.review {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #bfdbfe;
        }
        .verify-status.failed,
        .verify-status.invalid {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fecaca;
        }
        .verify-tabs,
        .verify-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .verify-meta {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            margin-top: 14px;
        }
        .verify-meta-item {
            padding: 10px 12px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 0.8rem;
            color: #475569;
        }
        .verify-copy {
            margin-top: 14px;
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.75;
        }
        .verify-flags {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #fff7ed;
            border: 1px solid #fdba74;
            font-size: 0.82rem;
            color: #9a3412;
            line-height: 1.7;
        }
        .verify-empty {
            padding: 26px;
            text-align: center;
            color: var(--muted);
            border-radius: 16px;
            border: 1px dashed var(--border);
            background: #ffffff;
        }
        @media (max-width: 1023px) {
            .verify-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $visitStatusLabels = $visitStatusOptions ?? [];
        $followUpLabels = $followUpActionOptions ?? [];
        $reviewStatusLabels = $reviewStatusOptions ?? [];
        $reviewBucket = $filters['review_bucket'] !== '' ? $filters['review_bucket'] : 'need_review';
        $reviewTabs = [
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

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-clipboard-check"></i> TirtaVerifikator</span>
                <h2>Dashboard Verifikator Baca Meter</h2>
                <p>Workspace ini fokus ke antrian pembacaan bermasalah, tindak lanjut kunjungan ulang, inspeksi teknis, dan notifikasi pelanggan tanpa tercampur form operasional petugas.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Periode Aktif</span>
                    <strong>{{ $selectedPeriod?->name ?? 'Belum dipilih' }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Butuh Review</span>
                    <strong>{{ $verifierStats['need_review'] ?? 0 }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Notif Pending</span>
                    <strong>{{ $verifierStats['notification_pending'] ?? 0 }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Scope Area</span>
                    <strong>{{ $areaScopeLabel }}</strong>
                </div>
            </div>
        </section>

        <section class="verify-stats">
            <div class="verify-stat">
                <strong>{{ $verifierStats['need_verification'] ?? 0 }}</strong>
                <span>Tunggu verifikasi manual</span>
            </div>
            <div class="verify-stat">
                <strong>{{ $verifierStats['revisit_required'] ?? 0 }}</strong>
                <span>Perlu kunjungan ulang</span>
            </div>
            <div class="verify-stat">
                <strong>{{ $verifierStats['inspection_required'] ?? 0 }}</strong>
                <span>Perlu inspeksi teknis</span>
            </div>
            <div class="verify-stat">
                <strong>{{ $verifierStats['customer_contact_required'] ?? 0 }}</strong>
                <span>Perlu hubungi pelanggan</span>
            </div>
            <div class="verify-stat">
                <strong>{{ $verifierStats['verified'] ?? 0 }}</strong>
                <span>Sudah beres</span>
            </div>
        </section>

        <section class="verify-grid">
            <div class="verify-stack">
                <div class="verify-card">
                    <div class="verify-card-head">
                        <div>
                            <h3>Bucket Verifikasi</h3>
                            <p>Pilih queue kerja utama verifikator lalu tindak lanjuti langsung dari kartu sambungan di bawah.</p>
                        </div>
                        <span class="verify-pill"><i class="fas fa-filter"></i> Queue Fokus</span>
                    </div>

                    <div class="verify-tabs" style="margin-top: 16px;">
                        @foreach ($reviewTabs as $bucket => $tab)
                            <a
                                href="{{ route('tenant.tirta.meter-verification', array_filter(['period' => $selectedPeriod?->id, 'review_bucket' => $bucket, 'service_area_id' => $filters['service_area_id'] !== '' ? $filters['service_area_id'] : null, 'user_id' => $filters['user_id'] !== '' ? $filters['user_id'] : null])) }}"
                                class="{{ $reviewBucket === $bucket ? 'tenant-btn' : 'tenant-btn-secondary' }}"
                            >
                                {{ $tab['label'] }} ({{ $tab['count'] }})
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="verify-card">
                    <div class="verify-card-head">
                        <div>
                            <h3>Filter Dashboard</h3>
                            <p>Filter periodik dan area untuk memecah pekerjaan verifikator per rayon, unit, atau petugas lapangan.</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('tenant.tirta.meter-verification') }}" class="filter-bar" style="margin-top: 16px;">
                        <div>
                            <label class="field-label">Periode</label>
                            <select name="period">
                                @foreach ($periods as $period)
                                    <option value="{{ $period->id }}" @selected((string) $selectedPeriod?->id === (string) $period->id)>{{ $period->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Bucket Verifikator</label>
                            <select name="review_bucket">
                                @foreach ($reviewTabs as $bucket => $tab)
                                    <option value="{{ $bucket }}" @selected($filters['review_bucket'] === $bucket || ($filters['review_bucket'] === '' && $bucket === 'need_review'))>{{ $tab['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Filter Area</label>
                            <select name="service_area_id">
                                <option value="">Semua area</option>
                                <option value="__global__" @selected($filters['service_area_id'] === '__global__')>{{ $usesServiceAreas ? 'Tanpa Area' : 'Semua Sambungan' }}</option>
                                @foreach ($serviceAreas as $area)
                                    <option value="{{ $area->id }}" @selected($filters['service_area_id'] === $area->id)>{{ $serviceAreaOptions->get($area->id, $area->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Filter Petugas</label>
                            <select name="user_id">
                                <option value="">Semua petugas</option>
                                @foreach ($meterReaders as $reader)
                                    <option value="{{ $reader->id }}" @selected($filters['user_id'] === $reader->id)>{{ $reader->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="inline-actions" style="grid-column: 1 / -1;">
                            <button class="tenant-btn-secondary" type="submit">Terapkan Filter</button>
                            <a class="period-link" href="{{ route('tenant.tirta.meter-verification', array_filter(['period' => $selectedPeriod?->id, 'review_bucket' => 'need_review'])) }}">
                                <i class="fas fa-rotate-left"></i> Reset
                            </a>
                            <a class="period-link" href="{{ route('tenant.tirta.meter-readings', array_filter(['period' => $selectedPeriod?->id])) }}">
                                <i class="fas fa-arrow-up-right-from-square"></i> Buka Catat Meter
                            </a>
                        </div>
                    </form>
                </div>

                <div class="verify-list">
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
                            $statusClass = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                ? 'failed'
                                : ($requiresReview ? 'review' : (in_array($readingStatus, ['warning', 'draft', 'pending'], true) ? 'warning' : (in_array($readingStatus, ['invalid'], true) ? 'invalid' : 'normal')));
                            $statusLabel = $visitStatus !== 'read' && $visitStatus !== 'pending'
                                ? ($visitStatusLabels[$visitStatus] ?? ucfirst(str_replace('_', ' ', $visitStatus)))
                                : ($requiresReview ? 'Perlu Review' : ucfirst($readingStatus));
                        @endphp
                        <div class="verify-queue-card">
                            <div class="verify-queue-head">
                                <div>
                                    <h3>{{ $connection->service_number }} - {{ $connection->customer?->name ?? 'Tanpa pelanggan' }}</h3>
                                    <p>{{ $row['service_area_label'] }} @if ($connection->meter_number) · Meter {{ $connection->meter_number }} @endif</p>
                                </div>
                                <span class="verify-status {{ $statusClass }}">{{ $statusLabel }}</span>
                            </div>

                            <div class="verify-meta">
                                <div class="verify-meta-item">Baseline: {{ number_format((int) $row['baseline_reading'], 0, ',', '.') }}</div>
                                <div class="verify-meta-item">Sekarang: {{ number_format((int) ($currentReading?->current_reading ?? 0), 0, ',', '.') }}</div>
                                <div class="verify-meta-item">Pemakaian: {{ number_format((int) ($row['usage_volume'] ?? 0), 0, ',', '.') }} m3</div>
                                <div class="verify-meta-item">Review: {{ $reviewStatusLabels[$reviewStatus] ?? ucfirst(str_replace('_', ' ', $reviewStatus)) }}</div>
                                <div class="verify-meta-item">Kunjungan: {{ $visitStatusLabels[$visitStatus] ?? ucfirst(str_replace('_', ' ', $visitStatus)) }}</div>
                                <div class="verify-meta-item">Petugas: {{ $assignedReader?->name ?? 'Belum diassign' }}</div>
                            </div>

                            <div class="verify-copy">
                                @if ($currentReading)
                                    Dicatat {{ $currentReading->recorded_at?->format('d M Y H:i') ?? '-' }}
                                    @if ($currentReading->reader_name)
                                        oleh {{ $currentReading->reader_name }}
                                    @endif
                                @else
                                    Belum ada pembacaan untuk periode ini.
                                @endif

                                @if ($previousReading?->period)
                                    <br>Periode sebelumnya: {{ $previousReading->period->name }}
                                @endif
                                @if ($assignment && filled($assignment->notes))
                                    <br>Catatan assignment: {{ $assignment->notes }}
                                @endif
                                @if (filled($currentReading?->anomaly_notes))
                                    <br>{{ $currentReading->anomaly_notes }}
                                @endif
                                @if ($currentReading && $currentReading->customer_notification_status !== 'not_applicable')
                                    <br>Notifikasi pelanggan: {{ ucfirst(str_replace('_', ' ', $currentReading->customer_notification_status)) }}
                                    @if (! empty($currentReading->customer_notification_channels))
                                        via {{ implode(', ', $currentReading->customer_notification_channels) }}
                                    @endif
                                @endif
                                @if (filled($row['follow_up_action']))
                                    <br>Tindak lanjut: {{ $followUpLabels[$row['follow_up_action']] ?? ucfirst(str_replace('_', ' ', $row['follow_up_action'])) }}
                                @endif
                            </div>

                            @if ($currentReading && ! empty($currentReading->review_flags))
                                <div class="verify-flags">
                                    {{ implode(' | ', $currentReading->review_flags) }}
                                </div>
                            @endif

                            @if ($currentReading)
                                <div class="verify-actions" style="margin-top: 14px;">
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

                                    <a class="period-link" href="{{ route('tenant.tirta.meter-readings', array_filter(['period' => $selectedPeriod?->id, 'service_area_id' => $filters['service_area_id'] !== '' ? $filters['service_area_id'] : null, 'user_id' => $filters['user_id'] !== '' ? $filters['user_id'] : null])) }}">
                                        <i class="fas fa-arrow-up-right-from-square"></i> Buka Workspace Lengkap
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="verify-empty">
                            Tidak ada sambungan pada bucket verifikator ini. Coba ganti periode, bucket review, atau filter area.
                        </div>
                    @endforelse
                </div>
            </div>

            <aside class="verify-stack">
                <div class="verify-summary">
                    <div class="verify-summary-card">
                        <strong>{{ $selectedPeriod?->name ?? 'Belum ada periode' }}</strong>
                        <span>{{ $selectedPeriod?->period_start?->format('d M Y') ?? '-' }} - {{ $selectedPeriod?->period_end?->format('d M Y') ?? '-' }}</span>
                    </div>
                    <div class="verify-summary-card">
                        <strong>{{ $readingStats['recorded'] }} pembacaan tercatat</strong>
                        <span>{{ $readingStats['warnings'] }} masih butuh review dan {{ $readingStats['pending'] }} belum ada pembacaan.</span>
                    </div>
                    <div class="verify-summary-card">
                        <strong>{{ $workflowStats['billing_locked_periods'] }} periode billing lock</strong>
                        <span>{{ $workflowStats['draft_billing_periods'] }} periode sudah punya draft billing dan perlu hati-hati saat koreksi.</span>
                    </div>
                </div>

                <div class="verify-card">
                    <div class="verify-card-head">
                        <div>
                            <h3>Aturan Kerja Cepat</h3>
                            <p>Ringkasannya biar verifikator nggak perlu buka modul lain saat ambil keputusan.</p>
                        </div>
                    </div>

                    <div class="verify-summary" style="margin-top: 16px;">
                        <div class="verify-summary-card">
                            <strong>Approve</strong>
                            <span>Pakai kalau bacaan final valid, evidence cukup, dan tidak ada anomali yang tersisa.</span>
                        </div>
                        <div class="verify-summary-card">
                            <strong>Kunjungan Ulang</strong>
                            <span>Pakai untuk kasus rumah kosong, pagar dikunci, atau evidence lapangan belum memadai.</span>
                        </div>
                        <div class="verify-summary-card">
                            <strong>Inspeksi Teknis</strong>
                            <span>Pakai untuk meter rusak, indikasi tampering, stagnan mencurigakan, atau mismatch berulang.</span>
                        </div>
                        <div class="verify-summary-card">
                            <strong>Hubungi Pelanggan</strong>
                            <span>Pakai untuk kasus akses meter, koordinasi buka pagar, atau notifikasi gagal baca.</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </div>
@endsection
