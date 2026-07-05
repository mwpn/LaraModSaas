@extends('central.layouts.master')

@section('page_title', 'Demo Requests')
@section('page_subtitle', 'Kelola waitlist demo dan follow-up lead publik')

@section('content')
    @php
        $currentUser = auth('central')->user();
        $canManageLeads = $currentUser?->canAccessCentral('leads.manage') ?? false;
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if (session('provisioned_owner'))
            @php
                $provisionedOwner = session('provisioned_owner');
            @endphp
            <div class="alert alert-success">
                <strong>Owner tenant pertama berhasil dibuat.</strong><br>
                Email: {{ data_get($provisionedOwner, 'email') }}<br>
                Password sementara: {{ data_get($provisionedOwner, 'password') }}<br>
                Login: <a href="{{ data_get($provisionedOwner, 'login_url') }}" target="_blank" rel="noreferrer">{{ data_get($provisionedOwner, 'login_url') }}</a>
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
                <span class="hero-badge"><i class="fas fa-funnel-dollar"></i> Lead Pipeline</span>
                <h2>Demo Request Workspace</h2>
                <p>Pantau lead masuk dari form publik, follow-up lewat email atau WhatsApp, lalu gerakkan status pipeline sampai converted.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Platform</span>
                    <strong>{{ ucfirst((string) $platformType) }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Total Lead</span>
                    <strong>{{ $stats['total'] }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Converted</span>
                    <strong>{{ $stats['converted'] }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-inbox"></i></span>
                    <div class="stat-copy">
                        <p>Lead Baru</p>
                        <strong>{{ $stats['new'] }}</strong>
                        <span>Belum di-follow up</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-phone-volume"></i></span>
                    <div class="stat-copy">
                        <p>Contacted</p>
                        <strong>{{ $stats['contacted'] }}</strong>
                        <span>Sudah dihubungi</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-filter-circle-dollar"></i></span>
                    <div class="stat-copy">
                        <p>Qualified</p>
                        <strong>{{ $stats['qualified'] }}</strong>
                        <span>Layak lanjut closing</span>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-circle-check"></i></span>
                    <div class="stat-copy">
                        <p>Converted</p>
                        <strong>{{ $stats['converted'] }}</strong>
                        <span>Sudah jadi calon onboard</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Daftar Demo Request</h3>
                    <p class="card-subtitle">Filter lead publik lalu update status pipeline dari panel pusat.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('central.super-admin.leads.index') }}" class="inline-actions" style="margin-bottom: 18px;">
                <input
                    class="field"
                    type="text"
                    name="q"
                    value="{{ $filters['q'] }}"
                    placeholder="Cari nama, email, atau nomor HP"
                    style="flex: 1 1 240px;"
                >
                <select name="status" style="flex: 0 1 200px;">
                    <option value="">Semua status</option>
                    @foreach ($availableStatuses as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="central-btn-secondary" type="submit">Filter</button>
                @if ($filters['q'] !== '' || $filters['status'] !== '')
                    <a class="central-btn-secondary" href="{{ route('central.super-admin.leads.index') }}">Reset</a>
                @endif
            </form>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Lead</th>
                            <th>Kontak</th>
                            <th>Pipeline</th>
                            <th>Aktivitas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leads as $lead)
                            @php
                                $statusClass = match ($lead->normalizedStatus()) {
                                    'contacted' => 'status-pending',
                                    'qualified' => 'status-active',
                                    'converted' => 'status-active',
                                    default => 'status-muted',
                                };
                                $defaultBusinessName = old('business_name') ?: $lead->name;
                                $defaultSubdomain = old('subdomain') ?: \Illuminate\Support\Str::slug($lead->name);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $lead->name }}</strong><br>
                                    <span class="muted">{{ ucfirst((string) $lead->platform_type) }} · {{ $lead->created_at?->format('d M Y H:i') }}</span>
                                </td>
                                <td>
                                    <strong>{{ $lead->email }}</strong><br>
                                    <span class="muted">{{ $lead->phone_number }}</span>
                                </td>
                                <td>
                                    <div class="form-stack" style="gap: 8px;">
                                        <span class="{{ $statusClass }}">{{ $lead->statusLabel() }}</span>
                                        @if ($lead->last_contacted_at)
                                            <span class="muted">Last contact {{ $lead->last_contacted_at->format('d M Y H:i') }}</span>
                                        @endif
                                        @if ($lead->converted_at)
                                            <span class="muted">Converted {{ $lead->converted_at->format('d M Y H:i') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="inline-actions" style="justify-content: flex-start;">
                                        <a class="central-btn-secondary" href="mailto:{{ $lead->email }}">Email</a>
                                        <a class="central-btn-secondary" href="{{ $lead->whatsappUrl() }}" target="_blank" rel="noopener">WhatsApp</a>
                                    </div>
                                </td>
                                <td>
                                    @if ($canManageLeads)
                                        <div class="form-stack" style="gap: 10px;">
                                            <form method="POST" action="{{ route('central.super-admin.leads.update-status', $lead->id) }}" class="inline-actions" style="justify-content: flex-start;">
                                                @csrf
                                                <select name="status" style="min-width: 170px;">
                                                    @foreach ($availableStatuses as $status)
                                                        <option value="{{ $status }}" @selected($lead->normalizedStatus() === $status)>{{ ucfirst($status) }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="central-btn" type="submit">Update</button>
                                            </form>

                                            @if ($lead->isConverted())
                                                <a class="central-btn-secondary" href="{{ route('central.super-admin.tenants.show', $lead->converted_tenant_id) }}">
                                                    Buka Tenant `{{ $lead->converted_tenant_id }}`
                                                </a>
                                            @else
                                                <form method="POST" action="{{ route('central.super-admin.leads.convert-to-tenant', $lead->id) }}" class="form-stack" style="gap: 10px;">
                                                    @csrf
                                                    <input
                                                        class="field"
                                                        type="text"
                                                        name="business_name"
                                                        value="{{ $defaultBusinessName }}"
                                                        placeholder="Nama bisnis tenant"
                                                    >
                                                    <input
                                                        class="field"
                                                        type="text"
                                                        name="subdomain"
                                                        value="{{ $defaultSubdomain }}"
                                                        placeholder="subdomain-tenant"
                                                    >
                                                    <button
                                                        class="central-btn-secondary"
                                                        type="submit"
                                                        data-confirm
                                                        data-confirm-title="Convert lead ke tenant?"
                                                        data-confirm-message="Aksi ini akan membuat tenant baru, domain/subdomain, database tenant, lalu menandai lead ini sebagai converted."
                                                        data-confirm-confirm-label="Ya, convert sekarang">
                                                        Convert to Tenant
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @else
                                        <span class="status-muted">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state" style="padding: 24px 8px;">
                                        <strong>Belum ada demo request yang cocok dengan filter.</strong>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
