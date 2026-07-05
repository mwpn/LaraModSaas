@extends('basefeature::layouts.master')

@section('page_title', 'Pengaturan Web')
@section('page_subtitle', 'Branding and landing setup')

@section('content')
    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-paint-brush"></i> Tenant</span>
                <h2>Pengaturan Web</h2>
                <p>Update brand, description, and color theme used across login, landing, and dashboard.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Brand</span>
                    <strong>{{ $setting->brand_name }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Theme</span>
                    <strong>{{ $setting->theme_color }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-signature"></i></span>
                    <div class="stat-copy">
                        <p>Brand</p>
                        <strong>{{ $setting->brand_name }}</strong>
                        <span>Public identity</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-fill-drip"></i></span>
                    <div class="stat-copy">
                        <p>Theme</p>
                        <strong>{{ $setting->theme_color }}</strong>
                        <span>Accent color</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-eye"></i></span>
                    <div class="stat-copy">
                        <p>Preview</p>
                        <strong>Ready</strong>
                        <span>Landing available</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-window-maximize"></i></span>
                    <div class="stat-copy">
                        <p>Scope</p>
                        <strong>Web</strong>
                        <span>Login, landing, dashboard</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Brand Settings</h3>
                        <p class="card-subtitle">Edit visual identity for this tenant.</p>
                    </div>
                    <div class="inline-actions">
                        <a class="tenant-btn-secondary" href="{{ route('tenant.dashboard') }}">Dashboard</a>
                        <a class="tenant-btn" href="{{ route('tenant.home') }}">Preview Landing</a>
                    </div>
                </div>

                <form method="POST" action="{{ route('tenant.settings.update') }}" class="form-stack">
                    @csrf

                    <div class="form-block">
                        <label class="field-label" for="brand_name">Nama Brand</label>
                        <input class="field" id="brand_name" type="text" name="brand_name" value="{{ old('brand_name', $setting->brand_name) }}">
                    </div>

                    <div class="form-block">
                        <label class="field-label" for="description">Deskripsi</label>
                        <textarea id="description" name="description" rows="5">{{ old('description', $setting->description) }}</textarea>
                    </div>

                    <div class="form-block">
                        <label class="field-label" for="theme_color">Warna Tema</label>
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <input id="theme_color" type="color" name="theme_color" value="{{ old('theme_color', $setting->theme_color) }}" style="height: 48px; width: 72px; padding: 4px; border-radius: 12px; border: 1px solid var(--border); background: #ffffff;">
                            <span class="status-muted">{{ old('theme_color', $setting->theme_color) }}</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                        <button class="tenant-btn" type="submit">Simpan Perubahan</button>
                        <span class="status-pending">{{ old('theme_color', $setting->theme_color) }}</span>
                    </div>
                </form>
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Preview Impact</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Landing</span>
                            <strong>Brand</strong>
                        </div>
                        <div class="mini-row">
                            <span>Login</span>
                            <strong>Accent</strong>
                        </div>
                        <div class="mini-row">
                            <span>Dashboard</span>
                            <strong>Theme</strong>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Current Values</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Brand</span>
                            <strong>{{ $setting->brand_name }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Theme</span>
                            <strong>{{ $setting->theme_color }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Status</span>
                            <strong>Ready</strong>
                        </div>
                    </div>
                </section>
            </aside>
        </section>
    </div>
@endsection
