<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $tenantSetting->brand_name ?? tenant('name') ?? tenant('id') }} - @yield('page_title', 'Dashboard')</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <style>
            :root {
                --primary: {{ $tenantSetting->theme_color ?? '#2563eb' }};
                --body-bg: #f9fafb;
                --panel: #ffffff;
                --border: #e5e7eb;
                --text: #111827;
                --muted: #6b7280;
                --muted-soft: #9ca3af;
                --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.06);
                --shadow-md: 0 10px 30px rgba(15, 23, 42, 0.06);
            }

            * { box-sizing: border-box; }
            html, body { margin: 0; min-height: 100%; }
            body {
                font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background:
                    radial-gradient(circle at top left, color-mix(in srgb, var(--primary) 10%, transparent), transparent 22%),
                    radial-gradient(circle at bottom right, rgba(15, 23, 42, 0.04), transparent 18%),
                    var(--body-bg);
                color: var(--text);
            }

            a { color: inherit; text-decoration: none; }
            button, input, select, textarea { font: inherit; }
            .app-shell { min-height: 100vh; background: var(--body-bg); }
            .sidebar-shell { position: fixed; inset: 0 auto 0 0; width: 256px; background: #ffffff; border-right: 1px solid var(--border); overflow-y: auto; z-index: 50; transition: transform 0.28s ease; }
            .shell-backdrop { position: fixed; inset: 0; background: rgba(17, 24, 39, 0.48); opacity: 0; pointer-events: none; transition: opacity 0.2s ease; z-index: 45; }
            .content-shell { min-height: 100vh; margin-left: 256px; display: flex; flex-direction: column; }
            .topbar { position: sticky; top: 0; z-index: 30; background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); }
            .topbar-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 24px; }
            .topbar-left, .topbar-right { display: flex; align-items: center; gap: 12px; min-width: 0; }
            .topbar-toggle, .icon-button { width: 40px; height: 40px; border: 1px solid var(--border); background: #ffffff; border-radius: 10px; color: var(--muted); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: border-color 0.18s ease, color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease; }
            .topbar-toggle { display: none; }
            .topbar-toggle:hover, .icon-button:hover { color: var(--primary); border-color: color-mix(in srgb, var(--primary) 20%, var(--border)); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
            .page-copy h1 { margin: 0; font-size: 1.125rem; line-height: 1.4; font-weight: 700; color: var(--text); }
            .page-copy p { margin: 2px 0 0; font-size: 0.875rem; color: var(--muted); }
            .context-pill { display: inline-flex; align-items: center; gap: 10px; min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--border); background: rgba(255, 255, 255, 0.92); color: var(--muted); box-shadow: var(--shadow-sm); }
            .context-pill i { color: var(--primary); }
            .context-pill strong { color: var(--text); font-size: 0.8125rem; font-weight: 700; line-height: 1; }
            .context-pill span { font-size: 0.75rem; line-height: 1; }
            .user-trigger { display: inline-flex; align-items: center; gap: 10px; height: 40px; max-width: min(100%, 280px); padding: 0 10px; border: 1px solid var(--border); border-radius: 10px; background: #ffffff; cursor: pointer; transition: border-color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease; }
            .user-trigger:hover { border-color: color-mix(in srgb, var(--primary) 20%, var(--border)); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
            .user-avatar { width: 32px; height: 32px; flex: 0 0 auto; border-radius: 999px; background: color-mix(in srgb, var(--primary) 12%, #ffffff); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; }
            .user-copy { min-width: 0; flex: 1 1 auto; text-align: left; }
            .user-copy strong { display: block; font-size: 0.875rem; color: var(--text); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .user-copy span { display: block; margin-top: 2px; font-size: 0.75rem; color: var(--muted); line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); width: 220px; padding: 8px; background: #ffffff; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-md); }
            .dropdown-link { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 12px; border: 0; border-radius: 10px; background: transparent; color: var(--text); text-align: left; cursor: pointer; }
            .dropdown-link:hover { background: #f3f4f6; }
            .dropdown-link-danger { color: #dc2626; }
            .page-shell { flex: 1; padding: 16px; }
            .page-container { width: 100%; max-width: 1400px; margin: 0 auto; }
            .page-grid { display: grid; gap: 24px; }
            .hero-card { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 24px; border-radius: 16px; background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 88%, #0f172a) 0%, var(--primary) 100%); color: #ffffff; box-shadow: var(--shadow-md); position: relative; overflow: hidden; }
            .hero-card::after { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.16), transparent 28%), linear-gradient(180deg, rgba(255,255,255,0.06), transparent 40%); pointer-events: none; }
            .hero-card h2 { margin: 0; font-size: 1.75rem; line-height: 1.2; font-weight: 800; }
            .hero-card p { margin: 8px 0 0; color: rgba(255, 255, 255, 0.82); max-width: 700px; line-height: 1.6; }
            .hero-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.14); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }
            .hero-meta { display: grid; gap: 12px; min-width: 210px; }
            .hero-meta-card { padding: 14px 16px; border-radius: 12px; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.14); }
            .hero-meta-card span { display: block; font-size: 0.75rem; color: rgba(255,255,255,0.76); text-transform: uppercase; letter-spacing: 0.08em; }
            .hero-meta-card strong { display: block; margin-top: 6px; font-size: 1.125rem; font-weight: 700; }
            .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
            .stat-card, .dashboard-card { background: #ffffff; border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-sm); transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease; }
            .stat-card { padding: 20px; transition: transform 0.2s ease, box-shadow 0.2s ease; }
            .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
            .stat-inner { display: flex; align-items: center; gap: 14px; }
            .stat-icon { width: 48px; height: 48px; border-radius: 12px; background: color-mix(in srgb, var(--primary) 12%, #ffffff); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.125rem; flex: none; }
            .stat-copy p { margin: 0; color: var(--muted); font-size: 0.875rem; font-weight: 500; }
            .stat-copy strong { display: block; margin-top: 4px; font-size: 1.75rem; line-height: 1.15; color: var(--text); }
            .stat-copy span { display: block; margin-top: 6px; color: var(--muted); font-size: 0.8125rem; }
            .content-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr); gap: 24px; }
            .side-stack { display: grid; gap: 24px; align-content: start; }
            .dashboard-card { padding: 24px; }
            .dashboard-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); border-color: color-mix(in srgb, var(--primary) 12%, var(--border)); }
            .card-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
            .card-title { margin: 0; font-size: 1.125rem; line-height: 1.4; font-weight: 700; }
            .card-subtitle { margin: 4px 0 0; font-size: 0.875rem; color: var(--muted); }
            .table-responsive { overflow-x: auto; }
            table { width: 100%; border-collapse: collapse; }
            th { padding: 12px 16px; background: #f9fafb; color: var(--muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; text-align: left; border-bottom: 1px solid var(--border); }
            td { padding: 14px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); font-size: 0.875rem; }
            tbody tr:hover { background: #f9fafb; }
            .status-active, .status-pending, .status-muted { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
            .status-active { background: #dcfce7; color: #166534; }
            .status-pending { background: #dbeafe; color: #1d4ed8; }
            .status-muted { background: #f3f4f6; color: #374151; }
            .quick-grid { display: grid; gap: 12px; }
            .quick-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 12px; background: #ffffff; transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease; }
            .quick-item:hover { transform: translateY(-1px); border-color: color-mix(in srgb, var(--primary) 18%, var(--border)); box-shadow: var(--shadow-sm); }
            .quick-item strong { display: block; font-size: 0.9375rem; color: var(--text); }
            .quick-item span { display: block; margin-top: 4px; font-size: 0.8125rem; color: var(--muted); }
            .mini-list { display: grid; gap: 12px; }
            .mini-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
            .mini-row:last-child { border-bottom: 0; }
            .mini-row span { font-size: 0.8125rem; color: var(--muted); }
            .mini-row strong { font-size: 0.9375rem; color: var(--text); }
            .inline-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            .form-stack { display: grid; gap: 16px; }
            .form-block { padding: 18px; border: 1px solid var(--border); border-radius: 14px; background: #ffffff; }
            .field-label { display: block; margin-bottom: 8px; font-size: 0.875rem; font-weight: 600; color: var(--text); }
            .field, select, textarea { width: 100%; min-height: 44px; padding: 10px 14px; border-radius: 10px; border: 1px solid #d1d5db; background: #ffffff; color: var(--text); outline: none; }
            .field:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary) 14%, transparent); }
            .tenant-btn, .tenant-btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 9px 14px; border-radius: 10px; border: 1px solid transparent; cursor: pointer; font-weight: 600; transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, filter 0.18s ease; }
            .tenant-btn { background: var(--primary); border-color: var(--primary); color: #ffffff; }
            .tenant-btn-secondary { background: #ffffff; border-color: var(--border); color: var(--text); }
            .tenant-btn:hover, .tenant-btn-secondary:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }
            .tenant-btn:hover { filter: brightness(1.03); }
            .tenant-btn-secondary:hover { border-color: color-mix(in srgb, var(--primary) 18%, var(--border)); }
            .alert { padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border); font-size: 0.875rem; line-height: 1.6; }
            .alert-success { background: #ecfdf5; border-color: #bbf7d0; color: #166534; }
            .alert-danger { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
            .sidebar-panel { min-height: 100%; display: flex; flex-direction: column; padding: 20px 12px 16px; }
            .sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 12px 12px; }
            .brand-mark { width: 36px; height: 36px; border-radius: 10px; background: var(--primary); color: #ffffff; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 24%, transparent); }
            .brand-copy strong { display: block; font-size: 1rem; line-height: 1.2; }
            .brand-copy span { display: block; margin-top: 2px; font-size: 0.75rem; color: var(--muted); }
            .sidebar-nav { display: grid; gap: 4px; margin-top: 18px; }
            .sidebar-item { display: flex; align-items: center; gap: 12px; padding: 11px 12px; border-radius: 10px; font-size: 0.9375rem; font-weight: 600; color: #374151; transition: background 0.2s ease, color 0.2s ease, transform 0.18s ease; }
            .sidebar-item i { width: 18px; text-align: center; color: #9ca3af; }
            .sidebar-item:hover { background: #f3f4f6; transform: translateX(2px); }
            .sidebar-item.active { background: color-mix(in srgb, var(--primary) 10%, #ffffff); color: var(--primary); box-shadow: inset 3px 0 0 var(--primary); }
            .sidebar-item.active i { color: var(--primary); }
            .sidebar-footer { margin-top: auto; padding: 16px 12px 0; border-top: 1px solid var(--border); }
            .sidebar-user { display: flex; align-items: center; gap: 12px; min-width: 0; padding: 10px 12px; border-radius: 12px; background: #f9fafb; border: 1px solid var(--border); }
            .footer-shell { padding: 0 16px 16px; }
            .footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 8px 0; color: var(--muted); font-size: 0.8125rem; }
            .muted { color: var(--muted); }
            @media (max-width: 1279px) { .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (max-width: 1023px) {
                .sidebar-shell { transform: translateX(-100%); box-shadow: var(--shadow-md); width: min(280px, 86vw); }
                body[data-sidebar-open="true"] .sidebar-shell { transform: translateX(0); }
                body[data-sidebar-open="true"] .shell-backdrop { opacity: 1; pointer-events: auto; }
                .content-shell { margin-left: 0; }
                .topbar-toggle { display: inline-flex; }
                .content-grid { grid-template-columns: 1fr; }
            }
            @media (max-width: 767px) {
                .topbar-inner { padding: 12px 16px; gap: 10px; }
                .page-shell, .footer-shell { padding-left: 12px; padding-right: 12px; }
                .context-pill, .topbar-right .icon-button { display: none; }
                .topbar-left { min-width: 0; flex: 1; }
                .topbar-right { margin-left: auto; }
                .page-copy h1 { font-size: 1rem; line-height: 1.3; }
                .page-copy p { display: none; }
                .user-trigger { padding: 0 8px; }
                .user-copy, .user-trigger .fa-chevron-down { display: none; }
                .hero-card { padding: 20px; flex-direction: column; align-items: flex-start; }
                .hero-card h2 { font-size: 1.5rem; }
                .hero-meta { width: 100%; grid-template-columns: 1fr; }
                .stat-grid { grid-template-columns: 1fr; }
                .dashboard-card, .stat-card { padding: 18px; }
                .card-head, .mini-row { flex-direction: column; align-items: flex-start; }
                .card-head > * { width: 100%; }
                .inline-actions { width: 100%; }
                .inline-actions > * { flex: 1 1 calc(50% - 5px); }
                .footer-inner { flex-direction: column; align-items: flex-start; }
            }
        </style>
        @stack('styles')
        @include('shared.confirm-modal-styles')
    </head>
    <body data-sidebar-open="false">
        <div class="app-shell">
            <aside class="sidebar-shell">
                @include('basefeature::layouts.partials.sidebar')
            </aside>

            <div class="shell-backdrop" data-sidebar-close></div>

            <div class="content-shell">
                @include('basefeature::layouts.partials.header')

                <main class="page-shell">
                    <div class="page-container">
                        @yield('content')
                    </div>
                </main>

                @include('basefeature::layouts.partials.footer')
            </div>
        </div>

        @include('shared.confirm-modal')

        <script>
            (() => {
                const body = document.body;
                const closeSidebar = () => body.setAttribute('data-sidebar-open', 'false');
                const toggleSidebar = () => {
                    if (window.innerWidth >= 1024) {
                        return;
                    }

                    const isOpen = body.getAttribute('data-sidebar-open') === 'true';
                    body.setAttribute('data-sidebar-open', isOpen ? 'false' : 'true');
                };

                document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
                    button.addEventListener('click', toggleSidebar);
                });

                document.querySelectorAll('[data-sidebar-close]').forEach((element) => {
                    element.addEventListener('click', closeSidebar);
                });

                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) {
                        closeSidebar();
                    }
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeSidebar();
                    }
                });
            })();
        </script>
        @stack('scripts')
        @include('shared.confirm-modal-script')
    </body>
</html>
