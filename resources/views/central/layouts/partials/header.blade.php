@php
    $pageHeading = trim($__env->yieldContent('page_title', 'Central Admin'));
    $pageSubtitle = trim($__env->yieldContent('page_subtitle', ''));
    $user = auth('central')->user();
    $avatarUrl = $user?->avatarUrl();
    $initials = $user?->profileInitials() ?? 'CA';
    $displayName = $user?->name ?: $user?->email ?: 'Central User';
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
                <i class="fas fa-shield-halved"></i>
                <strong>Central Guard</strong>
                <span>Super Admin</span>
            </div>

            <button class="icon-button" type="button" aria-label="Notifications">
                <i class="fas fa-bell"></i>
            </button>

            <div x-data="{ open: false }" style="position: relative;">
                <button type="button" class="user-trigger" @click="open = !open">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="user-avatar" style="object-fit: cover;">
                    @else
                        <span class="user-avatar">{{ $initials }}</span>
                    @endif
                    <span class="user-copy">
                        <strong title="{{ $displayName }}">{{ $displayName }}</strong>
                        <span>{{ ucfirst((string) ($user?->roleSlug() ?? 'staff')) }}</span>
                    </span>
                    <i class="fas fa-chevron-down" style="font-size: 12px; color: var(--muted-soft);"></i>
                </button>

                <div x-show="open" @click.away="open = false" x-transition class="dropdown-menu">
                    <a class="dropdown-link" href="{{ route('central.super-admin.profile.edit') }}">
                        <i class="fas fa-id-badge"></i>
                        Profil Saya
                    </a>
                    @if ($user?->canAccessCentral('tenants.view'))
                        <a class="dropdown-link" href="{{ route('central.super-admin.tenants.index') }}">
                            <i class="fas fa-users"></i>
                            Tenant Panel
                        </a>
                    @endif
                    @if ($user?->canAccessCentral('settings.view'))
                        <a class="dropdown-link" href="{{ route('central.super-admin.settings.edit') }}">
                            <i class="fas fa-cog"></i>
                            System Settings
                        </a>
                    @endif
                    @if ($user?->canAccessCentral('packages.view'))
                        <a class="dropdown-link" href="{{ route('central.super-admin.packages.index') }}">
                            <i class="fas fa-wallet"></i>
                            Package Settings
                        </a>
                    @endif
                    @if ($user?->canAccessCentral('users.view'))
                        <a class="dropdown-link" href="{{ route('central.super-admin.users.index') }}">
                            <i class="fas fa-user-shield"></i>
                            Pengguna
                        </a>
                    @endif
                    @if ($user?->canAccessCentral('billing.view'))
                        <a class="dropdown-link" href="{{ route('central.super-admin.billing.index') }}">
                            <i class="fas fa-file-invoice-dollar"></i>
                            Billing Dashboard
                        </a>
                    @endif
                    <form method="POST" action="{{ route('central.logout') }}">
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
