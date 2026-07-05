@php
    $brand = $tenantSetting->brand_name ?? tenant('name') ?? tenant('id');
    $items = [
        [
            'label' => 'Dashboard',
            'route' => route('tenant.dashboard'),
            'active' => request()->routeIs('tenant.dashboard'),
            'icon' => 'fa-home',
        ],
        [
            'label' => 'Pengguna',
            'route' => route('tenant.users.index'),
            'active' => request()->routeIs('tenant.users.*'),
            'icon' => 'fa-users',
        ],
        [
            'label' => 'Pengaturan Web',
            'route' => route('tenant.settings'),
            'active' => request()->routeIs('tenant.settings') || request()->routeIs('tenant.settings.update'),
            'icon' => 'fa-cog',
        ],
        [
            'label' => 'Landing',
            'route' => route('tenant.home'),
            'active' => request()->routeIs('tenant.home'),
            'icon' => 'fa-globe',
        ],
    ];
@endphp

<div class="sidebar-panel">
    <div>
        <a href="{{ route('tenant.dashboard') }}" class="sidebar-brand">
            <span class="brand-mark"><i class="fas fa-chart-line"></i></span>
            <span class="brand-copy">
                <strong>{{ $brand }}</strong>
                <span>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</span>
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
            <span class="user-avatar">{{ strtoupper(substr((string) $brand, 0, 2)) }}</span>
            <span class="user-copy">
                <strong title="{{ $brand }}">{{ $brand }}</strong>
                <span>Tenant Workspace</span>
            </span>
        </div>
    </div>
</div>
