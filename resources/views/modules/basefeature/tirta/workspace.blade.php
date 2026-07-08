@extends('basefeature::layouts.master')

@section('page_title', 'Workspace Tirta')
@section('page_subtitle', 'Panel operasional awal untuk alur pelanggan, meter, dan tagihan air')

@push('styles')
    <style>
        .workspace-accent {
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 14%, #ffffff), #ffffff);
            border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--border));
        }
        .ops-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr);
            gap: 24px;
        }
        .ops-stack,
        .section-stack,
        .queue-list,
        .check-list,
        .anchor-list {
            display: grid;
            gap: 16px;
        }
        .ops-card,
        .queue-card,
        .section-card,
        .mini-card {
            padding: 18px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        .ops-card strong,
        .queue-card strong,
        .section-card strong,
        .mini-card strong {
            display: block;
            font-size: 0.9375rem;
            color: var(--text);
        }
        .ops-card span,
        .queue-card span,
        .section-card span,
        .mini-card span {
            display: block;
            margin-top: 6px;
            font-size: 0.8125rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .ops-card-top,
        .queue-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .ops-label,
        .queue-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .ops-label {
            background: color-mix(in srgb, var(--primary) 10%, #ffffff);
            color: var(--primary);
        }
        .queue-status {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .section-card h3,
        .queue-card h3 {
            margin: 0;
            font-size: 1rem;
        }
        .section-card p,
        .queue-card p {
            margin: 8px 0 0;
            font-size: 0.875rem;
            color: var(--muted);
            line-height: 1.7;
        }
        .section-list {
            margin-top: 14px;
            display: grid;
            gap: 10px;
        }
        .section-list-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 0.875rem;
            color: #334155;
        }
        .section-list-item i {
            margin-top: 2px;
            color: var(--primary);
        }
        .anchor-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .anchor-link:hover {
            border-color: color-mix(in srgb, var(--primary) 20%, var(--border));
            box-shadow: var(--shadow-sm);
        }
        .anchor-link strong {
            font-size: 0.9rem;
        }
        .anchor-link span {
            margin-top: 4px;
            font-size: 0.8rem;
        }
        .check-list-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #ecfeff;
            border: 1px solid #bae6fd;
            color: #0f172a;
            font-size: 0.875rem;
            line-height: 1.65;
        }
        .check-list-item i {
            color: #0891b2;
            margin-top: 2px;
        }
        @media (max-width: 1023px) {
            .ops-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="page-grid">
        @if (! empty($accessContext['area_scope_label']) || ! empty($accessContext['role_label']))
            <div class="alert alert-info">
                Login sebagai <strong>{{ $accessContext['role_label'] ?? 'Pengguna Tenant' }}</strong>
                @if (! empty($accessContext['area_scope_label']))
                    dengan cakupan area <strong>{{ $accessContext['area_scope_label'] }}</strong> dan turunannya.
                @else
                    dengan cakupan seluruh tenant.
                @endif
            </div>
        @endif

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-water"></i> Tirta</span>
                <h2>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</h2>
                <p>Workspace ini jadi titik kerja awal buat operasional air: siap menampung master pelanggan, pembacaan meter, billing, dan kontrol pembayaran per sprint.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Status</span>
                    <strong>Workspace Aktif</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Mode</span>
                    <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'tirta')) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Role Aktif</span>
                    <strong style="font-size: 0.95rem;">{{ $accessContext['role_label'] ?? 'Pengguna Tenant' }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Scope Area</span>
                    <strong style="font-size: 0.95rem;">{{ $accessContext['area_scope_label'] ?? 'Semua Area' }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            @foreach ($workspaceStats as $stat)
                <div class="stat-card">
                    <div class="stat-inner">
                        <span class="stat-icon"><i class="fas {{ $stat['icon'] }}"></i></span>
                        <div class="stat-copy">
                            <p>{{ $stat['label'] }}</p>
                            <strong style="font-size: 1.05rem;">{{ $stat['value'] }}</strong>
                            <span>{{ $stat['hint'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="ops-grid">
            <div class="ops-stack">
                <div class="dashboard-card workspace-accent">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Control Deck</h3>
                            <p class="card-subtitle">Shortcut inti yang ditampilkan sesuai role dan area kerja pengguna yang sedang login.</p>
                        </div>
                    </div>

                    <div class="quick-grid">
                        @foreach ($quickActions as $action)
                            <a class="quick-item" href="{{ $action['route'] }}">
                                <div>
                                    <strong>{{ $action['label'] }}</strong>
                                    <span>{{ $action['description'] }}</span>
                                </div>
                                <i class="fas {{ $action['icon'] }} muted"></i>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Sprint Queue</h3>
                            <p class="card-subtitle">Peta eksekusi fitur Tirta yang langsung nyambung ke workspace ini.</p>
                        </div>
                    </div>

                    <div class="queue-list">
                        @foreach ($workQueues as $queue)
                            <div class="queue-card">
                                <div class="queue-head">
                                    <div>
                                        <h3>{{ $queue['title'] }}</h3>
                                        <p>{{ $queue['description'] }}</p>
                                    </div>
                                    <span class="queue-status">{{ $queue['status'] }}</span>
                                </div>
                                <div class="inline-actions" style="margin-top: 14px;">
                                    <a class="tenant-btn-secondary" href="#{{ $queue['anchor'] }}">Buka Ringkasan</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="section-stack">
                    <section id="master-pelanggan" class="section-card">
                        <span class="ops-label"><i class="fas fa-id-card"></i> Master Pelanggan</span>
                        <h3>Fondasi Sambungan Rumah dan Data Warga</h3>
                        <p>Area ini disiapkan untuk data pelanggan, nomor SR, zona tarif, status aktif/nonaktif, dan atribut teknis sambungan yang nanti dipakai di pembacaan meter serta billing bulanan.</p>
                        <div class="section-list">
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Data inti: nomor pelanggan, nama, alamat layanan, golongan, dan nomor meter.</span></div>
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Status sambungan: aktif, tunggakan, putus sementara, atau segel lapangan.</span></div>
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Relasi siap dipakai untuk histori pemakaian dan invoice periode berikutnya.</span></div>
                        </div>
                    </section>

                    <section id="pembacaan-meter" class="section-card">
                        <span class="ops-label"><i class="fas fa-gauge-high"></i> Pembacaan Meter</span>
                        <h3>Workspace Petugas Lapangan</h3>
                        <p>Halaman ini menampung daftar pembacaan meter, input angka akhir, dan validasi lonjakan konsumsi untuk deteksi kebocoran atau salah catat sebelum masuk ke billing.</p>
                        <div class="section-list">
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Periode baca meter bulanan dengan progres petugas per wilayah.</span></div>
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Validasi lonjakan pemakaian dibanding bulan sebelumnya.</span></div>
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Siap disambung ke input lapangan atau aplikasi mobile pada sprint lanjutan.</span></div>
                        </div>
                    </section>

                    <section id="tagihan-pembayaran" class="section-card">
                        <span class="ops-label"><i class="fas fa-file-invoice-dollar"></i> Tagihan & Pembayaran</span>
                        <h3>Kontrol Billing, Piutang, dan Follow Up</h3>
                        <p>Begitu data meter tersedia, blok ini akan jadi pusat generate tagihan, distribusi invoice digital, pelacakan outstanding, dan kontrol pembayaran pelanggan air.</p>
                        <div class="section-list">
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Generate invoice bulanan per periode operasional.</span></div>
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Status pembayaran, denda, dan aging piutang pelanggan.</span></div>
                            <div class="section-list-item"><i class="fas fa-check-circle"></i><span>Siap disambungkan ke QRIS, transfer, atau loket pembayaran tenant.</span></div>
                        </div>
                    </section>
                </div>
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Navigasi Sprint</h3>
                            <p class="card-subtitle">Shortcut ke blok kerja utama di halaman ini.</p>
                        </div>
                    </div>

                    <div class="anchor-list">
                        @foreach ($workQueues as $queue)
                            <a class="anchor-link" href="#{{ $queue['anchor'] }}">
                                <div>
                                    <strong>{{ $queue['title'] }}</strong>
                                    <span>{{ $queue['status'] }}</span>
                                </div>
                                <i class="fas fa-arrow-right muted"></i>
                            </a>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Matriks Role Aktif</h3>
                            <p class="card-subtitle">Tugas inti yang otomatis terbuka untuk akun yang sedang login.</p>
                        </div>
                    </div>

                    <div class="check-list">
                        @forelse (($rolePlaybook ?? []) as $ability)
                            <div class="check-list-item">
                                <i class="fas fa-badge-check"></i>
                                <span>{{ $ability }}</span>
                            </div>
                        @empty
                            <div class="check-list-item">
                                <i class="fas fa-circle-info"></i>
                                <span>Akun ini belum punya matriks kerja operasional khusus. Cek penugasan role atau area kerjanya.</span>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Readiness Check</h3>
                            <p class="card-subtitle">Checklist hasil sprint awal yang sudah siap dipakai.</p>
                        </div>
                    </div>

                    <div class="check-list">
                        @foreach ($readinessChecklist as $item)
                            <div class="check-list-item">
                                <i class="fas fa-circle-check"></i>
                                <span>{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Catatan Implementasi</h3>
                            <p class="card-subtitle">Batas scope sprint ini supaya struktur tetap aman.</p>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Schema</span>
                                <strong>Master, meter, billing</strong>
                        </div>
                        <div class="mini-row">
                            <span>Fokus</span>
                                <strong>Generate invoice Tirta</strong>
                        </div>
                        <div class="mini-row">
                            <span>Next Step</span>
                                <strong>Pembayaran pelanggan</strong>
                        </div>
                    </div>
                </section>
            </aside>
        </section>
    </div>
@endsection
