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
                    'label' => 'Demo Air',
                    'accent' => '#164e63',
                    'badge' => 'Demo Billing Air',
                    'title' => 'Ajukan demo sistem air',
                    'description' => 'Isi kontak singkat, lalu tim kami follow up untuk jadwal demo dan kesiapan migrasi data.',
                    'aside_title' => 'Cocok untuk operator air dan pengelola sambungan aktif.',
                    'aside_description' => 'Alur request demo dibuat lebih langsung dan ringan.',
                    'preview_label' => 'Field onboarding',
                    'preview_value' => 'Cocok untuk billing sambungan, meter, dan notifikasi tagihan.',
                    'steps' => [
                        ['title' => 'Audit kebutuhan', 'body' => 'Kami cek skema tarif, pencatatan meter, dan alur penagihan yang dipakai sekarang.'],
                        ['title' => 'Simulasi demo', 'body' => 'Tim menyiapkan contoh billing cycle dan pembayaran yang relevan.'],
                        ['title' => 'Migrasi bertahap', 'body' => 'Data lama bisa dibantu rapikan sebelum masuk ke sistem baru.'],
                    ],
                ],
                'hotel' => [
                    'initial' => 'H',
                    'brand' => 'InnSystem',
                    'label' => 'Demo Properti',
                    'accent' => '#0f172a',
                    'badge' => 'Demo Hotel',
                    'title' => 'Ajukan demo manajemen hotel',
                    'description' => 'Cocok untuk owner atau operasional hotel yang ingin lihat reservasi, housekeeping, dan revenue dalam satu sistem.',
                    'aside_title' => 'Demo difokuskan ke alur properti yang paling sering bikin bottleneck.',
                    'aside_description' => 'Presentasi bisa disesuaikan dengan tipe properti Anda.',
                    'preview_label' => 'Property onboarding',
                    'preview_value' => 'Pas untuk PMS, sinkron OTA, front office, dan owner reporting.',
                    'steps' => [
                        ['title' => 'Property mapping', 'body' => 'Kami identifikasi jumlah kamar, channel booking, dan kebutuhan tim Anda.'],
                        ['title' => 'Live walkthrough', 'body' => 'Demo diarahkan ke room matrix, housekeeping, dan laporan utama.'],
                        ['title' => 'Rollout plan', 'body' => 'Kalau cocok, tim bantu susun tahapan implementasi tanpa ganggu operasional.'],
                    ],
                ],
                'resto' => [
                    'initial' => 'R',
                    'brand' => 'RestoFlow',
                    'label' => 'Demo Outlet',
                    'accent' => '#171717',
                    'badge' => 'Demo Outlet',
                    'title' => 'Ajukan demo POS outlet',
                    'description' => 'Isi kontak untuk lihat alur kasir, dapur, stok, dan laporan outlet sesuai bisnis F&B Anda.',
                    'aside_title' => 'Demo disusun biar owner langsung lihat dampak ke kasir, stok, dan dapur.',
                    'aside_description' => 'Form dibuat lebih padat supaya cepat diisi.',
                    'preview_label' => 'Outlet onboarding',
                    'preview_value' => 'Cocok untuk cafe, resto, cloud kitchen, dan multi-outlet.',
                    'steps' => [
                        ['title' => 'Outlet profiling', 'body' => 'Kami mapping jumlah outlet, kanal order, dan struktur menu saat ini.'],
                        ['title' => 'Operational demo', 'body' => 'Sesi demo fokus ke order flow, KDS, stok bahan, dan laporan owner.'],
                        ['title' => 'Go-live setup', 'body' => 'Bila lanjut, tim bantu siapkan struktur menu dan onboarding outlet.'],
                    ],
                ],
                'netbilling' => [
                    'initial' => 'N',
                    'brand' => 'NetFlow.id',
                    'label' => 'Demo Network',
                    'accent' => '#4f46e5',
                    'badge' => 'Demo Billing ISP',
                    'title' => 'Ajukan demo billing ISP',
                    'description' => 'Isi kontak untuk lihat recurring billing, auto isolir, integrasi router, dan panel pelanggan.',
                    'aside_title' => 'Demo diarahkan untuk operator RT/RW Net, WISP, dan tim network.',
                    'aside_description' => 'Konteks jaringan tetap terasa, tapi form tetap ringan.',
                    'preview_label' => 'Subscriber onboarding',
                    'preview_value' => 'Cocok untuk recurring billing internet, router sync, voucher, dan alert jaringan.',
                    'steps' => [
                        ['title' => 'Topology review', 'body' => 'Kami cek router, profile pelanggan, model isolir, dan jalur pembayaran.'],
                        ['title' => 'Automation preview', 'body' => 'Demo fokus ke auto suspend, re-enable, dan status pelanggan real-time.'],
                        ['title' => 'Migration planning', 'body' => 'Kalau lanjut, rollout bisa dipecah per node atau wilayah.'],
                    ],
                ],
                'universal' => [
                    'initial' => 'A',
                    'brand' => config('app.name', 'AirCloud'),
                    'label' => 'Demo Pusat',
                    'accent' => '#2563eb',
                    'badge' => 'Guided Onboarding',
                    'title' => 'Ajukan request demo',
                    'description' => 'Isi data singkat, lalu tim kami arahkan demo ke vertical yang paling cocok.',
                    'aside_title' => 'Request demo umum untuk produk multi-vertical.',
                    'aside_description' => 'Mode universal dibuat sederhana dan cepat dipahami.',
                    'preview_label' => 'Platform onboarding',
                    'preview_value' => 'Tenant baru diarahkan ke blueprint vertical yang paling relevan.',
                    'steps' => [
                        ['title' => 'Intake singkat', 'body' => 'Kami pahami kebutuhan bisnis, skala operasional, dan vertical yang paling cocok.'],
                        ['title' => 'Demo terarah', 'body' => 'Tim menyiapkan walkthrough berdasarkan flow kerja yang relevan.'],
                        ['title' => 'Tahap lanjut', 'body' => 'Setelah cocok, kami bantu susun rencana implementasi berikutnya.'],
                    ],
                ],
            ];
            $theme = $themeCatalog[$platformType] ?? $themeCatalog['universal'];
        @endphp
        <title>{{ $theme['brand'] }} Demo</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <style>
            :root {
                --accent: {{ $theme['accent'] }};
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
                    radial-gradient(circle at top left, color-mix(in srgb, var(--accent) 16%, transparent), transparent 28%),
                    radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.06), transparent 18%),
                    var(--bg);
            }
            a { color: inherit; text-decoration: none; }
            .shell { min-height: 100vh; display: grid; grid-template-columns: minmax(0, .96fr) minmax(0, 1.04fr); }
            .panel { display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
            .card {
                width: 100%;
                max-width: 520px;
                padding: 30px;
                border-radius: 28px;
                background: rgba(255, 255, 255, .92);
                border: 1px solid rgba(255, 255, 255, .6);
                box-shadow: 0 24px 60px rgba(15, 23, 42, .1);
                backdrop-filter: blur(18px);
            }
            .brand { display: inline-flex; align-items: center; gap: 12px; }
            .brand-mark {
                width: 44px;
                height: 44px;
                border-radius: 14px;
                color: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 84%, #fff), var(--accent));
                box-shadow: 0 16px 28px color-mix(in srgb, var(--accent) 24%, transparent);
            }
            .brand-copy strong { display: block; font-size: 1rem; }
            .brand-copy span { display: block; margin-top: 3px; font-size: .78rem; color: var(--muted); }
            h1 { margin: 24px 0 10px; font-size: 2.2rem; line-height: 1.05; letter-spacing: -.03em; }
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
            .grid-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
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
            .hint {
                padding: 14px 16px;
                border-radius: 16px;
                background: #f8fafc;
                border: 1px solid rgba(148, 163, 184, .18);
                color: var(--muted);
                line-height: 1.65;
                font-size: .92rem;
            }
            .alert { margin-top: 18px; padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(148, 163, 184, 0.24); font-size: .875rem; }
            .alert-danger { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
            .alert-danger ul { margin: 0; padding-left: 18px; }
            .alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #047857; }
            .helper-links { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 18px; font-size: .875rem; }
            .helper-links a { color: var(--muted); }
            .aside {
                position: relative;
                overflow: hidden;
                padding: 40px;
                background: linear-gradient(135deg, #08111d 0%, color-mix(in srgb, var(--accent) 44%, #0f172a) 100%);
                color: #fff;
            }
            .aside::before {
                content: "";
                position: absolute;
                inset: 0;
                background:
                    radial-gradient(circle at top left, rgba(255, 255, 255, .12), transparent 28%),
                    radial-gradient(circle at bottom right, color-mix(in srgb, var(--accent) 34%, transparent), transparent 32%);
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
            .stack { display: grid; gap: 14px; margin-top: 22px; }
            .stack-item {
                padding: 16px 18px;
                border-radius: 20px;
                background: rgba(255, 255, 255, .08);
                border: 1px solid rgba(255, 255, 255, .1);
                backdrop-filter: blur(10px);
            }
            .stack-item span { display: block; font-size: .72rem; color: rgba(255, 255, 255, .58); text-transform: uppercase; letter-spacing: .08em; }
            .stack-item strong { display: block; margin-top: 8px; font-size: 1rem; }
            .visual {
                min-height: 260px;
                border-radius: 28px;
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, .1);
                background: rgba(255, 255, 255, .08);
                display: flex;
                align-items: stretch;
            }
            .visual-fallback {
                width: 100%;
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
            @media (max-width: 1023px) {
                .shell { grid-template-columns: 1fr; }
                .aside { min-height: 420px; }
            }
            @media (max-width: 640px) {
                .panel, .aside { padding: 24px 16px; }
                .card { padding: 24px; border-radius: 24px; }
                .grid-two { grid-template-columns: 1fr; }
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
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
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

                    <form method="POST" action="{{ route('central.register') }}" class="form-stack">
                        @csrf

                        <div class="grid-two">
                            <div>
                                <label class="field-label" for="name">Nama</label>
                                <input id="name" class="field" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Contoh: Budi Santoso">
                            </div>
                            <div>
                                <label class="field-label" for="email">Email</label>
                                <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="contoh: budi@bisnisanda.com">
                            </div>
                        </div>

                        <div>
                            <label class="field-label" for="phone_number">Nomor HP</label>
                            <input id="phone_number" class="field" type="text" name="phone_number" value="{{ old('phone_number') }}" required autocomplete="tel" placeholder="contoh: 081234567890">
                        </div>

                        <div class="hint">
                            Data Anda masuk ke antrean demo platform <strong>{{ strtoupper((string) $platformType) }}</strong> dan akan ditindaklanjuti tim kami.
                        </div>

                        <button type="submit" class="btn">Kirim Request Demo</button>
                    </form>

                    <div class="helper-links">
                        <a href="{{ route('central.login') }}">Sudah punya akses superadmin?</a>
                        <a href="{{ route('central.home') }}">Kembali ke landing</a>
                    </div>
                </div>
            </section>

            <aside class="aside">
                <div class="aside-inner">
                    <div>
                        <span class="pill"><i class="fas fa-diagram-project"></i> {{ $theme['badge'] }}</span>
                        <h2>{{ $theme['aside_title'] }}</h2>
                        <p>{{ $theme['aside_description'] }}</p>

                        <div class="visual">
                            <div class="visual-fallback">
                                <div class="window">
                                    <span>{{ $theme['preview_label'] }}</span>
                                    <strong>{{ $theme['preview_value'] }}</strong>
                                </div>
                                <div class="window">
                                    <span>Output</span>
                                    <strong>Request demo masuk ke lead pipeline pusat agar follow-up tetap rapi.</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stack">
                        @foreach ($theme['steps'] as $step)
                            <div class="stack-item">
                                <span>{{ $step['title'] }}</span>
                                <strong>{{ $step['body'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </body>
</html>
