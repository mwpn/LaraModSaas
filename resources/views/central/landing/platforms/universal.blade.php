<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $landingBrand = trim((string) data_get($platformExperience ?? [], 'brand_name', ''));
        $landingHeadline = trim((string) data_get($platformExperience ?? [], 'headline', ''));
        $displayBrand = $landingBrand !== '' ? $landingBrand : config('app.name', 'AirCloud');
        $brandInitial = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $displayBrand) ?: 'A', 0, 1));
        $pageTitle = $landingHeadline !== ''
            ? $landingHeadline . ' | ' . $displayBrand
            : $displayBrand;
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; font-family: "Plus Jakarta Sans", sans-serif; background: #f8fafc; color: #0f172a; }
        .shell { max-width: 1120px; margin: 0 auto; padding: 24px; }
        .nav { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; }
        .mark { width: 42px; height: 42px; border-radius: 14px; background: linear-gradient(135deg, #2563eb, #0f172a); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border-radius: 14px; text-decoration: none; font-weight: 700; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        .hero { margin-top: 28px; padding: 40px; border-radius: 28px; background: linear-gradient(135deg, #0f172a, #1d4ed8); color: #fff; }
        .hero h1 { margin: 0; font-size: clamp(2.2rem, 6vw, 4.5rem); line-height: 1.05; }
        .hero p { margin: 18px 0 0; max-width: 720px; line-height: 1.8; color: rgba(255,255,255,.78); }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-top: 28px; }
        .hero-card { padding: 22px; border-radius: 22px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.12); }
        .hero-card strong { display: block; font-size: 1rem; }
        .hero-card p { margin-top: 8px; color: rgba(255,255,255,.8); }
        .section { margin-top: 26px; padding: 28px; border-radius: 26px; background: #fff; border: 1px solid #e2e8f0; }
        .section h2 { margin: 0 0 10px; font-size: 2rem; }
        .section p { margin: 0; color: #64748b; line-height: 1.8; }
        .section-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-top: 22px; }
        .section-card { padding: 22px; border-radius: 22px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .section-card strong { display: block; margin-bottom: 8px; }
        @media (max-width: 860px) {
            .grid, .section-grid { grid-template-columns: 1fr; }
            .hero, .section { padding: 24px; }
            .nav { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="nav">
            <a href="{{ route('central.home') }}" class="brand">
                <span class="mark">{{ $brandInitial }}</span>
                <span>
                    <strong>{{ $displayBrand }}</strong><br>
                    <span style="color:#64748b; font-size:.85rem;">Platform SaaS pusat</span>
                </span>
            </a>
            <div class="actions">
                <a href="{{ route('central.login') }}" class="btn btn-secondary">Masuk Panel Pusat</a>
                <a href="{{ route('central.register.create') }}" class="btn btn-primary">Ajukan Demo</a>
            </div>
        </div>
        <section class="hero">
            <h1>{{ $landingHeadline !== '' ? $landingHeadline : 'Kelola produk SaaS dari satu panel pusat yang rapi.' }}</h1>
            <p>Mode universal dibuat singkat. Yang dipertahankan cuma CTA dan info inti yang memang dipakai.</p>
            <div class="actions" style="margin-top:24px;">
                <a href="{{ route('central.register.create') }}" class="btn btn-primary">Mulai Request Demo</a>
                <a href="{{ route('central.login') }}" class="btn btn-secondary">Login Super Admin</a>
            </div>
            <div class="grid">
                <div class="hero-card">
                    <strong>Route nyata</strong>
                    <p>CTA langsung ke demo dan login yang aktif.</p>
                </div>
                <div class="hero-card">
                    <strong>Brand tetap clean</strong>
                    <p>Tampilan tetap bersih tanpa copy dinamis berlapis.</p>
                </div>
                <div class="hero-card">
                    <strong>Fallback aman</strong>
                    <p>Tetap aman dipakai saat platform belum dipilih.</p>
                </div>
            </div>
        </section>
        <section class="section">
            <h2>Yang disisakan cuma inti</h2>
            <p>Landing source-based dipakai untuk platform spesifik. Halaman universal cukup jadi pintu masuk umum yang cepat dan ringan.</p>
            <div class="section-grid">
                <div class="section-card">
                    <strong>Public entry</strong>
                    <p>Pengunjung langsung pilih masuk panel atau ajukan demo.</p>
                </div>
                <div class="section-card">
                    <strong>Tanpa config berat</strong>
                    <p>Tidak lagi bergantung pada headline dan panel dinamis yang numpuk.</p>
                </div>
                <div class="section-card">
                    <strong>Siap dikembangkan</strong>
                    <p>Kalau nanti perlu template khusus, file ini bisa diganti terpisah.</p>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
