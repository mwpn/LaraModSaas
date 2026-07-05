@extends('central.layouts.master')

@section('page_title', 'Tenant Detail')
@section('page_subtitle', 'Kontrol tenant, status, dan workspace snapshot')

@section('content')
    @php
        $statusClass = $tenantStatus === 'suspended' ? 'status-muted' : 'status-active';
        $statusLabel = $tenantStatus === 'suspended' ? 'Suspended' : 'Active';
        $syncClass = $isAlignedWithPlatform ? 'status-active' : 'status-pending';
        $syncLabel = $isAlignedWithPlatform ? 'Aligned with Platform' : 'Mismatch with Platform';
        $themeColor = $tenantWorkspaceSnapshot['theme_color'] ?? '#38bdf8';
        $packageLabel = $tenantPackage['label'] ?? ucfirst($tenantPackageCode);
        $subscriptionStatus = (string) data_get($tenantBillingSummary, 'status', 'active');
        $subscriptionLabel = match ($subscriptionStatus) {
            'trial' => 'Trial',
            'grace' => 'Grace',
            'expired' => 'Expired',
            'suspended' => 'Suspended',
            default => 'Active',
        };
        $subscriptionClass = match ($subscriptionStatus) {
            'expired', 'suspended' => 'status-muted',
            'grace' => 'status-pending',
            default => 'status-active',
        };
        $billingInvoice = data_get($tenantBillingSummary, 'invoice', []);
        $billingUsage = data_get($tenantBillingSummary, 'usage', []);
        $invoiceHistory = data_get($tenantBillingSummary, 'invoices', []);
        $latestInvoice = data_get($tenantBillingSummary, 'latest_invoice');
        $accessBlock = data_get($tenantBillingSummary, 'access_block', []);
        $accessBlockReason = (string) data_get($accessBlock, 'reason', '');
        $accessBlockLabel = (string) data_get($accessBlock, 'label', $statusLabel);
        $accessBlockClass = match ($accessBlockReason) {
            'manual_suspend', 'subscription_expired', 'invoice_overdue' => 'status-muted',
            default => 'status-active',
        };
        $latestInvoiceStatus = (string) data_get($latestInvoice, 'status', 'draft');
        $latestInvoiceLabel = match ($latestInvoiceStatus) {
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'void' => 'Void',
            'draft' => 'Draft',
            default => 'Issued',
        };
        $latestInvoiceClass = match ($latestInvoiceStatus) {
            'paid' => 'status-active',
            'overdue' => 'status-pending',
            'void' => 'status-muted',
            default => 'status-pending',
        };
        $qrisReady = filled(config('services.interactive_qris.apikey')) && filled(config('services.interactive_qris.merchant_id'));
        $manualTransfer = (array) config('services.billing_payment.manual_transfer', []);
        $currentUser = auth('central')->user();
        $canManageTenants = $currentUser?->canAccessCentral('tenants.manage') ?? false;
        $canManageBilling = $currentUser?->canAccessCentral('billing.manage') ?? false;
        $canViewPackages = $currentUser?->canAccessCentral('packages.view') ?? false;
        $tenantUserWorkspaceData = $tenantUserWorkspace ?? [];
        $tenantUserSchemaReady = (bool) data_get($tenantUserWorkspaceData, 'schema_ready', false);
        $tenantUsers = data_get($tenantUserWorkspaceData, 'users', []);
        $tenantUserRoles = data_get($tenantUserWorkspaceData, 'roles', []);
        $tenantUserStats = data_get($tenantUserWorkspaceData, 'stats', ['total' => 0, 'active' => 0, 'inactive' => 0, 'owners' => 0]);
        $tenantUserGeneratedPassword = session('tenant_user_generated_password');
        $collectibleInvoice = data_get($tenantBillingSummary, 'collectible_invoice');
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($tenantUserGeneratedPassword)
            <div class="alert alert-success">
                Password sementara untuk <strong>{{ $tenantUserGeneratedPassword['user_name'] }}</strong> ({{ $tenantUserGeneratedPassword['user_email'] }}) :
                <strong>{{ $tenantUserGeneratedPassword['password'] }}</strong>
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
            <div>
                <span class="hero-badge"><i class="fas fa-building"></i> Tenant Detail</span>
                <h2>{{ $tenantWorkspaceSnapshot['brand_name'] ?? $tenant->name ?? $tenant->id }}</h2>
                <p>Kontrol status tenant, cek sinkron platform, dan lihat snapshot workspace dari satu panel.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Tenant ID</span>
                    <strong>{{ $tenant->id }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Access</span>
                    <strong>{{ $accessBlockLabel }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Mode</span>
                    <strong>{{ ucfirst($tenantSaasType) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Package</span>
                    <strong>{{ $packageLabel }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Subscription</span>
                    <strong>{{ $subscriptionLabel }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Latest Invoice</span>
                    <strong>{{ $latestInvoice['invoice_number'] ?? '-' }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-users"></i></span>
                    <div class="stat-copy">
                        <p>User Tenant</p>
                        <strong>{{ $tenantUserStats['total'] }}</strong>
                        <span>{{ $tenantUserStats['active'] }} aktif</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-shield-halved"></i></span>
                    <div class="stat-copy">
                        <p>Owner Aktif</p>
                        <strong>{{ $tenantUserStats['owners'] }}</strong>
                        <span>Proteksi akun owner tenant</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-user-check"></i></span>
                    <div class="stat-copy">
                        <p>Akses Login</p>
                        <strong>{{ $tenantUserStats['active'] }}</strong>
                        <span>{{ $tenantUserStats['inactive'] }} nonaktif</span>
                    </div>
                </div>
            </div>
        </section>

        @if ($tenantUsers !== [])
            <section class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">User Tenant Snapshot</h3>
                        <p class="card-subtitle">Preview akun tenant yang sudah ada. Klik untuk lompat ke workspace manajemen user tenant.</p>
                    </div>
                    <div class="inline-actions">
                        <a class="central-btn-secondary" href="#tenant-users">Buka Pengguna Tenant</a>
                    </div>
                </div>

                <div class="quick-grid">
                    @foreach (collect($tenantUsers)->take(4) as $tenantUser)
                        <a class="quick-item" href="#tenant-user-card-{{ $tenantUser['id'] }}">
                            <div>
                                <strong>{{ $tenantUser['name'] }}</strong>
                                <span>{{ $tenantUser['email'] }}</span>
                                <span>{{ ucfirst((string) ($tenantUser['role_slug'] ?? 'staff')) }} · {{ $tenantUser['is_active'] ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Workspace Snapshot</h3>
                        <p class="card-subtitle">Ringkasan identitas tenant, koneksi, dan hasil sinkronisasi.</p>
                    </div>
                    <div class="inline-actions">
                        <span class="{{ $accessBlockClass }}">{{ $accessBlockLabel }}</span>
                        <span class="{{ $syncClass }}">{{ $syncLabel }}</span>
                    </div>
                </div>

                <div class="mini-list">
                    <div class="mini-row">
                        <span>Primary URL</span>
                        <strong>
                            @if ($tenantPrimaryUrl)
                                <a href="{{ $tenantPrimaryUrl }}" target="_blank" rel="noreferrer" style="color: inherit;">
                                    {{ $tenantPrimaryUrl }}
                                </a>
                            @else
                                Tidak tersedia
                            @endif
                        </strong>
                    </div>
                    <div class="mini-row">
                        <span>Database</span>
                        <strong>{{ $tenantDatabaseName ?: 'Belum tersedia' }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Platform Pusat</span>
                        <strong>{{ ucfirst($platformSaasType) }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Mode Tenant</span>
                        <strong>{{ ucfirst($tenantSaasType) }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Package</span>
                        <strong>{{ $packageLabel }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Price / Month</span>
                        <strong>
                            @if ($tenantPackage)
                                Rp{{ number_format((int) $tenantPackage['price_monthly'], 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </strong>
                    </div>
                    <div class="mini-row">
                        <span>Block Reason</span>
                        <strong>{{ data_get($accessBlock, 'message', 'Tenant aktif normal.') }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Subscription</span>
                        <strong>{{ $subscriptionLabel }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Suspend Sejak</span>
                        <strong>{{ $tenant->suspendedAt()?->format('d M Y H:i') ?? '-' }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Invoice Bulan Ini</span>
                        <strong>Rp{{ number_format((int) data_get($billingInvoice, 'invoice_total', 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Dibuat</span>
                        <strong>{{ $tenant->created_at?->format('d M Y H:i') ?? '-' }}</strong>
                    </div>
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
                        <a class="quick-item" href="{{ route('central.super-admin.tenants.index') }}">
                            <div>
                                <strong>Kembali ke Tenant Panel</strong>
                                <span>Balik ke daftar tenant</span>
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>

                        @if ($tenantPrimaryUrl)
                            <a class="quick-item" href="{{ $tenantPrimaryUrl }}" target="_blank" rel="noreferrer">
                                <div>
                                    <strong>Buka Workspace</strong>
                                    <span>Lihat landing tenant</span>
                                </div>
                                <i class="fas fa-arrow-up-right-from-square muted"></i>
                            </a>
                        @endif

                        @if ($canViewPackages)
                            <a class="quick-item" href="{{ route('central.super-admin.packages.index') }}">
                                <div>
                                    <strong>Package Settings</strong>
                                    <span>Kelola pricing pusat</span>
                                </div>
                                <i class="fas fa-arrow-right muted"></i>
                            </a>
                        @endif

                        @if ($canManageBilling)
                            <form method="POST" action="{{ route('central.super-admin.tenants.generate-invoice', $tenant->id) }}">
                                @csrf
                                <button
                                    class="quick-item"
                                    type="submit"
                                    style="width: 100%; text-align: left;"
                                    data-confirm
                                    data-confirm-title="Generate Invoice"
                                    data-confirm-message="Buat invoice baru untuk tenant {{ $tenant->id }} berdasarkan package aktif dan usage snapshot saat ini?"
                                    data-confirm-confirm-label="Ya, generate"
                                >
                                    <div>
                                        <strong>Generate Invoice</strong>
                                        <span>Rekam tagihan resmi periode berjalan</span>
                                    </div>
                                    <i class="fas fa-file-invoice-dollar muted"></i>
                                </button>
                            </form>
                        @endif

                        <a class="quick-item" href="#tenant-users">
                            <div>
                                <strong>Pengguna Tenant</strong>
                                <span>{{ $tenantUserStats['total'] }} akun tenant</span>
                            </div>
                            <i class="fas fa-arrow-right muted"></i>
                        </a>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Danger Zone</h3>
                            <p class="card-subtitle">Hapus tenant akan menghapus record central, domain, dan database tenant secara permanen.</p>
                        </div>
                    </div>

                    @if (is_array($collectibleInvoice))
                        <div class="alert alert-warning">
                            Tenant ini belum bisa dihapus karena masih ada invoice collectible
                            <strong>{{ $collectibleInvoice['invoice_number'] ?? '-' }}</strong>
                            dengan status
                            <strong>{{ strtoupper((string) ($collectibleInvoice['status'] ?? 'issued')) }}</strong>.
                        </div>
                    @endif

                    @if ($canManageTenants)
                        <form method="POST" action="{{ route('central.super-admin.tenants.destroy', $tenant->id) }}" class="form-stack">
                            @csrf
                            <div>
                                <label class="field-label" for="confirm-tenant-id">Ketik Tenant ID untuk konfirmasi</label>
                                <input
                                    class="field"
                                    id="confirm-tenant-id"
                                    type="text"
                                    name="confirm_tenant_id"
                                    value="{{ old('confirm_tenant_id') }}"
                                    placeholder="{{ $tenant->id }}"
                                >
                            </div>

                            <div class="mini-list">
                                <div class="mini-row">
                                    <span>Tenant ID</span>
                                    <strong>{{ $tenant->id }}</strong>
                                </div>
                                <div class="mini-row">
                                    <span>Domain</span>
                                    <strong>{{ $tenantDomains[0] ?? ($tenant->id . '.' . config('tenancy.central_domains.0')) }}</strong>
                                </div>
                                <div class="mini-row">
                                    <span>Database</span>
                                    <strong>{{ $tenantDatabaseName ?: 'Belum tersedia' }}</strong>
                                </div>
                            </div>

                            <button
                                class="central-btn-secondary"
                                type="submit"
                                @disabled(! $tenantCanDelete)
                                data-confirm
                                data-confirm-variant="danger"
                                data-confirm-title="Hapus tenant permanen?"
                                data-confirm-message="Tenant {{ $tenant->id }} akan dihapus permanen beserta domain dan database tenant. Aksi ini tidak bisa dibatalkan."
                                data-confirm-confirm-label="Ya, hapus tenant">
                                Hapus Tenant
                            </button>

                            @if (! $tenantCanDelete)
                                <span class="status-muted">Selesaikan atau void invoice collectible tenant dulu sebelum menghapus.</span>
                            @endif
                        </form>
                    @else
                        <span class="status-muted">Role ini tidak punya izin untuk menghapus tenant.</span>
                    @endif
                </section>
            </aside>
        </section>

        <section class="content-grid" id="tenant-users">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Pengguna Tenant</h3>
                        <p class="card-subtitle">Lihat akun tenant, role, dan bantu support operasional langsung dari panel pusat.</p>
                    </div>
                    <div class="inline-actions">
                        <span class="status-muted">{{ $tenantUserStats['total'] }} user</span>
                        <span class="status-active">{{ $tenantUserStats['active'] }} aktif</span>
                        <span class="status-pending">{{ $tenantUserStats['owners'] }} owner aktif</span>
                    </div>
                </div>

                @if (! $tenantUserSchemaReady)
                    <div class="alert alert-warning">
                        Fondasi `role_id` dan `is_active` pada user tenant belum siap. Jalankan migrasi tenant terbaru dulu supaya superadmin bisa ngelola user tenant penuh.
                    </div>
                @elseif ($tenantUsers === [])
                    <div class="quick-item">
                        <div>
                            <strong>Belum ada user tenant</strong>
                            <span>Workspace ini belum punya akun operasional selain seed default.</span>
                        </div>
                        <i class="fas fa-user-slash muted"></i>
                    </div>
                @else
                    <div class="form-stack">
                        @foreach ($tenantUsers as $tenantUser)
                            @php
                                $tenantUserRoleSlug = (string) ($tenantUser['role_slug'] ?? 'staff');
                                $tenantUserProtectedOwner = $tenantUserRoleSlug === 'owner' && ! $canManageTenants;
                            @endphp

                            <section class="form-block" id="tenant-user-card-{{ $tenantUser['id'] }}" style="display: grid; gap: 16px;">
                                <div class="card-head" style="margin-bottom: 0;">
                                    <div>
                                        <h3 class="card-title" style="font-size: 1rem;">{{ $tenantUser['name'] }}</h3>
                                        <p class="card-subtitle">{{ $tenantUser['email'] }}</p>
                                    </div>

                                    <div class="inline-actions">
                                        @if ($tenantUser['is_active'])
                                            <span class="status-active">Aktif</span>
                                        @else
                                            <span class="status-muted">Nonaktif</span>
                                        @endif

                                        <span class="status-pending">{{ ucfirst($tenantUserRoleSlug) }}</span>

                                        @if ($tenantUserRoleSlug === 'owner')
                                            <span class="status-muted">Owner</span>
                                        @endif
                                    </div>
                                </div>

                                @if ($canManageTenants)
                                    <form method="POST" action="{{ route('central.super-admin.tenants.users.update', [$tenant->id, $tenantUser['id']]) }}" class="form-stack">
                                        @csrf
                                        @method('PATCH')

                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                            <div>
                                                <label class="field-label" for="tenant-user-name-{{ $tenantUser['id'] }}">Nama</label>
                                                <input class="field" id="tenant-user-name-{{ $tenantUser['id'] }}" type="text" name="name" value="{{ $tenantUser['name'] }}">
                                            </div>

                                            <div>
                                                <label class="field-label" for="tenant-user-email-{{ $tenantUser['id'] }}">Email</label>
                                                <input class="field" id="tenant-user-email-{{ $tenantUser['id'] }}" type="email" name="email" value="{{ $tenantUser['email'] }}">
                                            </div>
                                        </div>

                                        <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                            <div>
                                                <label class="field-label" for="tenant-user-role-{{ $tenantUser['id'] }}">Role</label>
                                                <select id="tenant-user-role-{{ $tenantUser['id'] }}" name="role_id">
                                                    @foreach ($tenantUserRoles as $role)
                                                        <option value="{{ $role['id'] }}" @selected($tenantUser['role_id'] === $role['id'])>{{ $role['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="field-label" for="tenant-user-status-{{ $tenantUser['id'] }}">Status Akses</label>
                                                <select id="tenant-user-status-{{ $tenantUser['id'] }}" name="is_active">
                                                    <option value="1" @selected($tenantUser['is_active'])>Aktif</option>
                                                    <option value="0" @selected(! $tenantUser['is_active'])>Nonaktif</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="inline-actions">
                                            <button class="central-btn" type="submit">Simpan User</button>
                                        </div>
                                    </form>

                                    <div class="inline-actions">
                                        <form method="POST" action="{{ route('central.super-admin.tenants.users.toggle-active', [$tenant->id, $tenantUser['id']]) }}">
                                            @csrf
                                            <button
                                                class="central-btn-secondary"
                                                type="submit"
                                                data-confirm
                                                data-confirm-title="Ubah status user tenant?"
                                                data-confirm-message="Aksi ini akan {{ $tenantUser['is_active'] ? 'menonaktifkan' : 'mengaktifkan' }} akses login untuk {{ $tenantUser['name'] }}."
                                            >
                                                {{ $tenantUser['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('central.super-admin.tenants.users.reset-password', [$tenant->id, $tenantUser['id']]) }}">
                                            @csrf
                                            <button
                                                class="central-btn-secondary"
                                                type="submit"
                                                data-confirm
                                                data-confirm-title="Reset password user tenant?"
                                                data-confirm-message="Password {{ $tenantUser['name'] }} akan diganti dengan password sementara baru dari panel pusat."
                                            >
                                                Reset Password
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="mini-list">
                                        <div class="mini-row">
                                            <span>Role</span>
                                            <strong>{{ ucfirst($tenantUserRoleSlug) }}</strong>
                                        </div>
                                        <div class="mini-row">
                                            <span>Status</span>
                                            <strong>{{ $tenantUser['is_active'] ? 'Aktif' : 'Nonaktif' }}</strong>
                                        </div>
                                        <div class="mini-row">
                                            <span>Akses</span>
                                            <strong>{{ $tenantUserProtectedOwner ? 'Owner tenant terproteksi di mode view.' : 'Read only dari panel pusat.' }}</strong>
                                        </div>
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Tambah User Tenant</h3>
                            <p class="card-subtitle">Buat akun operasional tenant baru langsung dari support panel.</p>
                        </div>
                    </div>

                    @if ($canManageTenants && $tenantUserSchemaReady)
                        <form method="POST" action="{{ route('central.super-admin.tenants.users.store', $tenant->id) }}" class="form-stack">
                            @csrf

                            <div>
                                <label class="field-label" for="new-tenant-user-name">Nama</label>
                                <input class="field" id="new-tenant-user-name" type="text" name="name" value="{{ old('name') }}" placeholder="Nama pengguna tenant">
                            </div>

                            <div>
                                <label class="field-label" for="new-tenant-user-email">Email</label>
                                <input class="field" id="new-tenant-user-email" type="email" name="email" value="{{ old('email') }}" placeholder="user@tenant.com">
                            </div>

                            <div>
                                <label class="field-label" for="new-tenant-user-role">Role</label>
                                <select id="new-tenant-user-role" name="role_id">
                                    @foreach ($tenantUserRoles as $role)
                                        <option value="{{ $role['id'] }}" @selected(old('role_id') === $role['id'])>{{ $role['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <label class="checkbox-row">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1') style="margin-top: 3px; accent-color: var(--primary);">
                                <span>
                                    <strong style="display: block;">Aktifkan akses login</strong>
                                    <span class="muted">Kalau dicentang, user tenant langsung bisa login.</span>
                                </span>
                            </label>

                            <button class="central-btn" type="submit">Tambah User Tenant</button>
                        </form>
                    @elseif (! $tenantUserSchemaReady)
                        <div class="mini-list">
                            <div class="mini-row">
                                <span>Status</span>
                                <strong>Schema belum siap</strong>
                            </div>
                            <div class="mini-row">
                                <span>Catatan</span>
                                <strong>Tambah user tenant dikunci sampai migrasi tenant role/status siap.</strong>
                            </div>
                        </div>
                    @else
                        <div class="mini-list">
                            <div class="mini-row">
                                <span>Status</span>
                                <strong>Read only</strong>
                            </div>
                            <div class="mini-row">
                                <span>Catatan</span>
                                <strong>Role ini bisa lihat user tenant, tapi aksi support edit/jitak hanya untuk role manage tenant.</strong>
                            </div>
                        </div>
                    @endif
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Ringkasan User</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Total</span>
                            <strong>{{ $tenantUserStats['total'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Aktif</span>
                            <strong>{{ $tenantUserStats['active'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Nonaktif</span>
                            <strong>{{ $tenantUserStats['inactive'] }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Owner Aktif</span>
                            <strong>{{ $tenantUserStats['owners'] }}</strong>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Brand & Access</h3>
                        <p class="card-subtitle">Snapshot branding tenant yang sedang aktif di workspace.</p>
                    </div>
                </div>

                <div class="mini-list">
                    <div class="mini-row">
                        <span>Brand Name</span>
                        <strong>{{ $tenantWorkspaceSnapshot['brand_name'] }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Theme Color</span>
                        <strong>{{ $themeColor }}</strong>
                    </div>
                    <div class="mini-row">
                        <span>Source</span>
                        <strong>{{ $tenantWorkspaceSnapshot['source'] === 'tenant_settings' ? 'Tenant Settings' : 'Blueprint Fallback' }}</strong>
                    </div>
                    <div class="mini-row" style="align-items: flex-start;">
                        <span>Description</span>
                        <strong style="max-width: 520px; line-height: 1.7;">{{ $tenantWorkspaceSnapshot['description'] }}</strong>
                    </div>
                </div>

                <div class="card-head" style="margin-top: 24px;">
                    <div>
                        <h3 class="card-title">Domains</h3>
                    </div>
                </div>

                <div class="quick-grid">
                    @forelse ($tenantDomains as $tenantDomain)
                        <a class="quick-item" href="{{ 'https://' . $tenantDomain }}" target="_blank" rel="noreferrer">
                            <div>
                                <strong>{{ $tenantDomain }}</strong>
                                <span>Akses tenant</span>
                            </div>
                            <i class="fas fa-arrow-up-right-from-square muted"></i>
                        </a>
                    @empty
                        <div class="quick-item">
                            <div>
                                <strong>Belum ada domain</strong>
                                <span>Tenant belum punya endpoint aktif</span>
                            </div>
                            <i class="fas fa-link-slash muted"></i>
                        </div>
                    @endforelse
                </div>
            </div>

            <aside class="side-stack">
                @if ($canManageTenants)
                    <section class="dashboard-card">
                        <div class="card-head">
                            <div>
                                <h3 class="card-title">Tenant Control</h3>
                                <p class="card-subtitle">Aksi operasional langsung dari panel pusat.</p>
                            </div>
                        </div>

                        <div class="form-stack">
                            <form method="POST" action="{{ route('central.super-admin.tenants.switch-saas', $tenant->id) }}" class="form-stack">
                                @csrf
                                <label class="field-label" for="tenant-saas-type">Mode Tenant</label>
                                <select id="tenant-saas-type" name="saas_type">
                                    @foreach ($availableSaasTypes ?? \App\Models\CentralSetting::availablePlatformTypes() as $saasType)
                                        <option value="{{ $saasType }}" @selected($tenantSaasType === $saasType)>
                                            {{ ucfirst($saasType) }}
                                        </option>
                                    @endforeach
                                </select>
                                <button
                                    class="central-btn"
                                    type="submit"
                                    data-confirm
                                    data-confirm-title="Update Mode Tenant"
                                    data-confirm-message="Ubah mode tenant {{ $tenant->id }} sekarang? Tenant akan mengikuti mode yang dipilih pada form ini."
                                    data-confirm-confirm-label="Ya, update"
                                >
                                    Update Mode
                                </button>
                            </form>

                            <form method="POST" action="{{ route('central.super-admin.tenants.assign-package', $tenant->id) }}" class="form-stack">
                                @csrf
                                <label class="field-label" for="tenant-package-code">Package Tenant</label>
                                <select id="tenant-package-code" name="package_code">
                                    @foreach ($packageCatalog as $packageCode => $package)
                                        <option value="{{ $packageCode }}" @selected($tenantPackageCode === $packageCode)>
                                            {{ $package['label'] }} - Rp{{ number_format((int) $package['price_monthly'], 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>
                                <button
                                    class="central-btn-secondary"
                                    type="submit"
                                    data-confirm
                                    data-confirm-title="Update Package Tenant"
                                    data-confirm-message="Ubah package tenant {{ $tenant->id }} sekarang? Limit fitur dan modul tenant akan mengikuti package yang dipilih."
                                    data-confirm-confirm-label="Ya, update"
                                >
                                    Update Package
                                </button>
                            </form>

                            @if ($tenant->isSuspended())
                                <form method="POST" action="{{ route('central.super-admin.tenants.activate', $tenant->id) }}">
                                    @csrf
                                    <button class="central-btn" type="submit">Activate Tenant</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('central.super-admin.tenants.suspend', $tenant->id) }}">
                                    @csrf
                                    <button
                                        class="central-btn-secondary"
                                        type="submit"
                                        data-confirm
                                        data-confirm-variant="danger"
                                        data-confirm-title="Suspend Tenant"
                                        data-confirm-message="Suspend tenant {{ $tenant->id }} sekarang? Landing, login, dan dashboard tenant akan ditahan sementara."
                                        data-confirm-confirm-label="Ya, suspend"
                                    >
                                        Suspend Tenant
                                    </button>
                                </form>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Runtime Modules</h3>
                        </div>
                    </div>

                    <div class="quick-grid">
                        @foreach ($tenantRuntimeModules as $moduleName)
                            <div class="quick-item">
                                <div>
                                    <strong>{{ $moduleName }}</strong>
                                    <span>Aktif untuk workspace ini</span>
                                </div>
                                <span class="status-active">On</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Billing Snapshot</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Status</span>
                            <strong class="{{ $subscriptionClass }}">{{ $subscriptionLabel }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Setup Fee</span>
                            <strong>Rp{{ number_format((int) data_get($billingInvoice, 'setup_fee', 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Total / Bulan</span>
                            <strong>Rp{{ number_format((int) data_get($billingInvoice, 'monthly_total', 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Invoice Saat Ini</span>
                            <strong>Rp{{ number_format((int) data_get($billingInvoice, 'invoice_total', 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Latest Invoice Status</span>
                            <strong class="{{ $latestInvoiceClass }}">{{ $latestInvoiceLabel }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Access State</span>
                            <strong class="{{ $accessBlockClass }}">{{ $accessBlockLabel }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Billing Grace Ends</span>
                            <strong>{{ data_get($accessBlock, 'grace_ends_at')?->format('d M Y H:i') ?? '-' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Billing Sync</span>
                            <strong>{{ data_get($tenantBillingSummary, 'last_synced_at')?->format('d M Y H:i') ?? '-' }}</strong>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Payment Methods</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>QRIS</span>
                            <strong>{{ $qrisReady ? 'Ready' : 'Belum dikonfigurasi' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Transfer Bank</span>
                            <strong>{{ $manualTransfer['bank_name'] ?: '-' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Atas Nama</span>
                            <strong>{{ $manualTransfer['account_name'] ?: '-' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>No. Rekening</span>
                            <strong>{{ $manualTransfer['account_number'] ?: '-' }}</strong>
                        </div>
                        <div class="mini-row" style="align-items: flex-start;">
                            <span>Catatan</span>
                            <strong style="max-width: 320px; line-height: 1.7;">{{ $manualTransfer['notes'] ?: 'Belum ada catatan transfer manual.' }}</strong>
                        </div>
                    </div>
                </section>
            </aside>
        </section>

        <section class="content-grid">
            <div class="dashboard-card">
                <div class="card-head">
                    <div>
                        <h3 class="card-title">Subscription & Usage</h3>
                        <p class="card-subtitle">Pakai data ini untuk menghidupkan billing package tenant secara nyata.</p>
                    </div>
                </div>

                @if ($canManageBilling)
                    <form method="POST" action="{{ route('central.super-admin.tenants.update-billing', $tenant->id) }}" class="form-stack">
                        @csrf

                        <div class="inline-actions">
                            <div style="flex: 1 1 220px;">
                                <label class="field-label" for="subscription-status">Subscription Status</label>
                                <select id="subscription-status" name="subscription_status">
                                    @foreach (['trial' => 'Trial', 'active' => 'Active', 'grace' => 'Grace', 'expired' => 'Expired'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('subscription_status', $subscriptionStatus) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex: 1 1 220px;">
                                <label class="field-label" for="subscription-starts-at">Starts At</label>
                                <input id="subscription-starts-at" class="field" type="datetime-local" name="subscription_starts_at" value="{{ old('subscription_starts_at', data_get($tenantBillingSummary, 'starts_at')?->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div style="flex: 1 1 220px;">
                                <label class="field-label" for="subscription-expires-at">Expires At</label>
                                <input id="subscription-expires-at" class="field" type="datetime-local" name="subscription_expires_at" value="{{ old('subscription_expires_at', data_get($tenantBillingSummary, 'expires_at')?->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div style="flex: 1 1 220px;">
                                <label class="field-label" for="subscription-grace-until">Grace Until</label>
                                <input id="subscription-grace-until" class="field" type="datetime-local" name="subscription_grace_until" value="{{ old('subscription_grace_until', data_get($tenantBillingSummary, 'grace_until')?->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>

                        <div class="inline-actions">
                            <div style="flex: 1 1 180px;">
                                <label class="field-label" for="billing-customers">Customers</label>
                                <input id="billing-customers" class="field" type="number" min="0" name="billing_usage[customers]" value="{{ old('billing_usage.customers', $billingUsage['customers'] ?? 0) }}">
                            </div>
                            <div style="flex: 1 1 180px;">
                                <label class="field-label" for="billing-successful-transactions">Successful Transactions</label>
                                <input id="billing-successful-transactions" class="field" type="number" min="0" name="billing_usage[successful_transactions]" value="{{ old('billing_usage.successful_transactions', $billingUsage['successful_transactions'] ?? 0) }}">
                            </div>
                            <div style="flex: 1 1 180px;">
                                <label class="field-label" for="billing-checkouts">Checkouts</label>
                                <input id="billing-checkouts" class="field" type="number" min="0" name="billing_usage[checkouts]" value="{{ old('billing_usage.checkouts', $billingUsage['checkouts'] ?? 0) }}">
                            </div>
                            <div style="flex: 1 1 220px;">
                                <label class="field-label" for="billing-transaction-amount">Transaction Amount</label>
                                <input id="billing-transaction-amount" class="field" type="number" min="0" name="billing_usage[transaction_amount]" value="{{ old('billing_usage.transaction_amount', $billingUsage['transaction_amount'] ?? 0) }}">
                            </div>
                        </div>

                        <div class="inline-actions">
                            <button class="central-btn" type="submit">Update Billing Tenant</button>
                            <span class="status-muted">Package tenant saat ini akan dipakai untuk hitung invoice berdasarkan usage yang lo isi di sini.</span>
                        </div>
                    </form>
                @else
                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Status</span>
                            <strong>Read only</strong>
                        </div>
                        <div class="mini-row">
                            <span>Catatan</span>
                            <strong>Role ini hanya bisa lihat billing snapshot tenant tanpa mengubah runtime billing.</strong>
                        </div>
                    </div>
                @endif
            </div>

            <aside class="side-stack">
                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Package Limits</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        <div class="mini-row">
                            <span>Admin Users</span>
                            <strong>{{ $tenantPackage['limits']['max_admin_users'] ?? null ? number_format((int) $tenantPackage['limits']['max_admin_users']) : 'Unlimited' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Staff Users</span>
                            <strong>{{ $tenantPackage['limits']['max_staff_users'] ?? null ? number_format((int) $tenantPackage['limits']['max_staff_users']) : 'Unlimited' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Customers</span>
                            <strong>{{ $tenantPackage['limits']['max_customers'] ?? null ? number_format((int) $tenantPackage['limits']['max_customers']) : 'Unlimited' }}</strong>
                        </div>
                        <div class="mini-row">
                            <span>Transactions / Month</span>
                            <strong>{{ $tenantPackage['limits']['max_monthly_transactions'] ?? null ? number_format((int) $tenantPackage['limits']['max_monthly_transactions']) : 'Unlimited' }}</strong>
                        </div>
                    </div>
                </section>

                <section class="dashboard-card">
                    <div class="card-head">
                        <div>
                            <h3 class="card-title">Invoice Breakdown</h3>
                        </div>
                    </div>

                    <div class="mini-list">
                        @forelse (data_get($billingInvoice, 'lines', []) as $line)
                            <div class="mini-row">
                                <span>{{ $line['label'] }}</span>
                                <strong>Rp{{ number_format((int) $line['total'], 0, ',', '.') }}</strong>
                            </div>
                        @empty
                            <div class="mini-row">
                                <span>Belum ada billing rule aktif</span>
                                <strong>-</strong>
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </section>

        <section class="dashboard-card">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Invoice History</h3>
                    <p class="card-subtitle">Riwayat invoice tenant yang sudah digenerate dari package aktif.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Periode</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoiceHistory as $invoice)
                            @php
                                $historyStatus = (string) ($invoice['status'] ?? 'issued');
                                $historyLabel = match ($historyStatus) {
                                    'paid' => 'Paid',
                                    'overdue' => 'Overdue',
                                    'void' => 'Void',
                                    'draft' => 'Draft',
                                    default => 'Issued',
                                };
                                $historyClass = match ($historyStatus) {
                                    'paid' => 'status-active',
                                    'overdue' => 'status-pending',
                                    'void' => 'status-muted',
                                    default => 'status-pending',
                                };
                                $payment = (array) ($invoice['payment'] ?? []);
                                $paymentMethod = (string) data_get($payment, 'method', '');
                                $paymentStatus = (string) data_get($payment, 'status', '');
                                $paymentLabel = match ($paymentMethod) {
                                    'qris' => 'QRIS',
                                    'manual_transfer' => 'Transfer Manual',
                                    default => 'Belum dipilih',
                                };
                                $paymentStatusLabel = match ($paymentStatus) {
                                    'paid' => 'Paid',
                                    'pending' => 'Pending',
                                    'expired' => 'Expired',
                                    'void' => 'Void',
                                    'unpaid' => 'Unpaid',
                                    default => $paymentStatus !== '' ? ucfirst($paymentStatus) : '-',
                                };
                                $paymentStatusClass = match ($paymentStatus) {
                                    'paid' => 'status-active',
                                    'pending', 'unpaid' => 'status-pending',
                                    'expired', 'void' => 'status-muted',
                                    default => 'status-muted',
                                };
                                $qrisExpiresAt = data_get($payment, 'qris.expires_at');
                                $manualTransferPayment = (array) data_get($payment, 'manual_transfer', []);
                                $manualExpectedAmount = (int) data_get($manualTransferPayment, 'expected_amount', 0);
                                $manualUniqueCode = (int) data_get($manualTransferPayment, 'unique_code', 0);
                                $manualEvidenceMessageId = (string) data_get($manualTransferPayment, 'evidence.message_id', '');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $invoice['invoice_number'] }}</strong><br>
                                    <span class="muted">Due {{ $invoice['due_at']?->format('d M Y') ?? '-' }}</span><br>
                                    <span class="muted">{{ $paymentLabel }} · {{ $paymentStatusLabel }}</span>
                                </td>
                                <td>
                                    <strong>{{ $invoice['period_label'] ?: '-' }}</strong><br>
                                    <span class="muted">{{ $invoice['issued_at']?->format('d M Y H:i') ?? '-' }}</span>
                                </td>
                                <td>
                                    <strong>Rp{{ number_format((int) ($invoice['invoice_total'] ?? 0), 0, ',', '.') }}</strong><br>
                                    <span class="muted">{{ $invoice['currency'] ?? 'IDR' }}</span>
                                    @if ($manualExpectedAmount > 0)
                                        <br><span class="muted">Exact Rp{{ number_format($manualExpectedAmount, 0, ',', '.') }} · Kode {{ str_pad((string) $manualUniqueCode, 3, '0', STR_PAD_LEFT) }}</span>
                                    @endif
                                    @if ((string) data_get($payment, 'reference', '') !== '')
                                        <br><span class="muted">Ref {{ data_get($payment, 'reference') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ $historyClass }}">{{ $historyLabel }}</span>
                                    @if ($invoice['paid_at'])
                                        <br><span class="muted">Paid {{ $invoice['paid_at']?->format('d M Y H:i') }}</span>
                                    @endif
                                    @if ($paymentMethod !== '')
                                        <br><span class="{{ $paymentStatusClass }}">{{ $paymentStatusLabel }}</span>
                                    @endif
                                    @if ($paymentMethod === 'qris' && $qrisExpiresAt)
                                        <br><span class="muted">Expire {{ $qrisExpiresAt?->format('d M Y H:i') ?? '-' }}</span>
                                    @endif
                                    @if ((string) data_get($payment, 'paid_via', '') !== '')
                                        <br><span class="muted">Via {{ data_get($payment, 'paid_via') }}</span>
                                    @endif
                                    @if ($manualEvidenceMessageId !== '')
                                        <br><span class="muted">Evidence {{ $manualEvidenceMessageId }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="inline-actions">
                                        <a class="central-btn-secondary" href="{{ route('central.public-invoice.show', [$tenant->id, $invoice['invoice_number']]) }}" target="_blank" rel="noreferrer">
                                            Public Page
                                        </a>

                                        @if ($canManageBilling && ! in_array($historyStatus, ['paid', 'void'], true))
                                            @if ($qrisReady)
                                                <form method="POST" action="{{ route('central.super-admin.tenants.create-qris', $tenant->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="invoice_number" value="{{ $invoice['invoice_number'] }}">
                                                    <button
                                                        class="central-btn"
                                                        type="submit"
                                                        data-confirm
                                                        data-confirm-title="Generate QRIS"
                                                        data-confirm-message="Buat atau refresh QRIS untuk invoice {{ $invoice['invoice_number'] }} sekarang?"
                                                        data-confirm-confirm-label="Ya, generate"
                                                    >
                                                        Generate QRIS
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="POST" action="{{ route('central.super-admin.tenants.mark-transfer-paid', $tenant->id) }}">
                                                @csrf
                                                <input type="hidden" name="invoice_number" value="{{ $invoice['invoice_number'] }}">
                                                <button
                                                    class="central-btn-secondary"
                                                    type="submit"
                                                    data-confirm
                                                    data-confirm-title="Konfirmasi Transfer Manual"
                                                    data-confirm-message="Tandai invoice {{ $invoice['invoice_number'] }} sebagai paid via transfer manual?"
                                                    data-confirm-confirm-label="Ya, paid"
                                                >
                                                    Transfer Paid
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canManageBilling && $paymentMethod === 'qris' && ! in_array($historyStatus, ['paid', 'void'], true))
                                            <form method="POST" action="{{ route('central.super-admin.tenants.check-qris-status', $tenant->id) }}">
                                                @csrf
                                                <input type="hidden" name="invoice_number" value="{{ $invoice['invoice_number'] }}">
                                                <button
                                                    class="central-btn-secondary"
                                                    type="submit"
                                                    data-confirm
                                                    data-confirm-title="Cek Status QRIS"
                                                    data-confirm-message="Cek status pembayaran QRIS untuk invoice {{ $invoice['invoice_number'] }} sekarang?"
                                                    data-confirm-confirm-label="Ya, cek"
                                                >
                                                    Check QRIS
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canManageBilling && $historyStatus !== 'void')
                                            <form method="POST" action="{{ route('central.super-admin.tenants.update-invoice-status', $tenant->id) }}">
                                                @csrf
                                                <input type="hidden" name="invoice_number" value="{{ $invoice['invoice_number'] }}">
                                                <input type="hidden" name="status" value="void">
                                                <button
                                                    class="central-btn-secondary"
                                                    type="submit"
                                                    data-confirm
                                                    data-confirm-variant="danger"
                                                    data-confirm-title="Void Invoice"
                                                    data-confirm-message="Void invoice {{ $invoice['invoice_number'] }} sekarang? Record invoice tetap disimpan untuk histori."
                                                    data-confirm-confirm-label="Ya, void"
                                                >
                                                    Void
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canManageBilling && in_array($historyStatus, ['paid', 'void'], true))
                                            <form method="POST" action="{{ route('central.super-admin.tenants.update-invoice-status', $tenant->id) }}">
                                                @csrf
                                                <input type="hidden" name="invoice_number" value="{{ $invoice['invoice_number'] }}">
                                                <input type="hidden" name="status" value="issued">
                                                <button
                                                    class="central-btn-secondary"
                                                    type="submit"
                                                    data-confirm
                                                    data-confirm-title="Aktifkan Lagi Invoice"
                                                    data-confirm-message="Kembalikan invoice {{ $invoice['invoice_number'] }} ke status issued?"
                                                    data-confirm-confirm-label="Ya, aktifkan"
                                                >
                                                    Reopen
                                                </button>
                                            </form>
                                        @endif

                                        @unless ($canManageBilling)
                                            <span class="status-muted">Read only</span>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                            @if ($paymentMethod === 'qris' && (string) data_get($payment, 'qris.content', '') !== '')
                                <tr>
                                    <td colspan="5" style="background: rgba(15, 23, 42, 0.02);">
                                        <div class="mini-list">
                                            <div class="mini-row">
                                                <span>QRIS Invoice ID</span>
                                                <strong>{{ data_get($payment, 'qris.invoice_id', '-') }}</strong>
                                            </div>
                                            <div class="mini-row">
                                                <span>QRIS NMID</span>
                                                <strong>{{ data_get($payment, 'qris.nmid', '-') }}</strong>
                                            </div>
                                            <div class="mini-row" style="align-items: flex-start;">
                                                <span>QRIS Content</span>
                                                <strong style="max-width: 780px; word-break: break-all; line-height: 1.7;">{{ data_get($payment, 'qris.content') }}</strong>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="muted">Belum ada invoice yang direkam untuk tenant ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
