@php
    $hasWarehouseRoute = \Illuminate\Support\Facades\Route::has('tenant.tirta.warehouse');
    $brand = $tenantSetting->brand_name ?? tenant('name') ?? tenant('id');
    $isTirta = tenant('saas_type') === 'tirta';
    $user = auth('tenant')->user();
    $userRoleSlug = $user && method_exists($user, 'roleSlug') ? $user->roleSlug() : null;
    $canManageUsers = $user && method_exists($user, 'canManageUsers') ? $user->canManageUsers() : false;
    $canAccessMasterData = $user && method_exists($user, 'canAccessTirtaMasterData') ? $user->canAccessTirtaMasterData() : false;
    $canAccessMeterReading = $user && method_exists($user, 'canAccessTirtaMeterReadingWorkspace') ? $user->canAccessTirtaMeterReadingWorkspace() : false;
    $canAccessBilling = $user && method_exists($user, 'canAccessTirtaBilling') ? $user->canAccessTirtaBilling() : false;
    $canAccessWarehouse = $user && method_exists($user, 'canAccessTirtaWarehouse') ? $user->canAccessTirtaWarehouse() : false;
    $jobTitleLabel = trim((string) data_get($user, 'job_title', ''));
    $userAreaLabel = trim((string) data_get($user, 'serviceArea.name', ''));
    if ($userAreaLabel === '' && data_get($user, 'serviceArea.parent')) {
        $userAreaLabel = trim((string) data_get($user, 'serviceArea.parent.name', ''));
    }
    $roleLabel = $user && method_exists($user, 'tirtaRoleLabel')
        ? $user->tirtaRoleLabel()
        : (string) (data_get($user, 'role.name') ?: 'Pengguna Tenant');
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
    $isMeterReader = $isTirta && $user && method_exists($user, 'roleSlug') && $user->roleSlug() === 'meter_reader';
    $dashboardRoute = $isMeterReader
        ? route('tenant.tirta.meter-readings')
        : ($isTirta ? route('tenant.tirta.workspace') : route('tenant.dashboard'));
    $items = $isMeterReader
        ? [
            [
                'label' => 'Catat Meter',
                'route' => route('tenant.tirta.meter-readings'),
                'active' => request()->routeIs('tenant.tirta.meter-readings')
                    || request()->routeIs('tenant.tirta.meter-readings.*')
                    || request()->routeIs('tenant.tirta.meter-reading-periods.*'),
                'icon' => 'fa-gauge-high',
            ],
            [
                'label' => 'Profil Saya',
                'route' => route('tenant.profile.edit'),
                'active' => request()->routeIs('tenant.profile.*'),
                'icon' => 'fa-id-badge',
            ],
            [
                'label' => 'Landing',
                'route' => route('tenant.home'),
                'active' => request()->routeIs('tenant.home'),
                'icon' => 'fa-globe',
            ],
        ]
        : [
            [
                'label' => $isTirta ? 'Workspace Tirta' : 'Dashboard',
                'route' => $dashboardRoute,
                'active' => request()->routeIs('tenant.dashboard') || request()->routeIs('tenant.tirta.workspace'),
                'icon' => $isTirta ? 'fa-water' : 'fa-home',
            ],
            [
                'label' => 'Profil Saya',
                'route' => route('tenant.profile.edit'),
                'active' => request()->routeIs('tenant.profile.*'),
                'icon' => 'fa-id-badge',
            ],
            [
                'label' => 'Landing',
                'route' => route('tenant.home'),
                'active' => request()->routeIs('tenant.home'),
                'icon' => 'fa-globe',
            ],
        ];

    if ($isTirta && ! $isMeterReader) {
        $tirtaItems = [];

        if ($canAccessMasterData) {
            $tirtaItems[] = [
                'label' => 'Master Tirta',
                'route' => route('tenant.tirta.master-data'),
                'active' => request()->routeIs('tenant.tirta.master-data')
                    || request()->routeIs('tenant.tirta.service-areas.*')
                    || request()->routeIs('tenant.tirta.service-categories.*')
                    || request()->routeIs('tenant.tirta.customers.*')
                    || request()->routeIs('tenant.tirta.connections.*')
                    || request()->routeIs('tenant.tirta.tariffs.*'),
                'icon' => 'fa-table-cells-large',
            ];
        }

        if ($canAccessMeterReading) {
            $tirtaItems[] = [
                'label' => 'Catat Meter',
                'route' => route('tenant.tirta.meter-readings'),
                'active' => request()->routeIs('tenant.tirta.meter-readings')
                    || request()->routeIs('tenant.tirta.meter-readings.*')
                    || request()->routeIs('tenant.tirta.meter-reading-periods.*'),
                'icon' => 'fa-gauge-high',
            ];

            $tirtaItems[] = [
                'label' => 'Verifikator',
                'route' => route('tenant.tirta.meter-verification'),
                'active' => request()->routeIs('tenant.tirta.meter-verification'),
                'icon' => 'fa-clipboard-check',
            ];
        }

        if ($canAccessBilling) {
            $tirtaItems[] = [
                'label' => 'Billing',
                'route' => route('tenant.tirta.billing'),
                'active' => request()->routeIs('tenant.tirta.billing')
                    || request()->routeIs('tenant.tirta.billing.*')
                    || request()->routeIs('tenant.tirta.billing-periods.*')
                    || request()->routeIs('tenant.tirta.billing-invoices.*'),
                'icon' => 'fa-file-invoice-dollar',
            ];
        }

        if ($hasWarehouseRoute && $canAccessWarehouse) {
            $tirtaItems[] = [
                'label' => 'Warehouse',
                'route' => route('tenant.tirta.warehouse'),
                'active' => request()->routeIs('tenant.tirta.warehouse')
                    || request()->routeIs('tenant.tirta.warehouse.*'),
                'icon' => 'fa-warehouse',
            ];
        }

        if ($tirtaItems !== []) {
            array_splice($items, 1, 0, $tirtaItems);
        }
    }

    if (! $isMeterReader && $canManageUsers) {
        array_splice($items, 2, 0, [[
            'label' => 'Pengguna',
            'route' => route('tenant.users.index'),
            'active' => request()->routeIs('tenant.users.*'),
            'icon' => 'fa-users',
        ]]);
    }

    if (! $isMeterReader && $canManageUsers) {
        array_splice($items, count($items) - 1, 0, [[
            'label' => 'Pengaturan Web',
            'route' => route('tenant.settings'),
            'active' => request()->routeIs('tenant.settings') || request()->routeIs('tenant.settings.update'),
            'icon' => 'fa-cog',
        ]]);
    }
