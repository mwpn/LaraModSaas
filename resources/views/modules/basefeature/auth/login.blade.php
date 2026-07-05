<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $tenantSetting->brand_name ?? tenant('name') ?? tenant('id') }} - Login</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <style>
            :root {
                --primary: {{ $tenantSetting->theme_color ?? '#2563eb' }};
                --bg: #f9fafb;
                --panel: rgba(255, 255, 255, 0.9);
                --border: #e5e7eb;
                --text: #111827;
                --muted: #6b7280;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: var(--text);
                background:
                    radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 12%, transparent), transparent 28%),
                    radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.04), transparent 18%),
                    var(--bg);
            }
            .shell { min-height: 100vh; display: grid; grid-template-columns: 1.05fr .95fr; }
            .panel { display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
            .form-card {
                width: 100%; max-width: 460px; padding: 28px; border: 1px solid rgba(255,255,255,.6); border-radius: 24px;
                background: var(--panel); box-shadow: 0 20px 50px rgba(15, 23, 42, .08); backdrop-filter: blur(18px);
                transition: transform .18s ease, box-shadow .18s ease;
            }
            .form-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 28px 60px rgba(15, 23, 42, .12);
            }
            .brand { display: inline-flex; align-items: center; gap: 12px; }
            .brand-mark {
                width: 42px; height: 42px; border-radius: 12px; background: var(--primary); color: #fff;
                display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 12px 24px color-mix(in srgb, var(--primary) 24%, transparent);
            }
            .brand-copy strong { display: block; font-size: 1rem; }
            .brand-copy span { display: block; margin-top: 2px; font-size: .75rem; color: var(--muted); }
            h1 { margin: 24px 0 10px; font-size: 2.1rem; line-height: 1.1; }
            .lead { margin: 0; color: var(--muted); line-height: 1.7; }
            .form-stack { display: grid; gap: 18px; margin-top: 24px; }
            .field-label { display: block; margin-bottom: 8px; font-size: .875rem; font-weight: 600; }
            input[type="email"], input[type="password"] {
                width: 100%; min-height: 48px; padding: 10px 14px; border-radius: 14px; border: 1px solid #d1d5db; background: #fff; outline: none;
                transition: border-color .18s ease, box-shadow .18s ease;
            }
            input[type="email"]:focus, input[type="password"]:focus {
                border-color: var(--primary); box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 12%, transparent);
            }
            .form-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .remember { display: inline-flex; align-items: center; gap: 10px; font-size: .875rem; color: var(--muted); }
            .btn {
                display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 48px;
                padding: 0 16px; border: 0; border-radius: 14px; background: var(--primary); color: #fff; font-weight: 700; cursor: pointer;
                box-shadow: 0 18px 30px color-mix(in srgb, var(--primary) 24%, transparent);
                transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
            }
            .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 24px 36px color-mix(in srgb, var(--primary) 28%, transparent);
                filter: brightness(1.03);
            }
            .alert { margin-top: 18px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--border); font-size: .875rem; }
            .alert-danger { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
            .alert-danger ul { margin: 0; padding-left: 18px; }
            .aside {
                position: relative; overflow: hidden; padding: 40px;
                background: linear-gradient(135deg, #0f172a 0%, #111827 45%, color-mix(in srgb, var(--primary) 45%, #0f172a) 100%); color: #fff;
            }
            .aside::before {
                content: ""; position: absolute; inset: 0;
                background: radial-gradient(circle at top left, rgba(255,255,255,.12), transparent 28%), radial-gradient(circle at bottom right, color-mix(in srgb, var(--primary) 38%, transparent), transparent 30%);
            }
            .aside-inner { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
            .pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.12); font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
            .aside h2 { margin: 24px 0 12px; max-width: 620px; font-size: clamp(2rem, 3vw, 3rem); line-height: 1.05; }
            .aside p { max-width: 620px; color: rgba(255,255,255,.78); line-height: 1.8; }
            .info-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 16px; }
            .info-card { padding: 18px; border-radius: 18px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.1); backdrop-filter: blur(12px); transition: transform .18s ease, background .18s ease; }
            .info-card:hover { transform: translateY(-1px); background: rgba(255,255,255,.1); }
            .info-card span { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: rgba(255,255,255,.62); }
            .info-card strong { display: block; margin-top: 8px; font-size: 1rem; }
            .back-link { color: var(--muted); font-size: .875rem; }
            @media (max-width: 1023px) {
                .shell { grid-template-columns: 1fr; }
                .aside { min-height: 300px; }
            }
            @media (max-width: 640px) {
                .panel, .aside { padding: 24px 16px; }
                .form-card { padding: 22px; border-radius: 20px; }
                .info-grid { grid-template-columns: 1fr; }
                .aside { min-height: auto; }
                .aside-inner { gap: 28px; }
                h1 { font-size: 1.8rem; }
                .lead { font-size: 0.9375rem; }
                .form-row { flex-wrap: wrap; align-items: flex-start; }
                .brand-copy strong { font-size: 0.95rem; }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <section class="panel">
                <div class="form-card">
                    <a href="{{ route('tenant.home') }}" class="brand">
                        <span class="brand-mark"><i class="fas fa-chart-line"></i></span>
                        <span class="brand-copy">
                            <strong>{{ $tenantSetting->brand_name ?? tenant('name') ?? tenant('id') }}</strong>
                            <span>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }} Workspace</span>
                        </span>
                    </a>

                    <h1>Masuk ke workspace</h1>
                    <p class="lead">Akses dashboard tenant dengan brand, mode SaaS, dan warna yang tetap sinkron dengan konfigurasi tenant.</p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('tenant.login.store') }}" class="form-stack">
                        @csrf

                        <div>
                            <label for="email" class="field-label">Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="you@company.com">
                        </div>

                        <div>
                            <div class="form-row">
                                <label for="password" class="field-label" style="margin-bottom: 0;">Password</label>
                                <a href="{{ route('tenant.home') }}" class="back-link">Kembali</a>
                            </div>
                            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan password" style="margin-top: 8px;">
                        </div>

                        <label class="remember">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember')) style="accent-color: var(--primary);">
                            <span>Remember me</span>
                        </label>

                        <button type="submit" class="btn">Masuk</button>
                    </form>
                </div>
            </section>

            <aside class="aside">
                <div class="aside-inner">
                    <div>
                        <span class="pill"><i class="fas fa-building"></i> Tenant Access</span>
                        <h2>{{ $tenantSetting->brand_name ?? tenant('name') ?? tenant('id') }}</h2>
                        <p>Masuk ke area kerja tenant dengan tampilan yang lebih tenang, modern, dan tetap mengikuti identitas brand tenant.</p>
                    </div>

                    <div class="info-grid">
                        <div class="info-card">
                            <span>Theme</span>
                            <strong>{{ $tenantSetting->theme_color ?? '#000000' }}</strong>
                        </div>
                        <div class="info-card">
                            <span>Tenant</span>
                            <strong>{{ tenant('id') }}</strong>
                        </div>
                        <div class="info-card">
                            <span>Mode</span>
                            <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</strong>
                        </div>
                        <div class="info-card">
                            <span>Access</span>
                            <strong>Workspace</strong>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </body>
</html>
