<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $themeCatalog = [
                'tirta' => [
                    'initial' => 'A',
                    'brand' => 'Aqualytic',
                    'label' => 'Kontrol Air',
                    'accent' => '#164e63',
                    'accent_soft' => '#ecfeff',
                    'badge' => 'Akses Billing Air',
                    'title' => 'Masuk ke panel billing air',
                    'description' => 'Pantau meter, tagihan, dan tunggakan dari satu panel.',
                    'aside_title' => 'Kontrol sambungan, invoice, dan pembayaran.',
                    'aside_description' => 'Login dibuat ringkas supaya operator langsung fokus ke kerja inti.',
                    'preview_label' => 'Billing Summary',
                    'preview_value' => 'Tagihan aktif, WA jalan, dan anomali pemakaian siap dipantau.',
                    'panels' => [
                        ['label' => 'Browser', 'value' => 'admin.aqualytic.id/billing/summary'],
                        ['label' => 'Status', 'value' => '1.240 SR aktif dan penagihan siap diproses'],
                        ['label' => 'Highlight', 'value' => 'Lonjakan pemakaian dan tunggakan tampil real-time'],
                    ],
                ],
                'hotel' => [
                    'initial' => 'H',
                    'brand' => 'InnSystem',
                    'label' => 'Kontrol Properti',
                    'accent' => '#0f172a',
                    'accent_soft' => '#f8fafc',
                    'badge' => 'Akses Properti',
                    'title' => 'Masuk ke panel properti',
                    'description' => 'Kelola okupansi, housekeeping, dan revenue dari satu panel.',
                    'aside_title' => 'Akses cepat ke front office dan owner dashboard.',
                    'aside_description' => 'Tetap clean, tapi nuansa vertical hotel masih terasa.',
                    'preview_label' => 'Occupancy Board',
                    'preview_value' => 'Front office, housekeeping, dan revenue tetap sinkron.',
                    'panels' => [
                        ['label' => 'Browser', 'value' => 'app.innsystem.com/dashboard/occupancy'],
                        ['label' => 'Status', 'value' => 'Okupansi 87.5% dengan room status terbaca live'],
                        ['label' => 'Highlight', 'value' => 'Night audit dan sinkron OTA siap dipantau'],
                    ],
                ],
                'resto' => [
                    'initial' => 'R',
                    'brand' => 'RestoFlow',
                    'label' => 'Kontrol Outlet',
                    'accent' => '#171717',
                    'accent_soft' => '#fafafa',
                    'badge' => 'Akses Outlet',
                    'title' => 'Masuk ke panel outlet',
                    'description' => 'Akses kasir, dapur, stok, dan laporan outlet dari satu panel.',
                    'aside_title' => 'Kasir, dapur, dan owner report tetap nyambung.',
                    'aside_description' => 'Yang dipertahankan cuma identitas vertical dan info kerja inti.',
                    'preview_label' => 'Live Orders',
                    'preview_value' => 'Pesanan outlet, status dapur, dan stok kritis bisa dilihat dari satu layar.',
                    'panels' => [
                        ['label' => 'Browser', 'value' => 'pos.restoflow.id/live-orders'],
                        ['label' => 'Status', 'value' => 'Kasir terhubung dan antrean dapur aktif'],
                        ['label' => 'Highlight', 'value' => 'Order online dan stok bahan tetap sinkron'],
                    ],
                ],
                'netbilling' => [
                    'initial' => 'N',
                    'brand' => 'NetFlow.id',
                    'label' => 'Kontrol Network',
                    'accent' => '#4f46e5',
                    'accent_soft' => '#0b1120',
                    'badge' => 'Akses Network',
                    'title' => 'Masuk ke panel billing jaringan',
                    'description' => 'Pantau pelanggan aktif, auto isolir, router sync, dan log jaringan dari satu panel.',
                    'aside_title' => 'Kontrol billing, API router, dan alarm jaringan.',
                    'aside_description' => 'Nuansa network tetap tegas, tapi alurnya tetap ringan.',
                    'preview_label' => 'Router Sync',
                    'preview_value' => 'Recurring billing jalan dan status pelanggan langsung terlihat.',
                    'panels' => [
                        ['label' => 'Browser', 'value' => 'panel.netflow.id/router-1/pppoe'],
                        ['label' => 'Status', 'value' => '842 pelanggan aktif dan auto isolir berjalan'],
                        ['label' => 'Highlight', 'value' => 'Alarm OLT dan log jaringan terkumpul dalam satu panel'],
                    ],
                ],
                'universal' => [
                    'initial' => 'A',
                    'brand' => config('app.name', 'AirCloud'),
                    'label' => 'Panel Pusat',
                    'accent' => '#2563eb',
                    'accent_soft' => '#eff6ff',
                    'badge' => 'Secure Access',
                    'title' => 'Masuk ke panel pusat',
                    'description' => 'Akses tenant, pengaturan platform, dan operasional pusat dari satu panel.',
                    'aside_title' => 'Kontrol pusat untuk semua vertical aktif.',
                    'aside_description' => 'Mode universal dibuat sederhana dan langsung ke inti.',
                    'preview_label' => 'Platform Workspace',
                    'preview_value' => 'Landing, tenant, dan pengaturan pusat tetap terkumpul rapi.',
                    'panels' => [
                        ['label' => 'Browser', 'value' => 'app.aircloud.biz.id/platform/overview'],
                        ['label' => 'Status', 'value' => 'Workspace modular siap dipakai lintas vertical'],
                        ['label' => 'Highlight', 'value' => 'Tenant, billing, dan automasi pusat bisa dipantau dari satu akses'],
                    ],
                ],
            ];
            $theme = $themeCatalog[$platformType] ?? $themeCatalog['universal'];
        @endphp
        <title>{{ $theme['brand'] }} Login</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <style>
            :root {
                --accent: {{ $theme['accent'] }};
                --accent-soft: {{ $theme['accent_soft'] }};
                --text: #0f172a;
                --muted: #64748b;
                --bg: #eef3f9;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: var(--text);
                background:
                    radial-gradient(circle at top left, color-mix(in srgb, var(--accent) 14%, transparent), transparent 26%),
                    radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.06), transparent 18%),
                    var(--bg);
            }
            a { color: inherit; text-decoration: none; }
            .shell { min-height: 100vh; display: grid; grid-template-columns: minmax(0, .92fr) minmax(0, 1.08fr); }
            .panel { display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
            .card {
                width: 100%;
                max-width: 480px;
                padding: 30px;
                border-radius: 28px;
                background: rgba(255, 255, 255, .92);
                border: 1px solid rgba(255, 255, 255, .64);
                box-shadow: 0 24px 60px rgba(15, 23, 42, .1);
                backdrop-filter: blur(18px);
            }
            .brand { display: inline-flex; align-items: center; gap: 12px; }
            .brand-mark {
                width: 46px;
                height: 46px;
                border-radius: 14px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-weight: 800;
                background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 78%, #fff), var(--accent));
                box-shadow: 0 18px 30px color-mix(in srgb, var(--accent) 24%, transparent);
            }
            .brand-copy strong { display: block; font-size: 1rem; }
            .brand-copy span { display: block; margin-top: 3px; font-size: .78rem; color: var(--muted); }
            h1 { margin: 24px 0 10px; font-size: 2.15rem; line-height: 1.05; letter-spacing: -.03em; }
            .lead { margin: 0; color: var(--muted); line-height: 1.75; }
            .form-stack { display: grid; gap: 18px; margin-top: 24px; }
            .field-label { display: block; margin-bottom: 8px; font-size: .875rem; font-weight: 700; }
            .field {
                width: 100%;
                min-height: 50px;
                padding: 12px 14px;
                border-radius: 14px;
                border: 1px solid #d1d5db;
                background: #fff;
                outline: none;
                transition: border-color .18s ease, box-shadow .18s ease;
            }
            .field:focus { border-color: var(--accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 12%, transparent); }
            .form-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .remember { display: inline-flex; align-items: center; gap: 10px; color: var(--muted); font-size: .875rem; }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                min-height: 50px;
                border: 0;
                border-radius: 14px;
                color: #fff;
                font-weight: 800;
                cursor: pointer;
                background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 82%, #fff), var(--accent));
                box-shadow: 0 18px 34px color-mix(in srgb, var(--accent) 24%, transparent);
                transition: transform .18s ease;
            }
            .btn:hover { transform: translateY(-1px); }
            .helper-links { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 18px; font-size: .875rem; }
            .helper-links a { color: var(--muted); }
            .alert { margin-top: 18px; padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(148, 163, 184, 0.24); font-size: .875rem; }
            .alert-ok { background: #ecfdf5; border-color: #bbf7d0; color: #166534; }
            .alert-danger { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
            .alert-danger ul { margin: 0; padding-left: 18px; }
            .aside {
                position: relative;
                overflow: hidden;
                padding: 40px;
                background: linear-gradient(135deg, #08111d 0%, color-mix(in srgb, var(--accent) 46%, #0f172a) 100%);
                color: #fff;
            }
            .aside::before {
                content: "";
                position: absolute;
                inset: 0;
                background:
                    radial-gradient(circle at top left, rgba(255, 255, 255, .12), transparent 28%),
                    radial-gradient(circle at bottom right, color-mix(in srgb, var(--accent) 28%, transparent), transparent 30%);
            }
            .aside-inner { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; justify-content: space-between; gap: 28px; }
            .pill {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 7px 12px;
                border-radius: 999px;
                background: rgba(255, 255, 255, .12);
                border: 1px solid rgba(255, 255, 255, .12);
                font-size: .75rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: .08em;
            }
            .aside h2 { margin: 20px 0 12px; max-width: 720px; font-size: clamp(2rem, 4vw, 3.2rem); line-height: 1.04; }
            .aside p { max-width: 720px; color: rgba(255, 255, 255, .76); line-height: 1.8; }
            .visual {
                margin-top: 24px;
                min-height: 280px;
                border-radius: 28px;
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, .1);
                background: rgba(255, 255, 255, .08);
            }
            .visual-fallback {
                width: 100%;
                height: 100%;
                padding: 24px;
                display: grid;
                align-content: end;
                gap: 12px;
                background:
                    radial-gradient(circle at top left, rgba(255, 255, 255, .14), transparent 28%),
                    linear-gradient(180deg, rgba(255, 255, 255, .08), rgba(255, 255, 255, .04));
            }
            .window {
                padding: 18px;
                border-radius: 18px;
                background: rgba(10, 15, 27, 0.64);
                border: 1px solid rgba(255, 255, 255, .08);
            }
            .window span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: rgba(255, 255, 255, .56); }
            .window strong { display: block; margin-top: 8px; font-size: 1rem; }
            .info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
            .info-card {
                padding: 18px;
                border-radius: 20px;
                background: rgba(255, 255, 255, .08);
                border: 1px solid rgba(255, 255, 255, .1);
                backdrop-filter: blur(10px);
            }
            .info-card span { display: block; font-size: .72rem; color: rgba(255, 255, 255, .58); text-transform: uppercase; letter-spacing: .08em; }
            .info-card strong { display: block; margin-top: 8px; font-size: 1rem; }
            @media (max-width: 1023px) {
                .shell { grid-template-columns: 1fr; }
                .aside { min-height: 420px; }
            }
            @media (max-width: 720px) {
                .info-grid { grid-template-columns: 1fr; }
            }
            @media (max-width: 640px) {
                .panel, .aside { padding: 24px 16px; }
                .card { padding: 24px; border-radius: 24px; }
                .helper-links { flex-direction: column; }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <section class="panel">
                <div class="card">
                    <a href="{{ route('central.home') }}" class="brand">
                        <span class="brand-mark">{{ $theme['initial'] }}</span>
                        <span class="brand-copy">
                            <strong>{{ $theme['brand'] }}</strong>
                            <span>{{ $theme['label'] }}</span>
                        </span>
                    </a>

                    <h1>{{ $theme['title'] }}</h1>
                    <p class="lead">{{ $theme['description'] }}</p>

                    @if (session('status'))
                        <div class="alert alert-ok">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ url('/login') }}" class="form-stack">
                        @csrf

                        <div>
                            <label for="email" class="field-label">Email</label>
                            <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="Masukkan email">
                        </div>

                        <div>
                            <div class="form-row">
                                <label for="password" class="field-label" style="margin-bottom: 0;">Password</label>
                                <a href="{{ route('central.home') }}" style="color: var(--muted); font-size: .875rem;">Kembali ke landing</a>
                            </div>
                            <input id="password" class="field" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password" style="margin-top: 8px;">
                        </div>

                        <label class="remember">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                            <span>Remember me</span>
                        </label>

                        <button type="submit" class="btn">Masuk ke Control Center</button>
                    </form>

                    <div class="helper-links">
                        <a href="{{ route('central.register.create') }}">Ajukan demo</a>
                        <a href="{{ route('central.home') }}">Kembali ke landing</a>
                    </div>
                </div>
            </section>

            <aside class="aside">
                <div class="aside-inner">
                    <div>
                        <span class="pill"><i class="fas fa-shield-halved"></i> {{ $theme['badge'] }}</span>
                        <h2>{{ $theme['aside_title'] }}</h2>
                        <p>{{ $theme['aside_description'] }}</p>

                        <div class="visual">
                            <div class="visual-fallback">
                                <div class="window">
                                    <span>{{ $theme['preview_label'] }}</span>
                                    <strong>{{ $theme['preview_value'] }}</strong>
                                </div>
                                <div class="window" style="background: color-mix(in srgb, var(--accent-soft) 18%, rgba(10, 15, 27, .68));">
                                    <span>Focus</span>
                                    <strong>Surface dibuat lebih statis supaya tetap konsisten dan ringan.</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-grid">
                        @foreach ($theme['panels'] as $panel)
                            <div class="info-card">
                                <span>{{ $panel['label'] }}</span>
                                <strong>{{ $panel['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </body>
</html>
