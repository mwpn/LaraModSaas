@extends('central.layouts.master')

@section('page_title', 'Profil Saya')
@section('page_subtitle', 'Biodata akun pusat, kontak, avatar, dan keamanan login')

@section('content')
    @php
        $avatarUrl = $user->avatarUrl();
        $initials = $user->profileInitials();
        $displayName = $user->name ?: $user->email;
        $roleLabel = ucfirst((string) ($user->roleSlug() ?? 'staff'));
        $brandName = $experience['brand_name'] ?? config('app.name', 'AirCloud');
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if (session('password_status'))
            <div class="alert alert-success">{{ session('password_status') }}</div>
        @endif

        @if (! $profileSchemaReady)
            <div class="alert alert-warning">
                Kolom `phone_number` dan `avatar_path` untuk user central belum siap. Nama dan email tetap bisa diubah, tapi jalankan migrasi terbaru dulu untuk menyimpan nomor HP dan avatar.
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="hero-card">
            <div class="profile-hero-copy">
                <span class="hero-badge"><i class="fas fa-id-badge"></i> Super Admin Profile</span>
                <h2>{{ $displayName }}</h2>
                <p>Kelola identitas akun pusat, channel kontak pribadi, dan keamanan login dari satu halaman yang ringkas.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Role</span>
                    <strong>{{ $roleLabel }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Email</span>
                    <strong>{{ $user->email }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Workspace</span>
                    <strong>{{ $brandName }}</strong>
                </div>
            </div>
        </section>

        <section class="content-grid profile-content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Data Profil</h3>
                        <p class="card-subtitle">Update identitas utama dan media avatar yang tampil di panel pusat.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('central.super-admin.profile.update') }}" enctype="multipart/form-data" class="form-stack">
                    @csrf
                    @method('PATCH')

                    <div class="profile-form-grid">
                        <div class="form-block">
                            <h4 class="form-title">Identitas</h4>

                            <label class="field-label" for="profile_name">Nama</label>
                            <input
                                id="profile_name"
                                type="text"
                                name="name"
                                class="field"
                                value="{{ old('name', $user->name) }}"
                                maxlength="255"
                                autocomplete="name"
                                required
                            >

                            <label class="field-label profile-field-top" for="profile_email">Email</label>
                            <input
                                id="profile_email"
                                type="email"
                                name="email"
                                class="field"
                                value="{{ old('email', $user->email) }}"
                                maxlength="255"
                                autocomplete="email"
                                required
                            >

                            <label class="field-label profile-field-top" for="profile_phone_number">Nomor HP</label>
                            <input
                                id="profile_phone_number"
                                type="text"
                                name="phone_number"
                                class="field"
                                value="{{ old('phone_number', $user->phone_number) }}"
                                maxlength="32"
                                autocomplete="tel"
                                placeholder="0812 3456 7890"
                            >
                            <p class="profile-help">Boleh kosong. Simpan format yang gampang dibaca admin, misalnya `0812 3456 7890` atau `+62 812 3456 7890`.</p>
                        </div>

                        <div class="form-block">
                            <h4 class="form-title">Avatar</h4>

                            <div class="avatar-panel">
                                @if ($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="{{ $displayName }}" class="profile-avatar-preview">
                                @else
                                    <div class="profile-avatar-preview profile-avatar-fallback">{{ $initials }}</div>
                                @endif

                                <div class="avatar-panel-copy">
                                    <strong>{{ $displayName }}</strong>
                                    <span>{{ $roleLabel }} central</span>
                                </div>
                            </div>

                            <label class="field-label profile-field-top" for="profile_avatar">Upload Avatar</label>
                            <input
                                id="profile_avatar"
                                type="file"
                                name="avatar"
                                class="field"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >
                            <p class="profile-help">Optional. Format `jpg`, `png`, atau `webp` dengan ukuran maksimal 2MB.</p>

                            @if ($avatarUrl)
                                <label class="checkbox-row profile-field-top">
                                    <input type="hidden" name="remove_avatar" value="0">
                                    <input type="checkbox" name="remove_avatar" value="1" style="margin-top: 3px;">
                                    <span>
                                        <strong style="display: block;">Hapus avatar sekarang</strong>
                                        <span class="muted">Kalau dicentang, panel akan balik pakai inisial nama.</span>
                                    </span>
                                </label>
                            @else
                                <input type="hidden" name="remove_avatar" value="0">
                            @endif
                        </div>
                    </div>

                    <div class="inline-actions">
                        <button type="submit" class="central-btn">
                            <i class="fas fa-save"></i>
                            Simpan Profil
                        </button>
                    </div>
                </form>
            </div>

            <div class="side-stack">
                <div class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Ubah Password</h3>
                            <p class="card-subtitle">Pisahkan update password dari biodata biar flow-nya tetap clean.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('central.super-admin.profile.password.update') }}" class="form-stack">
                        @csrf
                        @method('PATCH')

                        <div class="form-block">
                            <label class="field-label" for="current_password">Password Sekarang</label>
                            <input
                                id="current_password"
                                type="password"
                                name="current_password"
                                class="field"
                                autocomplete="current-password"
                                required
                            >

                            <label class="field-label profile-field-top" for="password">Password Baru</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="field"
                                autocomplete="new-password"
                                required
                            >

                            <label class="field-label profile-field-top" for="password_confirmation">Konfirmasi Password Baru</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="field"
                                autocomplete="new-password"
                                required
                            >
                            <p class="profile-help">Minimal 8 karakter, gunakan kombinasi huruf besar, huruf kecil, dan angka.</p>
                        </div>

                        <div class="inline-actions">
                            <button type="submit" class="central-btn">
                                <i class="fas fa-key"></i>
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <div class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Ringkasan Akun</h3>
                            <p class="card-subtitle">Snapshot cepat identitas akun yang aktif dipakai sekarang.</p>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Nama</span>
                            <strong>{{ $user->name ?: '-' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Email</span>
                            <strong>{{ $user->email }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Nomor HP</span>
                            <strong>{{ $user->phone_number ?: '-' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Role</span>
                            <strong>{{ $roleLabel }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Avatar</span>
                            <strong>{{ $avatarUrl ? 'Aktif' : 'Belum ada' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .profile-content-grid {
            grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.9fr);
        }

        .profile-form-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.9fr);
            gap: 18px;
        }

        .profile-field-top {
            margin-top: 16px;
        }

        .profile-help {
            margin: 8px 0 0;
            font-size: 0.8125rem;
            line-height: 1.5;
            color: var(--muted);
        }

        .avatar-panel {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, #f9fafb 100%);
        }

        .profile-avatar-preview {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            object-fit: cover;
            flex: none;
            box-shadow: var(--shadow-sm);
        }

        .profile-avatar-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: color-mix(in srgb, var(--primary) 14%, #ffffff);
            color: var(--primary);
            font-size: 1.125rem;
            font-weight: 800;
        }

        .avatar-panel-copy strong {
            display: block;
            font-size: 0.9375rem;
            color: var(--text);
        }

        .avatar-panel-copy span {
            display: block;
            margin-top: 4px;
            font-size: 0.8125rem;
            color: var(--muted);
        }

        @media (max-width: 1080px) {
            .profile-content-grid,
            .profile-form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush
