@extends('basefeature::layouts.master')

@section('page_title', 'Dashboard')
@section('page_subtitle', 'Overview of tenant workspace')

@section('content')
    <div class="page-grid">
        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-chart-pie"></i> Tenant</span>
                <h2>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</h2>
                <p>Track workspace identity, active SaaS mode, and core tenant configuration.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Mode</span>
                    <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Theme</span>
                    <strong>{{ $setting?->theme_color ?? '#000000' }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-layer-group"></i></span>
                    <div class="stat-copy">
                        <p>SaaS Mode</p>
                        <strong>{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</strong>
                        <span>Current tenant flow</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-palette"></i></span>
                    <div class="stat-copy">
                        <p>Theme</p>
                        <strong>{{ $setting?->theme_color ?? '#000000' }}</strong>
                        <span>Active brand color</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-database"></i></span>
                    <div class="stat-copy">
                        <p>Database</p>
                        <strong style="font-size: 1rem;">{{ tenant()?->database()?->getName() }}</strong>
                        <span>Connected workspace</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-id-badge"></i></span>
                    <div class="stat-copy">
                        <p>Tenant ID</p>
                        <strong>{{ tenant('id') }}</strong>
                        <span>Workspace identifier</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Workspace Summary</h3>
                        <p class="card-subtitle">Brand identity and current tenant status.</p>
                    </div>
                    <div class="inline-actions">
                        <a class="tenant-btn-secondary" href="{{ route('tenant.users.index') }}">Pengguna</a>
                        <a class="tenant-btn" href="{{ route('tenant.settings') }}">Pengaturan Web</a>
                        <a class="tenant-btn-secondary" href="{{ route('tenant.home') }}">Preview Landing</a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <tbody>
                            <tr>
                                <th>Brand</th>
                                <td>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</td>
                            </tr>
                            <tr>
                                <th>Deskripsi</th>
                                <td>{{ $setting?->description ?? 'Belum ada deskripsi bisnis.' }}</td>
                            </tr>
                            <tr>
                                <th>Mode</th>
                                <td><span class="status-pending">{{ ucfirst((string) (tenant('saas_type') ?? 'universal')) }}</span></td>
                            </tr>
                            <tr>
                                <th>Theme</th>
                                <td>{{ $setting?->theme_color ?? '#000000' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Quick Actions</h3>
                        </div>
                    </div>

                    <div class="quick-grid">
                        <a class="quick-item" href="{{ route('tenant.users.index') }}">
                            <div>
                                <strong>Pengguna</strong>
                                <span>Role & access</span>
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>
                        <a class="quick-item" href="{{ route('tenant.settings') }}">
                            <div>
                                <strong>Pengaturan Web</strong>
                                <span>Brand & theme</span>
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>
                        <a class="quick-item" href="{{ route('tenant.home') }}">
                            <div>
                                <strong>Preview Landing</strong>
                                <span>Public page</span>
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Tenant Stats</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Brand</span>
                            <strong>{{ $setting?->brand_name ?? tenant('name') ?? tenant('id') }}</strong>
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
                </section>
            </aside>
        </section>
    </div>
@endsection
