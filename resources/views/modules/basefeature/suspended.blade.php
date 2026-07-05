<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $tenantSetting->brand_name ?? $tenant->name ?? $tenant->id }} - Akses Ditangguhkan</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary: {{ $tenantSetting->theme_color ?? '#111827' }};
                --bg: #f8fafc;
                --text: #0f172a;
                --muted: #64748b;
                --border: #e2e8f0;
                --danger-bg: #fef2f2;
                --danger-text: #b91c1c;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: 'Plus Jakarta Sans', sans-serif;
                background:
                    radial-gradient(circle at top right, color-mix(in srgb, var(--primary) 12%, transparent), transparent 30%),
                    linear-gradient(180deg, #ffffff 0%, var(--bg) 100%);
                color: var(--text);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }

            .shell {
                width: min(100%, 760px);
                display: grid;
                gap: 18px;
            }

            .brand {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                padding: 12px 14px;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.82);
                border: 1px solid rgba(226, 232, 240, 0.92);
                box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
                width: fit-content;
            }

            .brand-mark {
                width: 42px;
                height: 42px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 14px;
                background: var(--primary);
                color: #ffffff;
                font-size: 1rem;
                font-weight: 800;
            }

            .brand-copy strong,
            .brand-copy span {
                display: block;
            }

            .brand-copy strong {
                font-size: 0.98rem;
            }

            .brand-copy span {
                margin-top: 2px;
                font-size: 0.78rem;
                color: var(--muted);
            }

            .card {
                padding: 28px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.92);
                border: 1px solid rgba(226, 232, 240, 0.92);
                box-shadow: 0 28px 80px rgba(15, 23, 42, 0.1);
            }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                border-radius: 999px;
                background: var(--danger-bg);
                color: var(--danger-text);
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }

            h1 {
                margin: 18px 0 12px;
                font-size: clamp(2rem, 5vw, 3rem);
                line-height: 1.08;
            }

            p {
                margin: 0;
                color: var(--muted);
                line-height: 1.75;
                font-size: 1rem;
            }

            .meta-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-top: 24px;
            }

            .meta-card {
                padding: 16px;
                border-radius: 18px;
                background: #f8fafc;
                border: 1px solid var(--border);
            }

            .meta-card span {
                display: block;
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--muted);
            }

            .meta-card strong {
                display: block;
                margin-top: 8px;
                font-size: 1rem;
                line-height: 1.45;
            }

            .footnote {
                margin-top: 22px;
                padding-top: 18px;
                border-top: 1px solid var(--border);
                font-size: 0.92rem;
            }

            @media (max-width: 767px) {
                body {
                    padding: 16px;
                }

                .card {
                    padding: 22px;
                    border-radius: 24px;
                }

                .meta-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <div class="brand">
                <span class="brand-mark">{{ strtoupper(substr((string) ($tenantSetting->brand_name ?? $tenant->id ?? 'AC'), 0, 2)) }}</span>
                <span class="brand-copy">
                    <strong>{{ $tenantSetting->brand_name ?? $tenant->name ?? $tenant->id }}</strong>
                    <span>{{ ucfirst((string) ($tenant->saas_type ?? 'universal')) }} Workspace</span>
                </span>
            </div>

            <section class="card">
                <span class="badge">{{ data_get($blockMeta ?? [], 'label', 'Akses Tenant Ditangguhkan') }}</span>
                <h1>Workspace ini sedang dinonaktifkan sementara.</h1>
                <p>
                    Tenant <strong>{{ $tenant->id }}</strong> saat ini tidak bisa diakses.
                    {{ data_get($blockMeta ?? [], 'message', 'Akses landing, login, dan dashboard ditahan sementara sampai diaktifkan lagi.') }}
                </p>

                <div class="meta-grid">
                    <div class="meta-card">
                        <span>Tenant ID</span>
                        <strong>{{ $tenant->id }}</strong>
                    </div>
                    <div class="meta-card">
                        <span>Mode</span>
                        <strong>{{ ucfirst((string) ($tenant->saas_type ?? 'universal')) }}</strong>
                    </div>
                    <div class="meta-card">
                        <span>Status</span>
                        <strong>{{ data_get($blockMeta ?? [], 'label', 'Suspended') }}</strong>
                    </div>
                    <div class="meta-card">
                        <span>Invoice</span>
                        <strong>{{ data_get($blockMeta ?? [], 'invoice_number', '-') ?: '-' }}</strong>
                    </div>
                </div>

                <p class="footnote">
                    Kalau ini tidak seharusnya terjadi, hubungi administrator platform untuk memperpanjang subscription, menyelesaikan billing,
                    atau mengaktifkan tenant ini kembali dari panel pusat.
                </p>
            </section>
        </div>
    </body>
</html>
