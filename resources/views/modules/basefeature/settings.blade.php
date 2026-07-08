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

                    <div class="form-block">
                        <div class="card-head" style="margin-bottom: 12px;">
                            <div>
                                <h3 class="card-title" style="font-size: 1rem;">Pengaturan Jabatan & Role</h3>
                                <p class="card-subtitle">Opsional untuk tenant skala kecil vs skala besar (lebih rapi dan konsisten).</p>
                            </div>
                        </div>

                        @if (! ($userMetaSettingsSchemaReady ?? false))
                            <div class="alert alert-danger" style="margin-bottom: 12px;">
                                Pengaturan jabatan/role belum siap karena kolom settings tenant belum termigrasi. Jalankan migrasi tenant terbaru dulu.
                            </div>
                        @else
                            <label class="remember" style="color: var(--text); display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="use_job_title_master" value="1" @checked(old('use_job_title_master', ($useJobTitleMaster ?? false) ? '1' : '0') === '1') style="accent-color: var(--primary);">
                                <span>Aktifkan master jabatan (dropdown) untuk input jabatan user</span>
                            </label>

                            <div class="mini-list" style="margin-top: 14px;">
                                <div class="mini-row">
                                    <span>Status Master Jabatan</span>
                                    <strong>
                                        @if (($useJobTitleMaster ?? false) && ($jobTitleCatalogSchemaReady ?? false))
                                            Aktif
                                        @elseif ($useJobTitleMaster ?? false)
                                            Aktif (tapi tabel belum siap)
                                        @else
                                            Nonaktif (manual)
                                        @endif
                                    </strong>
                                </div>
                                <div class="mini-row">
                                    <span>Catatan</span>
                                    <strong>Kalau master aktif dan list jabatan diisi, input jabatan user dibatasi ke opsi yang tersedia.</strong>
                                </div>
                            </div>

                            <div style="margin-top: 16px;">
                                <label class="field-label">Label Role (tampilan)</label>
                                <div class="form-stack" style="gap: 10px;">
                                    @php
                                        $roleLabelOverrides = is_array($roleLabelOverrides ?? null) ? $roleLabelOverrides : [];
                                    @endphp
                                    @foreach (($roles ?? []) as $role)
                                        <div style="display: grid; grid-template-columns: 150px minmax(0, 1fr); gap: 12px; align-items: center;">
                                            <span class="status-muted">{{ $role->slug }}</span>
                                            <input
                                                class="field"
                                                type="text"
                                                name="role_labels[{{ $role->slug }}]"
                                                value="{{ old('role_labels.'.$role->slug, $roleLabelOverrides[$role->slug] ?? '') }}"
                                                placeholder="Contoh: Kepala Unit / Operator Loket">
                                        </div>
                                    @endforeach
                                </div>
                                <p class="muted" style="margin: 10px 0 0; font-size: 0.8125rem;">
                                    Ini cuma mengganti teks label role di tampilan (menu/header), bukan mengubah akses/permission.
                                </p>
                            </div>
                        @endif
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

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Master Jabatan</h3>
                            <p class="card-subtitle">Opsional. Dipakai kalau mode master jabatan diaktifkan.</p>
                        </div>
                    </div>

                    @if (! ($jobTitleCatalogSchemaReady ?? false))
                        <div class="alert alert-danger">
                            Tabel master jabatan belum siap. Jalankan migrasi tenant terbaru dulu.
                        </div>
                    @else
                        <form method="POST" action="{{ route('tenant.settings.job-titles.store') }}" class="form-stack" style="margin-bottom: 14px;">
                            @csrf
                            <div style="display: grid; grid-template-columns: minmax(0, 1fr) 110px; gap: 10px;">
                                <input class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Tambah jabatan (contoh: Admin Loket)">
                                <input class="field" type="number" name="sort_order" value="{{ old('sort_order', '0') }}" min="0" placeholder="Urut">
                            </div>
                            <label class="remember" style="color: var(--text); display: flex; align-items: center; gap: 10px;">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1') style="accent-color: var(--primary);">
                                <span>Aktif</span>
                            </label>
                            <button class="tenant-btn" type="submit">Tambah Jabatan</button>
                        </form>

                        <div class="form-stack" style="gap: 12px;">
                            @forelse (($jobTitles ?? []) as $jobTitle)
                                <div class="form-block" style="padding: 14px;">
                                    <form method="POST" action="{{ route('tenant.settings.job-titles.update', $jobTitle->id) }}" class="form-stack" style="gap: 10px;">
                                        @csrf
                                        @method('PATCH')
                                        <div style="display: grid; grid-template-columns: minmax(0, 1fr) 110px; gap: 10px;">
                                            <input class="field" type="text" name="name" value="{{ old('name', $jobTitle->name) }}">
                                            <input class="field" type="number" name="sort_order" value="{{ old('sort_order', (string) $jobTitle->sort_order) }}" min="0">
                                        </div>
                                        <div class="inline-actions" style="justify-content: space-between;">
                                            <label class="remember" style="color: var(--text); display: flex; align-items: center; gap: 10px;">
                                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $jobTitle->is_active ? '1' : '0') === '1') style="accent-color: var(--primary);">
                                                <span>{{ $jobTitle->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                            </label>
                                            <button class="tenant-btn-secondary" type="submit">Update</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('tenant.settings.job-titles.destroy', $jobTitle->id) }}" style="margin-top: 10px;">
                                        @csrf
                                        @method('DELETE')
                                        <button class="tenant-btn-secondary" type="submit" data-confirm data-confirm-title="Hapus jabatan?" data-confirm-text="Jabatan {{ $jobTitle->name }} akan dihapus dari master list.">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="alert alert-danger">
                                    Belum ada jabatan di master list. Tambahkan minimal 1 jabatan kalau mode master mau dipakai.
                                </div>
                            @endforelse
                        </div>
                    @endif
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Master Role</h3>
                            <p class="card-subtitle">Tambah role operasional baru (contoh: kasir, kolektor) tapi tetap pakai capability bawaan.</p>
                        </div>
                    </div>

                    @if (! ($roleCatalogSchemaReady ?? false))
                        <div class="alert alert-danger">
                            Tabel roles belum siap. Jalankan migrasi tenant terbaru dulu.
                        </div>
                    @elseif (! ($roleCapabilitySchemaReady ?? false))
                        <div class="alert alert-danger">
                            Kolom capability role belum siap. Jalankan migrasi tenant terbaru dulu.
                        </div>
                    @else
                        <form method="POST" action="{{ route('tenant.settings.roles.store') }}" class="form-stack" style="margin-bottom: 14px;">
                            @csrf
                            <div>
                                <label class="field-label" for="new-role-name">Nama Role</label>
                                <input class="field" id="new-role-name" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Kasir / Kolektor">
                            </div>

                            <div>
                                <label class="field-label" for="new-role-slug">Slug Role</label>
                                <input class="field" id="new-role-slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="contoh: kasir / kolektor (lowercase underscore)">
                            </div>

                            <div>
                                <label class="field-label" for="new-role-capability">Capability</label>
                                @php
                                    $capabilityOptions = is_array($capabilityOptions ?? null) ? $capabilityOptions : [];
                                    $canUseAdminOwner = (bool) ($isOwnerManager ?? false);
                                @endphp
                                <select id="new-role-capability" name="capability_slug">
                                    @foreach ($capabilityOptions as $capabilityKey => $capabilityLabel)
                                        @if ($canUseAdminOwner || ! in_array($capabilityKey, ['owner', 'admin'], true))
                                            <option value="{{ $capabilityKey }}" @selected(old('capability_slug', 'staff') === $capabilityKey)>{{ $capabilityLabel }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <button class="tenant-btn" type="submit">Tambah Role</button>
                        </form>

                        <div class="form-stack" style="gap: 12px;">
                            @forelse (($roles ?? []) as $role)
                                @php
                                    $roleSlug = (string) $role->slug;
                                    $isSystemRole = in_array($roleSlug, ['owner', 'admin', 'staff', 'meter_reader'], true);
                                    $effectiveCapability = trim((string) ($role->capability_slug ?? ''));
                                    $effectiveCapability = $effectiveCapability !== '' ? $effectiveCapability : $roleSlug;
                                @endphp

                                <div class="form-block" style="padding: 14px;">
                                    <div class="mini-list" style="margin-bottom: 10px;">
                                        <div class="mini-row">
                                            <span>Slug</span>
                                            <strong>{{ $roleSlug }}</strong>
                                        </div>
                                        <div class="mini-row">
                                            <span>Capability</span>
                                            <strong>{{ $capabilityOptions[$effectiveCapability] ?? $effectiveCapability }}</strong>
                                        </div>
                                    </div>

                                    @if ($isSystemRole)
                                        <div class="alert alert-success">
                                            Role sistem. Kalau mau ganti teks yang tampil di header/menu, pakai section "Label Role (tampilan)".
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('tenant.settings.roles.update', $role->id) }}" class="form-stack" style="gap: 10px;">
                                            @csrf
                                            @method('PATCH')

                                            <div>
                                                <label class="field-label" for="role-name-{{ $role->id }}">Nama</label>
                                                <input class="field" id="role-name-{{ $role->id }}" type="text" name="name" value="{{ old('name', $role->name) }}">
                                            </div>

                                            <div>
                                                <label class="field-label" for="role-capability-{{ $role->id }}">Capability</label>
                                                <select id="role-capability-{{ $role->id }}" name="capability_slug">
                                                    @foreach ($capabilityOptions as $capabilityKey => $capabilityLabel)
                                                        @if ($canUseAdminOwner || ! in_array($capabilityKey, ['owner', 'admin'], true))
                                                            <option value="{{ $capabilityKey }}" @selected(old('capability_slug', $effectiveCapability) === $capabilityKey)>{{ $capabilityLabel }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>

                                            <button class="tenant-btn-secondary" type="submit">Update</button>
                                        </form>

                                        <form method="POST" action="{{ route('tenant.settings.roles.destroy', $role->id) }}" style="margin-top: 10px;">
                                            @csrf
                                            @method('DELETE')
                                            <button class="tenant-btn-secondary" type="submit" data-confirm data-confirm-title="Hapus role?" data-confirm-text="Role {{ $role->name }} akan dihapus. Pastikan tidak dipakai oleh user.">
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <div class="alert alert-danger">
                                    Belum ada role. Jalankan migrasi/seed role dulu.
                                </div>
                            @endforelse
                        </div>
                    @endif
                </section>
            </aside>
        </section>
    </div>
@endsection
