@php
    $pageHeading = trim($__env->yieldContent('page_title', 'Dashboard'));
    $pageSubtitle = trim($__env->yieldContent('page_subtitle', ''));
    $brand = $tenantSetting->brand_name ?? tenant('name') ?? tenant('id');
    $initials = strtoupper(substr((string) $brand, 0, 2));
@endphp

<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <button class="topbar-toggle" type="button" data-sidebar-toggle aria-label="Open sidebar">
                <i class="fas fa-bars"></i>
            </button>

            <div class="page-copy">
                <h1>{{ $pageHeading }}</h1>
                @if ($pageSubtitle !== '')
                    <p>{{ $pageSubtitle }}</p>
                @endif
            </div>
        </div>

        <div class="topbar-right">
            <div class="context-pill" aria-label="Workspace context">
                <i class="fas fa-layer-group"></i>
                <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</strong>
                <span>Tenant Mode</span>
            </div>

            <button class="icon-button" type="button" aria-label="Notifications">
                <i class="fas fa-bell"></i>
            </button>

            <div x-data="{ open: false }" style="position: relative;">
                <button type="button" class="user-trigger" @click="open = !open">
                    <span class="user-avatar">{{ $initials }}</span>
                    <span class="user-copy">
                        <strong title="{{ $brand }}">{{ $brand }}</strong>
                        <span>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</span>
                    </span>
                    <i class="fas fa-chevron-down" style="font-size: 12px; color: var(--muted-soft);"></i>
                </button>

                <div x-show="open" @click.away="open = false" x-transition class="dropdown-menu">
                    <a class="dropdown-link" href="{{ route('tenant.dashboard') }}">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a class="dropdown-link" href="{{ route('tenant.settings') }}">
                        <i class="fas fa-cog"></i>
                        Pengaturan Web
                    </a>
                    <a class="dropdown-link" href="{{ route('tenant.users.index') }}">
                        <i class="fas fa-users"></i>
                        Pengguna
                    </a>
                    <form method="POST" action="{{ route('tenant.logout') }}">
                        @csrf
                        <button class="dropdown-link dropdown-link-danger" type="submit">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