@endphp

<div class="sidebar-panel">
    <div>
        <a href="{{ $dashboardRoute }}" class="sidebar-brand">
            <span class="brand-mark"><i class="fas fa-chart-line"></i></span>
            <span class="brand-copy">
                <strong>{{ $brand }}</strong>
                <span>
                    {{ $jobTitleLabel !== '' ? $jobTitleLabel : ($isMeterReader ? 'Petugas Catat Meter' : ($isTirta ? $roleLabel : ucfirst((string) (tenant('saas_type') ?? 'universal')))) }}
                </span>
            </span>
        </a>

        <nav class="sidebar-nav">
            @foreach ($items as $item)
                <a href="{{ $item['route'] }}" class="sidebar-item{{ $item['active'] ? ' active' : '' }}">
                    <i class="fas {{ $item['icon'] }}"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span class="user-avatar">{{ $user && method_exists($user, 'profileInitials') ? $user->profileInitials() : strtoupper(substr((string) $brand, 0, 2)) }}</span>
            <span class="user-copy">
                <strong title="{{ $user?->name ?? $brand }}">{{ $user?->name ?? $brand }}</strong>
                <span>{{ $jobTitleLabel !== '' ? $jobTitleLabel.' · ' : '' }}{{ $userAreaLabel !== '' ? $roleLabel.' · '.$userAreaLabel : $roleLabel }}</span>
            </span>
        </div>
    </div>
</div>
