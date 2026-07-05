@php
    $user = auth('central')->user();
    $brandName = \App\Models\CentralSetting::platformExperience()['brand_name'] ?? config('app.name', 'AirCloud');
    $avatarUrl = $user?->avatarUrl();
    $initials = $user?->profileInitials() ?? 'CA';
    $displayName = $user?->name ?: $user?->email ?: 'Central User';
    $items = [
        [
            'label' => 'Profil Saya',
            'route' => route('central.super-admin.profile.edit'),
            'active' => request()->routeIs('central.super-admin.profile.*'),
            'icon' => 'fa-id-badge',
        ],
        [
            'label' => 'Tenant Panel',
            'route' => route('central.super-admin.tenants.index'),
            'active' => request()->routeIs('central.super-admin.tenants.*'),
            'icon' => 'fa-users',
            'ability' => 'tenants.view',
        ],
        [
            'label' => 'Pengguna',
            'route' => route('central.super-admin.users.index'),
            'active' => request()->routeIs('central.super-admin.users.*'),
            'icon' => 'fa-user-shield',
            'ability' => 'users.view',
        ],
        [
            'label' => 'Demo Requests',
            'route' => route('central.super-admin.leads.index'),
            'active' => request()->routeIs('central.super-admin.leads.*'),
            'icon' => 'fa-comments-dollar',
            'ability' => 'leads.view',
        ],
        [
            'label' => 'System Settings',
            'route' => route('central.super-admin.settings.edit'),
            'active' => request()->routeIs('central.super-admin.settings.*'),
            'icon' => 'fa-sliders',
            'ability' => 'settings.view',
        ],
        [
            'label' => 'Ops Health',
            'route' => route('central.super-admin.ops.health'),
            'active' => request()->routeIs('central.super-admin.ops.*'),
            'icon' => 'fa-heart-pulse',
            'ability' => 'settings.view',
        ],
        [
            'label' => 'Packages',
            'route' => route('central.super-admin.packages.index'),
            'active' => request()->routeIs('central.super-admin.packages.*'),
            'icon' => 'fa-wallet',
            'ability' => 'packages.view',
        ],
        [
            'label' => 'Billing',
            'route' => route('central.super-admin.billing.index'),
            'active' => request()->routeIs('central.super-admin.billing.*'),
            'icon' => 'fa-file-invoice-dollar',
            'ability' => 'billing.view',
        ],
        [
            'label' => 'Landing Central',
            'route' => route('central.home'),
            'active' => request()->routeIs('central.home'),
            'icon' => 'fa-globe',
        ],
    ];
@endphp

<div class="sidebar-panel">
    <div>
        <a href="{{ route('central.super-admin.tenants.index') }}" class="sidebar-brand">
            <span class="brand-mark"><i class="fas fa-chart-line"></i></span>
            <span class="brand-copy">
                <strong>{{ $brandName }}</strong>
                <span>Admin Panel</span>
            </span>
        </a>

        <nav class="sidebar-nav">
            @foreach ($items as $item)
                @if (! isset($item['ability']) || $user?->canAccessCentral($item['ability']))
                    <a href="{{ $item['route'] }}" class="sidebar-item{{ $item['active'] ? ' active' : '' }}">
                        <i class="fas {{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
    </div>

    <div class="sidebar-footer">
        <a href="{{ route('central.super-admin.profile.edit') }}" class="sidebar-user" style="text-decoration: none;">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="user-avatar" style="object-fit: cover;">
            @else
                <span class="user-avatar">{{ $initials }}</span>
            @endif
            <span class="user-copy">
                <strong title="{{ $displayName }}">{{ $displayName }}</strong>
                <span>{{ ucfirst((string) ($user?->roleSlug() ?? 'staff')) }}</span>
            </span>
        </a>
    </div>
</div>
