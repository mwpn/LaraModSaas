@php
    $pageHeading = trim($__env->yieldContent('page_title', 'Dashboard'));
    $pageSubtitle = trim($__env->yieldContent('page_subtitle', ''));
    $brand = $tenantSetting->brand_name ?? tenant('name') ?? tenant('id');
    $isTirta = tenant('saas_type') === 'tirta';
    $user = auth('tenant')->user();
    $initials = $user && method_exists($user, 'profileInitials')
        ? $user->profileInitials()
        : strtoupper(substr((string) $brand, 0, 2));
    $userRoleSlug = $user && method_exists($user, 'roleSlug') ? $user->roleSlug() : null;
    $roleLabel = match ($userRoleSlug) {
        'owner' => 'Owner Tenant',
        'admin' => 'Admin Tenant',
        'staff' => 'Staff Operasional',
        'meter_reader' => 'Petugas Catat Meter',
        default => (string) (data_get($user, 'role.name') ?: ucfirst((string) (tenant('saas_type') ?? 'universal'))),
    };
    $roleLabelOverrides = data_get($tenantSetting ?? null, 'role_label_overrides');
    if (is_string($roleLabelOverrides) && $roleLabelOverrides !== '') {
        $decodedOverrides = json_decode($roleLabelOverrides, true);
        $roleLabelOverrides = is_array($decodedOverrides) ? $decodedOverrides : null;
    }
    if (is_array($roleLabelOverrides) && filled($userRoleSlug)) {
        $customRoleLabel = $roleLabelOverrides[$userRoleSlug] ?? null;
        if (filled($customRoleLabel)) {
            $roleLabel = trim((string) $customRoleLabel);
        }
    }
    $canManageUsers = $user && method_exists($user, 'canManageUsers') ? $user->canManageUsers() : false;
    $isMeterReader = $user && method_exists($user, 'isMeterReader') ? $user->isMeterReader() : false;
    $jobTitleLabel = trim((string) data_get($user, 'job_title', ''));
    $userAreaLabel = trim((string) data_get($user, 'serviceArea.name', ''));
    if ($userAreaLabel === '' && data_get($user, 'serviceArea.parent')) {
        $userAreaLabel = trim((string) data_get($user, 'serviceArea.parent.name', ''));
    }
    $dashboardRoute = $isMeterReader
        ? route('tenant.tirta.meter-readings')
        : ($isTirta ? route('tenant.tirta.workspace') : route('tenant.dashboard'));
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
                <strong>{{ $jobTitleLabel !== '' ? $jobTitleLabel : ($isTirta ? $roleLabel : ucfirst((string) (tenant('saas_type') ?? 'universal'))) }}</strong>
                <span>{{ $userAreaLabel !== '' ? $userAreaLabel : ($isTirta ? 'Semua Area' : 'Tenant Mode') }}</span>
            </div>

            <button class="icon-button" type="button" aria-label="Notifications">
                <i class="fas fa-bell"></i>
            </button>

            <div x-data="{ open: false }" style="position: relative;">
                <button type="button" class="user-trigger" @click="open = !open">
                    <span class="user-avatar">{{ $initials }}</span>
                    <span class="user-copy">
                        <strong title="{{ $user?->name ?? $brand }}">{{ $user?->name ?? $brand }}</strong>
                        <span>{{ $jobTitleLabel !== '' ? $jobTitleLabel.' · ' : '' }}{{ $userAreaLabel !== '' ? $roleLabel.' · '.$userAreaLabel : $roleLabel }}</span>
                    </span>
                    <i class="fas fa-chevron-down" style="font-size: 12px; color: var(--muted-soft);"></i>
                </button>

                <div x-show="open" @click.away="open = false" x-transition class="dropdown-menu">
                    <a class="dropdown-link" href="{{ $dashboardRoute }}">
                        <i class="fas {{ $isTirta ? 'fa-water' : 'fa-home' }}"></i>
                        {{ $isTirta ? 'Workspace Tirta' : 'Dashboard' }}
                    </a>
                    <a class="dropdown-link" href="{{ route('tenant.profile.edit') }}">
                        <i class="fas fa-id-badge"></i>
                        Profil Saya
                    </a>
                    @if ($isTirta && ! $isMeterReader)
                        <a class="dropdown-link" href="{{ route('tenant.tirta.master-data') }}">
                            <i class="fas fa-table-cells-large"></i>
                            Master Tirta
                        </a>
                    @endif
                    @if ($canManageUsers)
                        <a class="dropdown-link" href="{{ route('tenant.users.index') }}">
                            <i class="fas fa-users"></i>
                            Pengguna
                        </a>
                        <a class="dropdown-link" href="{{ route('tenant.settings') }}">
                            <i class="fas fa-cog"></i>
                            Pengaturan Web
                        </a>
                    @endif
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
