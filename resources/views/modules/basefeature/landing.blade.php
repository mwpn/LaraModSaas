<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <style>
            :root {
                --accent: {{ $setting?->theme_color ?? '#2563eb' }};
                --bg: #f9fafb;
                --panel: #ffffff;
                --border: #e5e7eb;
                --text: #111827;
                --muted: #6b7280;
                --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
                --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.08);
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                min-height: 100vh;
                background:
                    radial-gradient(circle at top left, color-mix(in srgb, var(--accent) 14%, transparent), transparent 28%),
                    radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.04), transparent 18%),
                    var(--bg);
                color: var(--text);
            }
            a { color: inherit; text-decoration: none; }
            .container { max-width: 1280px; margin: 0 auto; padding: 24px 16px 56px; }
            .topbar {
                display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
                padding: 14px 0 24px;
            }
            .brand { display: inline-flex; align-items: center; gap: 12px; min-width: 0; }
            .brand-mark {
                width: 40px; height: 40px; border-radius: 12px; background: var(--accent); color: #fff;
                display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 12px 24px color-mix(in srgb, var(--accent) 24%, transparent);
            }
            .brand-copy { min-width: 0; }
            .brand-copy strong { display: block; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .brand-copy span { display: block; margin-top: 2px; font-size: .75rem; color: var(--muted); }
            .topbar-actions { display: flex; gap: 10px; flex-wrap: wrap; }
            .btn, .btn-secondary {
                display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                min-height: 42px; padding: 0 16px; border-radius: 12px; border: 1px solid transparent; font-weight: 600;
                transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, filter .18s ease;
            }
            .btn { background: var(--accent); color: #fff; box-shadow: var(--shadow-sm); }
            .btn-secondary { background: #fff; border-color: var(--border); color: var(--text); }
            .btn:hover, .btn-secondary:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
            .btn:hover { filter: brightness(1.03); }
            .btn-secondary:hover { border-color: color-mix(in srgb, var(--accent) 18%, var(--border)); }
            .hero {
                display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(280px, .85fr); gap: 24px; align-items: stretch;
            }
            .hero-main {
                padding: 28px; border-radius: 20px; color: #fff;
                background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 88%, #0f172a) 0%, var(--accent) 100%);
                box-shadow: var(--shadow-md);
                position: relative;
                overflow: hidden;
            }
            .hero-main::after {
                content: "";
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at top left, rgba(255,255,255,.16), transparent 28%), linear-gradient(180deg, rgba(255,255,255,.06), transparent 40%);
                pointer-events: none;
            }
            .hero-badge {
                display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px;
                background: rgba(255,255,255,.14); font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
            }
            .hero-main h1 { margin: 18px 0 12px; font-size: clamp(2rem, 4vw, 3.2rem); line-height: 1.05; }
            .hero-main p { margin: 0; max-width: 720px; color: rgba(255,255,255,.82); line-height: 1.7; }
            .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 22px; }
            .hero-actions .btn-secondary { background: rgba(255,255,255,.12); color: #fff; border-color: rgba(255,255,255,.18); }
            .hero-side, .card { background: var(--panel); border: 1px solid var(--border); border-radius: 18px; box-shadow: var(--shadow-sm); transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .hero-side { padding: 22px; display: grid; gap: 16px; }
            .hero-side:hover, .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: color-mix(in srgb, var(--accent) 12%, var(--border)); }
            .side-item { padding: 16px; border-radius: 14px; background: #f9fafb; border: 1px solid var(--border); }
            .side-item span { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
            .side-item strong { display: block; margin-top: 6px; font-size: 1.05rem; }
            .stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 16px; margin-top: 24px; }
            .stat { padding: 20px; background: var(--panel); border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--shadow-sm); }
            .stat-label { color: var(--muted); font-size: .82rem; }
            .stat strong { display: block; margin-top: 8px; font-size: 1.8rem; }
            .content-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); gap: 24px; margin-top: 24px; }
            .card { padding: 24px; }
            .card h2 { margin: 0; font-size: 1.2rem; }
            .card p { color: var(--muted); line-height: 1.7; }
            .feature-list { display: grid; gap: 12px; margin-top: 18px; }
            .feature-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px; background: #fff; transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
            .feature-item i { color: var(--accent); }
            .feature-item:hover { transform: translateY(-1px); border-color: color-mix(in srgb, var(--accent) 14%, var(--border)); box-shadow: var(--shadow-sm); }
            .mini-list { display: grid; gap: 12px; margin-top: 18px; }
            .mini-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--border); }
            .mini-row:last-child { border-bottom: 0; }
            .mini-row span { color: var(--muted); font-size: .82rem; }
            .mini-row strong { font-size: .95rem; }
            @media (max-width: 1023px) {
                .hero { grid-template-columns: 1fr; }
                .content-grid { grid-template-columns: 1fr; }
                .stats { grid-template-columns: 1fr 1fr; }
            }
            @media (max-width: 767px) {
                .container { padding: 20px 14px 40px; }
                .topbar { align-items: stretch; }
                .stats { grid-template-columns: 1fr; }
                .hero-main, .hero-side, .card, .stat { padding: 20px; }
                .hero-main h1 { font-size: clamp(1.8rem, 11vw, 2.4rem); }
                .topbar-actions, .hero-actions { width: 100%; }
                .topbar-actions a, .hero-actions a { flex: 1 1 calc(50% - 5px); min-width: 0; }
                .side-item strong, .stat strong { font-size: 1.2rem; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <header class="topbar">
                <a href="{{ route('tenant.home') }}" class="brand">
                    <span class="brand-mark"><i class="fas fa-building"></i></span>
                    <span class="brand-copy">
                        <strong>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</strong>
                        <span>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }} Workspace</span>
                    </span>
                </a>

                <div class="topbar-actions">
                    <a class="btn-secondary" href="{{ url('/login') }}">Login</a>
                    <a class="btn" href="{{ route('tenant.dashboard') }}">Dashboard</a>
                </div>
            </header>

            <section class="hero">
                <div class="hero-main">
                    <span class="hero-badge"><i class="fas fa-layer-group"></i> {{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</span>
                    <h1>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</h1>
                    <p>{{ $setting?->description ?? 'Landing page tenant belum dikustomisasi.' }}</p>

                    <div class="hero-actions">
                        <a class="btn" href="{{ route('tenant.dashboard') }}">Masuk Dashboard</a>
                        <a class="btn-secondary" href="{{ url('/login') }}">Login Workspace</a>
                    </div>
                </div>

                <aside class="hero-side">
                    <div class="side-item">
                        <span>Tenant ID</span>
                        <strong>{{ tenant('id') }}</strong>
                    </div>
                    <div class="side-item">
                        <span>Theme</span>
                        <strong>{{ $setting?->theme_color ?? '#000000' }}</strong>
                    </div>
                    <div class="side-item">
                        <span>Mode</span>
                        <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</strong>
                    </div>
                </aside>
            </section>

            <section class="stats">
                <div class="stat">
                    <span class="stat-label">Workspace</span>
                    <strong>Ready</strong>
                </div>
                <div class="stat">
                    <span class="stat-label">Theme</span>
                    <strong>{{ $setting?->theme_color ?? '#000000' }}</strong>
                </div>
                <div class="stat">
                    <span class="stat-label">Brand</span>
                    <strong>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</strong>
                </div>
                <div class="stat">
                    <span class="stat-label">Mode</span>
                    <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</strong>
                </div>
            </section>

            <section class="content-grid">
                <div class="card">
                    <h2>Workspace Highlights</h2>
                    <p>Landing tenant sekarang mengikuti identitas brand dan mode SaaS yang aktif di workspace ini.</p>

                    <div class="feature-list">
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>Brand tenant tampil konsisten</span></div>
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>Warna tema sinkron dengan dashboard</span></div>
                        <div class="feature-item"><i class="fas fa-check-circle"></i><span>Mode SaaS aktif ditampilkan langsung</span></div>
                    </div>
                </div>

                <div class="card">
                    <h2>Workspace Info</h2>
                    <p>Ringkasan cepat tenant aktif.</p>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Brand</span>
                            <strong>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Tenant</span>
                            <strong>{{ tenant('id') }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Mode</span>
                            <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Theme</span>
                            <strong>{{ $setting?->theme_color ?? '#000000' }}</strong>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </body>
</html>
