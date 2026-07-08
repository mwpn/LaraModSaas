@extends('basefeature::layouts.master')

@section('page_title', 'Pengguna')
@section('page_subtitle', 'Kelola akses user tenant dan role workspace')

@section('content')
    @php
        $managerIsOwner = $manager->roleSlug() === 'owner';
        $manageableRoles = $manageableRoles ?? $roles;
    @endphp

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

        @if ($generatedPassword)
            <div class="alert alert-success">
                Password sementara untuk <strong>{{ $generatedPassword['user_name'] }}</strong> ({{ $generatedPassword['user_email'] }}) :
                <strong>{{ $generatedPassword['password'] }}</strong>
            </div>
        @endif

        @if (! empty($areaScopeLabel))
            <div class="alert alert-info">
                Manajemen user dibatasi ke area kerja <strong>{{ $areaScopeLabel }}</strong> dan turunannya.
            </div>
        @endif

        @if (! $jobTitleSchemaReady)
            <div class="alert alert-danger">
                Kolom `job_title` di database tenant belum siap. Jalankan migrasi tenant terbaru dulu supaya jabatan pengguna bisa disimpan.
            </div>
        @endif

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-users-cog"></i> Tenant Access</span>
                <h2>Manajemen Pengguna</h2>
                <p>Tambah user baru, atur role sistem, jabatan operasional, dan area kerja seperti cabang, unit, atau rayon, aktifkan atau nonaktifkan akses, dan reset password langsung dari workspace tenant.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Manager</span>
                    <strong>{{ $manager->name }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Role</span>
                    <strong>{{ ucfirst((string) ($manager->roleSlug() ?? 'staff')) }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-users"></i></span>
                    <div class="stat-copy">
                        <p>Total User</p>
                        <strong>{{ $stats['total'] }}</strong>
                        <span>Semua akun tenant</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-user-check"></i></span>
                    <div class="stat-copy">
                        <p>User Aktif</p>
                        <strong>{{ $stats['active'] }}</strong>
                        <span>Akses berjalan</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-shield-halved"></i></span>
                    <div class="stat-copy">
                        <p>Owner Aktif</p>
                        <strong>{{ $stats['owners'] }}</strong>
                        <span>Minimal 1 owner aktif</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-map-location-dot"></i></span>
                    <div class="stat-copy">
                        <p>Area Terpasang</p>
                        <strong>{{ $stats['area_assigned'] }}</strong>
                        <span>User sudah punya area kerja</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-id-card-clip"></i></span>
                    <div class="stat-copy">
                        <p>Jabatan Terisi</p>
                        <strong>{{ $stats['job_filled'] }}</strong>
                        <span>User sudah punya jabatan operasional</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-user-slash"></i></span>
                    <div class="stat-copy">
                        <p>User Nonaktif</p>
                        <strong>{{ $stats['inactive'] }}</strong>
                        <span>Tidak bisa login</span>
                    </div>
                </div>
            </div>
        </section>

        @if (! $schemaReady)
            <section class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Fondasi Belum Siap</h3>
                        <p class="card-subtitle">Kolom `role_id` dan `is_active` pada user tenant belum tersedia.</p>
                    </div>
                </div>

                <div class="alert alert-danger">
                    Jalankan migrasi tenant terbaru dulu supaya manajemen pengguna bisa dipakai penuh.
                </div>
            </section>
        @else
            <section class="content-grid">
                <div class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Daftar Pengguna</h3>
                            <p class="card-subtitle">Edit profil user, role, jabatan, area kerja, status akses, dan password dari satu workspace.</p>
                        </div>
                    </div>

                    <div class="form-stack">
                        @forelse ($users as $user)
                            @php
                                $userRoleSlug = $user->roleSlug();
                                $isCurrentUser = $manager->getKey() === $user->getKey();
                                $canManageTarget = $managerIsOwner || $userRoleSlug !== 'owner';
                            @endphp

                            <section class="form-block" style="display: grid; gap: 16px;">
                                <div class="card-head" style="margin-bottom: 0;">
                                    <div>
                                        <h3 class="card-title" style="font-size: 1rem;">{{ $user->name }}</h3>
                                        <p class="card-subtitle">{{ $user->email }}</p>
                                    </div>

                                    <div class="inline-actions">
                                        @if ($user->isActiveUser())
                                            <span class="status-active">Aktif</span>
                                        @else
                                            <span class="status-muted">Nonaktif</span>
                                        @endif

                                        <span class="status-pending">{{ ucfirst((string) ($userRoleSlug ?? 'staff')) }}</span>

                                        @if (filled($user->job_title))
                                            <span class="status-muted">{{ $user->job_title }}</span>
                                        @endif

                                        @if ($isCurrentUser)
                                            <span class="status-muted">Akun Saat Ini</span>
                                        @endif

                                        <span class="status-muted">{{ $user->service_area_id ? $serviceAreaOptions->get($user->service_area_id, $user->serviceArea?->name ?? '-') : 'Tanpa Area' }}</span>

                                        @if (! $canManageTarget)
                                            <span class="status-muted">Protected Owner</span>
                                        @endif
                                    </div>
                                </div>

                                @if ($canManageTarget)
                                    <form method="POST" action="{{ route('tenant.users.update', $user->id) }}" class="form-stack">
                                        @csrf
                                        @method('PATCH')

                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                            <div>
                                                <label class="field-label" for="name-{{ $user->id }}">Nama</label>
                                                <input class="field" id="name-{{ $user->id }}" type="text" name="name" value="{{ old('name', $user->name) }}">
                                            </div>

                                            <div>
                                                <label class="field-label" for="job-title-{{ $user->id }}">Jabatan</label>
                                                @if ($jobTitleSchemaReady)
                                                    @php
                                                        $jobTitleCatalogActive = (bool) ($jobTitleCatalogEnabled ?? false);
                                                        $jobTitleCatalogOptions = $jobTitleCatalogOptions ?? collect();
                                                    @endphp
                                                    @if ($jobTitleCatalogActive && $jobTitleCatalogOptions->count() > 0)
                                                        <select id="job-title-{{ $user->id }}" name="job_title">
                                                            <option value="">Tanpa jabatan</option>
                                                            @foreach ($jobTitleCatalogOptions as $jobTitleOption)
                                                                <option value="{{ $jobTitleOption }}" @selected(old('job_title', $user->job_title) === $jobTitleOption)>{{ $jobTitleOption }}</option>
                                                            @endforeach
                                                        </select>
                                                    @else
                                                        <input class="field" id="job-title-{{ $user->id }}" type="text" name="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="Contoh: Supervisor Lapangan">
                                                    @endif
                                                @else
                                                    <input class="field" id="job-title-{{ $user->id }}" type="text" value="Jalankan migrasi tenant terbaru dulu" disabled>
                                                @endif
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                            <div>
                                                <label class="field-label" for="email-{{ $user->id }}">Email</label>
                                                <input class="field" id="email-{{ $user->id }}" type="email" name="email" value="{{ old('email', $user->email) }}">
                                            </div>

                                            <div class="mini-list" style="align-self: end;">
                                                <div class="mini-row">
                                                    <span>Struktur</span>
                                                    <strong>{{ $user->role?->name ?? 'User' }}{{ filled(old('job_title', $user->job_title)) ? ' | '.old('job_title', $user->job_title) : '' }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                            <div>
                                                <label class="field-label" for="role-{{ $user->id }}">Role</label>
                                                @if ($isCurrentUser)
                                                    <input type="hidden" name="role_id" value="{{ $user->role_id }}">
                                                    <input class="field" id="role-{{ $user->id }}" type="text" value="{{ $user->role?->name ?? 'Belum ada role' }}" disabled>
                                                @else
                                                    <select id="role-{{ $user->id }}" name="role_id">
                                                        @foreach ($manageableRoles as $role)
                                                            <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) === $role->id)>{{ $role->name }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </div>

                                            <div>
                                                <label class="field-label" for="area-{{ $user->id }}">Area Kerja</label>
                                                @if ($serviceAreaSchemaReady)
                                                    <select id="area-{{ $user->id }}" name="service_area_id">
                                                        @if (empty($areaScopeLabel))
                                                            <option value="">Global / tanpa area khusus</option>
                                                        @endif
                                                        @foreach ($serviceAreas as $serviceArea)
                                                            <option value="{{ $serviceArea->id }}" @selected((string) old('service_area_id', $user->service_area_id) === $serviceArea->id)>{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input class="field" id="area-{{ $user->id }}" type="text" value="Jalankan migrasi area kerja dulu" disabled>
                                                @endif
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                            <div>
                                                <label class="field-label" for="status-{{ $user->id }}">Status Akses</label>
                                                @if ($isCurrentUser)
                                                    <input type="hidden" name="is_active" value="{{ $user->isActiveUser() ? 1 : 0 }}">
                                                    <input class="field" id="status-{{ $user->id }}" type="text" value="{{ $user->isActiveUser() ? 'Aktif' : 'Nonaktif' }}" disabled>
                                                @else
                                                    <select id="status-{{ $user->id }}" name="is_active">
                                                        <option value="1" @selected((string) old('is_active', $user->isActiveUser() ? '1' : '0') === '1')>Aktif</option>
                                                        <option value="0" @selected((string) old('is_active', $user->isActiveUser() ? '1' : '0') === '0')>Nonaktif</option>
                                                    </select>
                                                @endif
                                            </div>
                                            <div class="mini-list" style="align-self: end;">
                                                <div class="mini-row">
                                                    <span>Cakupan</span>
                                                    <strong>{{ $user->service_area_id ? 'Berlaku di area terpilih dan turunannya' : 'Global untuk seluruh tenant' }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="inline-actions">
                                            <button class="tenant-btn" type="submit">Simpan Perubahan</button>
                                        </div>
                                    </form>

                                    <div class="inline-actions">
                                        @if (! $isCurrentUser)
                                            <form method="POST" action="{{ route('tenant.users.toggle-active', $user->id) }}">
                                                @csrf
                                                <button
                                                    class="tenant-btn-secondary"
                                                    type="submit"
                                                    data-confirm
                                                    data-confirm-title="Ubah status user?"
                                                    data-confirm-text="Aksi ini akan {{ $user->isActiveUser() ? 'menonaktifkan' : 'mengaktifkan' }} akses login untuk {{ $user->name }}.">
                                                    {{ $user->isActiveUser() ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('tenant.users.reset-password', $user->id) }}">
                                            @csrf
                                            <button
                                                class="tenant-btn-secondary"
                                                type="submit"
                                                data-confirm
                                                data-confirm-title="Reset password?"
                                                data-confirm-text="Password {{ $user->name }} akan diganti dengan password sementara baru.">
                                                Reset Password
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="mini-list">
                                        <div class="mini-row">
                                            <span>Role</span>
                                            <strong>{{ $user->role?->name ?? 'Belum ada role' }}</strong>
                                        </div>
                                        <div class="mini-row">
                                            <span>Status</span>
                                            <strong>{{ $user->isActiveUser() ? 'Aktif' : 'Nonaktif' }}</strong>
                                        </div>
                                        <div class="mini-row">
                                            <span>Jabatan</span>
                                            <strong>{{ $user->job_title ?: '-' }}</strong>
                                        </div>
                                        <div class="mini-row">
                                            <span>Area Kerja</span>
                                            <strong>{{ $user->service_area_id ? $serviceAreaOptions->get($user->service_area_id, $user->serviceArea?->name ?? '-') : 'Global / tanpa area khusus' }}</strong>
                                        </div>
                                        <div class="mini-row">
                                            <span>Akses</span>
                                            <strong>Hanya owner yang bisa mengubah akun owner lain.</strong>
                                        </div>
                                    </div>
                                @endif
                            </section>
                        @empty
                            <div class="dashboard-card">
                                <div class="card-head">
                                    <div>
                                        <h3 class="card-title">Belum Ada User di Scope Ini</h3>
                                        <p class="card-subtitle">Belum ada user yang terpasang ke area kerja Anda. Tambahkan user baru di panel samping untuk area ini.</p>
                                    </div>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>

                <aside class="side-stack">
                    <section class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Tambah Pengguna</h3>
                                <p class="card-subtitle">Invite user baru tanpa modal, lengkap dengan role, jabatan, dan area kerja.</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('tenant.users.store') }}" class="form-stack">
                            @csrf

                            <div>
                                <label class="field-label" for="new-name">Nama</label>
                                <input class="field" id="new-name" type="text" name="name" value="{{ old('name') }}" placeholder="Nama pengguna">
                            </div>

                            <div>
                                <label class="field-label" for="new-email">Email</label>
                                <input class="field" id="new-email" type="email" name="email" value="{{ old('email') }}" placeholder="user@tenant.com">
                            </div>

                            <div>
                                <label class="field-label" for="new-job-title">Jabatan</label>
                                @if ($jobTitleSchemaReady)
                                    @php
                                        $jobTitleCatalogActive = (bool) ($jobTitleCatalogEnabled ?? false);
                                        $jobTitleCatalogOptions = $jobTitleCatalogOptions ?? collect();
                                    @endphp
                                    @if ($jobTitleCatalogActive && $jobTitleCatalogOptions->count() > 0)
                                        <select id="new-job-title" name="job_title">
                                            <option value="">Tanpa jabatan</option>
                                            @foreach ($jobTitleCatalogOptions as $jobTitleOption)
                                                <option value="{{ $jobTitleOption }}" @selected(old('job_title') === $jobTitleOption)>{{ $jobTitleOption }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input class="field" id="new-job-title" type="text" name="job_title" value="{{ old('job_title') }}" placeholder="Contoh: Kolektor / Admin Cabang">
                                    @endif
                                @else
                                    <input class="field" id="new-job-title" type="text" value="Jalankan migrasi tenant terbaru dulu" disabled>
                                @endif
                            </div>

                            <div>
                                <label class="field-label" for="new-role">Role</label>
                                <select id="new-role" name="role_id">
                                    @foreach ($manageableRoles as $role)
                                        <option value="{{ $role->id }}" @selected(old('role_id') === $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="field-label" for="new-service-area">Area Kerja</label>
                                @if ($serviceAreaSchemaReady)
                                    <select id="new-service-area" name="service_area_id">
                                        @if (empty($areaScopeLabel))
                                            <option value="">Global / tanpa area khusus</option>
                                        @endif
                                        @foreach ($serviceAreas as $serviceArea)
                                            <option value="{{ $serviceArea->id }}" @selected(old('service_area_id') === $serviceArea->id)>{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input class="field" id="new-service-area" type="text" value="Jalankan migrasi area kerja dulu" disabled>
                                @endif
                            </div>

                            <label class="remember" style="color: var(--text);">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1') style="accent-color: var(--primary);">
                                <span>Aktifkan akses login setelah dibuat</span>
                            </label>

                            <button class="tenant-btn" type="submit">Tambah Pengguna</button>
                        </form>
                    </section>

                    <section class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Aturan Akses</h3>
                            </div>
                        </div>

                        <div class="mini-list">
                            <div class="mini-row">
                                <span>Owner</span>
                                <strong>Akses penuh tenant</strong>
                            </div>
                            <div class="mini-row">
                                <span>Admin</span>
                                <strong>Kelola user selain owner</strong>
                            </div>
                            <div class="mini-row">
                                <span>Staff</span>
                                <strong>Operasional biasa</strong>
                            </div>
                            <div class="mini-row">
                                <span>Jabatan</span>
                                <strong>Posisi kerja nyata seperti admin cabang, kolektor, kasir, atau supervisor lapangan</strong>
                            </div>
                            <div class="mini-row">
                                <span>Area Kerja</span>
                                <strong>Cabang, unit, atau rayon bisa dipasang per user dan berlaku ke turunannya</strong>
                            </div>
                            <div class="mini-row">
                                <span>Proteksi</span>
                                <strong>Minimal 1 owner aktif</strong>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>
        @endif
    </div>
@endsection
