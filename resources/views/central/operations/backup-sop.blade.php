@extends('central.layouts.master')

@section('page_title', 'Backup SOP')
@section('page_subtitle', 'Runbook minimum sebelum delete tenant, migrasi besar, atau maintenance billing')

@section('content')
    <div class="page-grid">
        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-database"></i> Backup Runbook</span>
                <h2>Operations Backup SOP</h2>
                <p>Checklist minimum untuk backup dan restore central serta tenant sebelum aksi operasional yang destructive.</p>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="mini-list">
                <div class="mini-row"><span>Scope</span><strong>Central DB, tenant DB, .env, config server, storage billing</strong></div>
                <div class="mini-row"><span>Routine</span><strong>Backup harian central + tenant, simpan 7 harian dan 4 mingguan</strong></div>
                <div class="mini-row"><span>Wajib Sebelum Aksi Besar</span><strong>Delete tenant, migrasi besar, ubah credential produksi, maintenance billing massal</strong></div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Restore Checklist</h3>
                </div>
            </div>

            <div class="form-stack">
                <div class="quick-item"><div><strong>1. Verifikasi backup</strong><span>Cek file backup dan timestamp sebelum restore.</span></div></div>
                <div class="quick-item"><div><strong>2. Restore ke staging dulu</strong><span>Kalau memungkinkan, validasi hasil restore di staging sebelum produksi.</span></div></div>
                <div class="quick-item"><div><strong>3. Restore central lalu tenant</strong><span>Utamakan central DB, baru tenant yang relevan.</span></div></div>
                <div class="quick-item"><div><strong>4. Validasi flow utama</strong><span>Cek login central, tenant lookup, invoice metadata, dan scheduler state.</span></div></div>
                <div class="quick-item"><div><strong>5. Hidupkan worker lagi</strong><span>Pastikan queue worker dan scheduler aktif kembali.</span></div></div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Post Restore Checks</h3>
                </div>
            </div>

            <div class="form-stack">
                <pre class="field">php artisan route:list</pre>
                <pre class="field">php artisan queue:work --once</pre>
                <pre class="field">php artisan billing:scan-reminders</pre>
                <div class="quick-item"><div><strong>Ops Health</strong><span>Buka halaman health untuk cek error log terbaru.</span></div></div>
            </div>
        </section>
    </div>
@endsection
