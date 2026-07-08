@extends('basefeature::layouts.master')

@section('page_title', 'Warehouse Tirta')
@section('page_subtitle', 'Kontrol stok barang operasional seperti pipa, water meter, fitting, dan distribusi antar lokasi')

@push('styles')
    <style>
        .warehouse-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(320px, 1fr);
            gap: 24px;
        }
        .warehouse-stack,
        .warehouse-list,
        .stock-list,
        .movement-list {
            display: grid;
            gap: 16px;
        }
        .warehouse-panel,
        .warehouse-item,
        .stock-item,
        .movement-item {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            padding: 18px;
        }
        .warehouse-head,
        .movement-head,
        .stock-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .warehouse-head h3,
        .movement-head h3,
        .stock-head h3 {
            margin: 0;
            font-size: 1rem;
        }
        .warehouse-head p,
        .movement-head p,
        .stock-head p {
            margin: 6px 0 0;
            font-size: 0.84rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .tab-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
        }
        .metric-row,
        .meta-row,
        .movement-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .metric-pill,
        .meta-pill,
        .movement-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
        }
        .movement-pill.transfer {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }
        .movement-pill.receipt {
            background: #ecfdf5;
            border-color: #bbf7d0;
            color: #047857;
        }
        .movement-pill.issue {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #c2410c;
        }
        .movement-pill.submitted {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }
        .movement-pill.approved {
            background: #fef3c7;
            border-color: #fde68a;
            color: #b45309;
        }
        .movement-pill.completed {
            background: #ecfdf5;
            border-color: #bbf7d0;
            color: #047857;
        }
        .warehouse-form,
        .movement-form {
            display: grid;
            gap: 14px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .field-label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
        }
        .field,
        select,
        textarea {
            width: 100%;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #ffffff;
            padding: 10px 12px;
            font-size: 0.875rem;
            color: var(--text);
        }
        textarea {
            min-height: 96px;
            resize: vertical;
        }
        .muted-note {
            font-size: 0.76rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .table-wrap {
            overflow-x: auto;
        }
        .warehouse-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
        }
        .warehouse-table th,
        .warehouse-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }
        .warehouse-table th {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }
        .low-stock {
            color: #b45309;
            font-weight: 700;
        }
        @media (max-width: 1023px) {
            .warehouse-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $tab = $activeTab ?: 'movements';
        $availableTabs = is_array($availableTabs ?? null) ? $availableTabs : ['requests', 'stocks'];
        $canCreateWarehouseRequest = (bool) ($canCreateWarehouseRequest ?? false);
        $canManageWarehouseStock = (bool) ($canManageWarehouseStock ?? false);
        $canManageWarehouseMaster = (bool) ($canManageWarehouseMaster ?? false);
        $canManageWarehouseSuppliers = (bool) ($canManageWarehouseSuppliers ?? false);
        $canApproveWarehouseRequests = (bool) ($canApproveWarehouseRequests ?? false);
        $canApproveProcurementRequests = (bool) ($canApproveProcurementRequests ?? false);
        $canCompleteWarehouseRequests = (bool) ($canCompleteWarehouseRequests ?? false);
        $viewErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
    @endphp

    <div class="page-grid">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($viewErrors->any())
            <div class="alert alert-danger">
                @foreach ($viewErrors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if (! empty($areaScopeLabel))
            <div class="alert alert-info">
                Data warehouse dibatasi ke area kerja <strong>{{ $areaScopeLabel }}</strong> dan turunannya.
            </div>
        @endif

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-warehouse"></i> Warehouse Tirta</span>
                <h2>Stok barang operasional per lokasi</h2>
                <p>Kelola stok pipa, water meter, fitting, dan material lapangan lain dari gudang pusat sampai distribusi ke unit, rayon, atau cabang.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Lokasi</span>
                    <strong>{{ number_format($stats['locations'], 0, ',', '.') }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Barang</span>
                    <strong>{{ number_format($stats['items'], 0, ',', '.') }}</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Total Stok</span>
                    <strong>{{ number_format($stats['stock_total'], 0, ',', '.') }}</strong>
                </div>
            </div>
        </section>

        <section class="stat-grid">
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-boxes-stacked"></i></span>
                    <div class="stat-copy">
                        <p>Lokasi Aktif</p>
                        <strong>{{ number_format($locations->where('is_active', true)->count(), 0, ',', '.') }}</strong>
                        <span>Gudang pusat, unit, rayon, dan cabang</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-droplet"></i></span>
                    <div class="stat-copy">
                        <p>Barang Aktif</p>
                        <strong>{{ number_format($items->where('is_active', true)->count(), 0, ',', '.') }}</strong>
                        <span>Master material yang siap dipakai di lapangan</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-triangle-exclamation"></i></span>
                    <div class="stat-copy">
                        <p>Stok Menipis</p>
                        <strong>{{ number_format($stats['low_stock_items'], 0, ',', '.') }}</strong>
                        <span>Barang yang sudah menyentuh batas minimum</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-file-signature"></i></span>
                    <div class="stat-copy">
                        <p>Request Pending</p>
                        <strong>{{ number_format($stats['request_submitted'], 0, ',', '.') }}</strong>
                        <span>Menunggu approval pengadaan/distribusi/PSB</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-inner">
                    <span class="stat-icon"><i class="fas fa-circle-check"></i></span>
                    <div class="stat-copy">
                        <p>Siap Eksekusi</p>
                        <strong>{{ number_format($stats['request_approved'], 0, ',', '.') }}</strong>
                        <span>Sudah approved dan tinggal dijalankan</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="tab-strip">
            @if (in_array('requests', $availableTabs, true))
                <a href="{{ route('tenant.tirta.warehouse', ['tab' => 'requests']) }}" class="{{ $tab === 'requests' ? 'tenant-btn' : 'tenant-btn-secondary' }}">Workflow Request</a>
            @endif
            @if (in_array('movements', $availableTabs, true))
                <a href="{{ route('tenant.tirta.warehouse', ['tab' => 'movements']) }}" class="{{ $tab === 'movements' ? 'tenant-btn' : 'tenant-btn-secondary' }}">Mutasi Stok</a>
            @endif
            @if (in_array('stocks', $availableTabs, true))
                <a href="{{ route('tenant.tirta.warehouse', ['tab' => 'stocks']) }}" class="{{ $tab === 'stocks' ? 'tenant-btn' : 'tenant-btn-secondary' }}">Saldo per Lokasi</a>
            @endif
            @if (in_array('items', $availableTabs, true))
                <a href="{{ route('tenant.tirta.warehouse', ['tab' => 'items']) }}" class="{{ $tab === 'items' ? 'tenant-btn' : 'tenant-btn-secondary' }}">Master Barang</a>
            @endif
            @if (in_array('locations', $availableTabs, true))
                <a href="{{ route('tenant.tirta.warehouse', ['tab' => 'locations']) }}" class="{{ $tab === 'locations' ? 'tenant-btn' : 'tenant-btn-secondary' }}">Lokasi Warehouse</a>
            @endif
            @if (in_array('suppliers', $availableTabs, true))
                <a href="{{ route('tenant.tirta.warehouse', ['tab' => 'suppliers']) }}" class="{{ $tab === 'suppliers' ? 'tenant-btn' : 'tenant-btn-secondary' }}">Supplier</a>
            @endif
        </div>

        <section class="warehouse-grid">
            <div class="warehouse-stack">
                @if ($tab === 'requests')
                    <div class="warehouse-panel">
                        <div class="warehouse-head">
                            <div>
                                <h3>Workflow Request Warehouse</h3>
                                <p>Flow disusun mengikuti bisnis: gudang pusat bisa ajukan pengadaan, cabang minta ke pusat, dan teknik/PSB minta barang sebelum pasang.</p>
                            </div>
                        </div>

                        @if ($canCreateWarehouseRequest)
                            <form method="POST" action="{{ route('tenant.tirta.warehouse.requests.store') }}" class="movement-form">
                                @csrf
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="request-type">Jenis Dokumen</label>
                                        <select id="request-type" name="request_type">
                                            @foreach ($requestTypeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('request_type', 'distribution') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label" for="request-title">Judul Request</label>
                                        <input id="request-title" class="field" type="text" name="title" value="{{ old('title') }}" placeholder="Contoh: Restock water meter gudang pusat / Request PSB pelanggan A">
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="request-source">Lokasi Asal</label>
                                        <select id="request-source" name="source_location_id">
                                            <option value="">Pilih lokasi asal</option>
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}" @selected(old('source_location_id') === $location->id)>{{ $location->name }} ({{ $locationTypeOptions[$location->location_type] ?? ucfirst($location->location_type) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label" for="request-destination">Lokasi Tujuan</label>
                                        <select id="request-destination" name="destination_location_id">
                                            <option value="">Pilih lokasi tujuan</option>
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}" @selected(old('destination_location_id') === $location->id)>{{ $location->name }} ({{ $locationTypeOptions[$location->location_type] ?? ucfirst($location->location_type) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="request-supplier">Supplier (untuk pengadaan)</label>
                                        <select id="request-supplier" name="supplier_id">
                                            <option value="">Pilih supplier</option>
                                            @foreach (($suppliers ?? []) as $supplier)
                                                @if ($supplier->is_active)
                                                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') === $supplier->id)>{{ $supplier->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label" for="request-connection">Sambungan / PSB</label>
                                        <select id="request-connection" name="service_connection_id">
                                            <option value="">Opsional, pilih kalau request PSB/teknik</option>
                                            @foreach ($connections as $connection)
                                                <option value="{{ $connection->id }}" @selected(old('service_connection_id') === $connection->id)>{{ $connection->service_number }} - {{ $connection->customer?->name ?? 'Tanpa pelanggan' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label" for="request-reference">Referensi</label>
                                    <input id="request-reference" class="field" type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Contoh: PR-001 / PSB-001">
                                </div>

                                @for ($i = 0; $i < 3; $i++)
                                    <div class="form-grid">
                                        <div>
                                            <label class="field-label">Barang {{ $i + 1 }}</label>
                                            <select name="lines[{{ $i }}][inventory_item_id]">
                                                <option value="">Pilih barang</option>
                                                @foreach ($items as $item)
                                                    <option value="{{ $item->id }}" @selected(old('lines.'.$i.'.inventory_item_id') === $item->id)>{{ $item->name }}{{ $item->is_serialized ? ' [SN]' : '' }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="field-label">Qty</label>
                                            <input class="field" type="number" min="0" name="lines[{{ $i }}][quantity_requested]" value="{{ old('lines.'.$i.'.quantity_requested', $i === 0 ? 1 : 0) }}">
                                        </div>
                                    </div>
                                @endfor

                                <div>
                                    <label class="field-label" for="request-notes">Catatan</label>
                                    <textarea id="request-notes" name="notes" rows="3" placeholder="Contoh: stok pipa gudang pusat menipis / kebutuhan survey sambungan baru">{{ old('notes') }}</textarea>
                                </div>

                                <button class="tenant-btn" type="submit">Buat Dokumen Request</button>
                            </form>
                        @else
                            <div class="warehouse-note">
                                Akun ini hanya bisa memantau atau memproses approval request. Pembuatan dokumen request warehouse dibatasi untuk owner, admin, staff operasional, dan role gudang/logistik.
                            </div>
                        @endif
                    </div>

                    <div class="movement-list">
                        @forelse ($requests as $requestItem)
                            @php
                                $requestConnection = $requestItem->serviceConnection;
                                $requestHasSerialized = $requestItem->lines->contains(fn ($line) => (bool) ($line->item?->is_serialized ?? false));
                                $meterSerialNumber = data_get($requestItem->meta, 'meter_serial_number');
                            @endphp
                            <div class="movement-item">
                                <div class="movement-head">
                                    <div>
                                        <h3>{{ $requestItem->title }}</h3>
                                        <p>{{ $requestItem->request_number }} • {{ $requestTypeOptions[$requestItem->request_type] ?? ucfirst($requestItem->request_type) }} • {{ $requestItem->requestedBy?->name ?? 'Sistem' }}</p>
                                    </div>
                                    <span class="movement-pill {{ $requestItem->status }}">{{ $requestStatusOptions[$requestItem->status] ?? ucfirst($requestItem->status) }}</span>
                                </div>

                                <div class="movement-meta">
                                    @if ($requestItem->sourceLocation)
                                        <span class="meta-pill"><i class="fas fa-arrow-up"></i> Dari {{ $requestItem->sourceLocation->name }}</span>
                                    @endif
                                    @if ($requestItem->destinationLocation)
                                        <span class="meta-pill"><i class="fas fa-arrow-down"></i> Ke {{ $requestItem->destinationLocation->name }}</span>
                                    @endif
                                @if ($requestItem->supplier)
                                    <span class="meta-pill"><i class="fas fa-truck-field"></i> {{ $requestItem->supplier->name }}</span>
                                @endif
                                    @if ($requestConnection)
                                        <span class="meta-pill"><i class="fas fa-house-user"></i> {{ $requestConnection->service_number }} - {{ $requestConnection->customer?->name ?? '-' }}</span>
                                    @endif
                                    @if ($meterSerialNumber)
                                        <span class="meta-pill"><i class="fas fa-barcode"></i> SN {{ $meterSerialNumber }}</span>
                                    @endif
                                </div>

                                <div class="mini-list" style="margin-top: 14px;">
                                    @foreach ($requestItem->lines as $line)
                                        <div class="mini-row">
                                            <span>{{ $line->item?->name ?? '-' }}{{ $line->item?->is_serialized ? ' [SN]' : '' }}</span>
                                            <strong>{{ number_format($line->quantity_requested, 0, ',', '.') }} / approve {{ number_format($line->quantity_approved, 0, ',', '.') }} / selesai {{ number_format($line->quantity_completed, 0, ',', '.') }}</strong>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($requestItem->notes)
                                    <p style="margin: 14px 0 0; font-size: 0.84rem; color: var(--muted);">{{ $requestItem->notes }}</p>
                                @endif

                                @if (
                                    $requestItem->status === 'submitted'
                                    && $canApproveWarehouseRequests
                                    && ($requestItem->request_type !== 'procurement' || $canApproveProcurementRequests)
                                )
                                    <form method="POST" action="{{ route('tenant.tirta.warehouse.requests.approve', $requestItem->id) }}" class="form-stack" style="margin-top: 14px;">
                                        @csrf
                                        <input class="field" type="text" name="approval_notes" placeholder="Catatan approval (opsional)">
                                        <button class="tenant-btn-secondary" type="submit">Approve Request</button>
                                    </form>
                                @elseif ($requestItem->status === 'submitted' && $canApproveWarehouseRequests)
                                    <div class="warehouse-note" style="margin-top: 14px;">
                                        Approval pengadaan pusat hanya bisa diproses oleh owner atau user role `keuangan`.
                                    </div>
                                @elseif ($requestItem->status === 'submitted' && $requestItem->request_type === 'procurement')
                                    <div class="warehouse-note" style="margin-top: 14px;">
                                        Approval pengadaan pusat hanya bisa diproses oleh owner atau user role `keuangan`.
                                    </div>
                                @elseif ($requestItem->status === 'approved' && $canCompleteWarehouseRequests)
                                    <form method="POST" action="{{ route('tenant.tirta.warehouse.requests.complete', $requestItem->id) }}" class="form-stack" style="margin-top: 14px;">
                                        @csrf
                                        @if ($requestItem->request_type === 'installation' && $requestHasSerialized)
                                            <div>
                                                <label class="field-label">SN Water Meter</label>
                                                <input class="field" type="text" name="meter_serial_number" placeholder="Wajib untuk barang serialized">
                                            </div>
                                        @endif
                                        <input class="field" type="text" name="completion_notes" placeholder="Catatan penerimaan/pengeluaran barang">
                                        <button class="tenant-btn" type="submit">Selesaikan & Update Stok</button>
                                    </form>
                                @elseif ($requestItem->status === 'approved')
                                    <div class="warehouse-note" style="margin-top: 14px;">
                                        Dokumen ini sudah approved. Hanya tim gudang/logistik atau admin yang bisa menyelesaikan request dan memperbarui stok.
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="warehouse-panel">Belum ada dokumen request warehouse. Mulai dari pengadaan pusat, distribusi ke cabang, atau request barang PSB.</div>
                        @endforelse
                    </div>
                @elseif ($tab === 'suppliers')
                    <div class="warehouse-panel">
                        <div class="warehouse-head">
                            <div>
                                <h3>Master Supplier</h3>
                                <p>Supplier dipakai khusus untuk dokumen pengadaan pusat. Nanti bisa dipakai untuk histori harga/PO kalau mau dinaikkan level.</p>
                            </div>
                        </div>

                        @if ($canManageWarehouseSuppliers)
                            <form method="POST" action="{{ route('tenant.tirta.warehouse.suppliers.store') }}" class="warehouse-form">
                                @csrf
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="supplier-name">Nama Supplier</label>
                                        <input id="supplier-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: CV Tirta Jaya">
                                    </div>
                                    <div>
                                        <label class="field-label" for="supplier-contact">Contact Person</label>
                                        <input id="supplier-contact" class="field" type="text" name="contact_person" value="{{ old('contact_person') }}" placeholder="Contoh: Pak Budi">
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="supplier-phone">Telepon</label>
                                        <input id="supplier-phone" class="field" type="text" name="phone" value="{{ old('phone') }}" placeholder="08xx / 0xxx">
                                    </div>
                                    <div>
                                        <label class="field-label" for="supplier-email">Email</label>
                                        <input id="supplier-email" class="field" type="email" name="email" value="{{ old('email') }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label" for="supplier-address">Alamat</label>
                                    <textarea id="supplier-address" name="address" rows="3">{{ old('address') }}</textarea>
                                </div>
                                <div>
                                    <label class="field-label" for="supplier-notes">Catatan</label>
                                    <textarea id="supplier-notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                                </div>
                                <label class="remember" style="color: var(--text); display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1') style="accent-color: var(--primary);">
                                    <span>Aktif</span>
                                </label>
                                <button class="tenant-btn" type="submit">Tambah Supplier</button>
                            </form>
                        @else
                            <div class="warehouse-note">
                                Akun ini hanya bisa melihat supplier. Pengelolaan master supplier dibatasi untuk owner, admin, staff operasional, dan role gudang/logistik.
                            </div>
                        @endif
                    </div>

                    <div class="warehouse-list">
                        @forelse (($suppliers ?? []) as $supplier)
                            <div class="warehouse-item">
                                <div class="warehouse-head">
                                    <div>
                                        <h3>{{ $supplier->name }}</h3>
                                        <p>
                                            {{ $supplier->contact_person ?: 'Tanpa PIC' }}
                                            {{ $supplier->phone ? ' • '.$supplier->phone : '' }}
                                            {{ $supplier->email ? ' • '.$supplier->email : '' }}
                                        </p>
                                    </div>
                                    <span class="meta-pill">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                                @if ($supplier->address)
                                    <p style="margin: 12px 0 0; font-size: 0.84rem; color: var(--muted);">{{ $supplier->address }}</p>
                                @endif
                                @if ($supplier->notes)
                                    <p style="margin: 10px 0 0; font-size: 0.84rem; color: var(--muted);">{{ $supplier->notes }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="warehouse-panel">Belum ada supplier. Tambahkan minimal 1 supplier untuk flow pengadaan.</div>
                        @endforelse
                    </div>
                @elseif ($tab === 'movements')
                    <div class="warehouse-panel">
                        <div class="warehouse-head">
                            <div>
                                <h3>Catat Mutasi Barang</h3>
                                <p>Gunakan form ini untuk barang masuk, barang keluar, atau transfer antar lokasi seperti dari gudang pusat ke rayon.</p>
                            </div>
                        </div>

                        @if ($canManageWarehouseStock)
                            <form method="POST" action="{{ route('tenant.tirta.warehouse.movements.store') }}" class="movement-form" data-movement-form>
                                @csrf
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="movement-type">Jenis Mutasi</label>
                                        <select id="movement-type" name="movement_type" data-movement-type>
                                            @foreach ($movementTypeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('movement_type', 'transfer') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label" for="movement-item">Barang</label>
                                        <select id="movement-item" name="inventory_item_id">
                                            <option value="">Pilih barang</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}" @selected(old('inventory_item_id') === $item->id)>{{ $item->name }}{{ $item->sku ? ' - ' . $item->sku : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div data-source-wrap>
                                        <label class="field-label" for="movement-source">Lokasi Asal</label>
                                        <select id="movement-source" name="source_location_id">
                                            <option value="">Pilih lokasi asal</option>
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}" @selected(old('source_location_id') === $location->id)>{{ $location->name }} ({{ $locationTypeOptions[$location->location_type] ?? ucfirst($location->location_type) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div data-destination-wrap>
                                        <label class="field-label" for="movement-destination">Lokasi Tujuan</label>
                                        <select id="movement-destination" name="destination_location_id">
                                            <option value="">Pilih lokasi tujuan</option>
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}" @selected(old('destination_location_id') === $location->id)>{{ $location->name }} ({{ $locationTypeOptions[$location->location_type] ?? ucfirst($location->location_type) }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="movement-quantity">Jumlah</label>
                                        <input id="movement-quantity" class="field" type="number" min="1" name="quantity" value="{{ old('quantity', 1) }}">
                                    </div>
                                    <div>
                                        <label class="field-label" for="movement-date">Tanggal Mutasi</label>
                                        <input id="movement-date" class="field" type="date" name="movement_date" value="{{ old('movement_date', now()->toDateString()) }}">
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="movement-ref">Referensi</label>
                                        <input id="movement-ref" class="field" type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="Contoh: DO-001 atau BA-STOK">
                                    </div>
                                    <div>
                                        <label class="field-label" for="movement-notes">Catatan</label>
                                        <input id="movement-notes" class="field" type="text" name="notes" value="{{ old('notes') }}" placeholder="Contoh: kirim untuk pemasangan sambungan baru">
                                    </div>
                                </div>

                                <button class="tenant-btn" type="submit">Simpan Mutasi</button>
                            </form>
                        @else
                            <div class="warehouse-note">
                                Akun ini hanya bisa melihat histori mutasi. Input mutasi stok dibatasi untuk owner, admin, staff operasional, dan role gudang/logistik.
                            </div>
                        @endif
                    </div>

                    <div class="movement-list">
                        @forelse ($movements as $movement)
                            <div class="movement-item">
                                <div class="movement-head">
                                    <div>
                                        <h3>{{ $movement->item?->name ?? '-' }}</h3>
                                        <p>{{ $movement->movement_date?->format('d M Y') ?? '-' }} • {{ $movement->createdBy?->name ?? 'Sistem' }}</p>
                                    </div>
                                    <span class="movement-pill {{ $movement->movement_type }}">{{ $movementTypeOptions[$movement->movement_type] ?? ucfirst($movement->movement_type) }}</span>
                                </div>
                                <div class="movement-meta">
                                    <span class="meta-pill"><i class="fas fa-cubes"></i> {{ number_format($movement->quantity, 0, ',', '.') }} {{ $movement->item?->unit ?? 'pcs' }}</span>
                                    @if ($movement->sourceLocation)
                                        <span class="meta-pill"><i class="fas fa-arrow-up"></i> Dari {{ $movement->sourceLocation->name }}</span>
                                    @endif
                                    @if ($movement->destinationLocation)
                                        <span class="meta-pill"><i class="fas fa-arrow-down"></i> Ke {{ $movement->destinationLocation->name }}</span>
                                    @endif
                                    @if ($movement->reference_number)
                                        <span class="meta-pill"><i class="fas fa-hashtag"></i> {{ $movement->reference_number }}</span>
                                    @endif
                                </div>
                                @if ($movement->notes)
                                    <p style="margin: 14px 0 0; font-size: 0.84rem; color: var(--muted);">{{ $movement->notes }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="warehouse-panel">Belum ada mutasi stok yang tercatat.</div>
                        @endforelse
                    </div>
                @elseif ($tab === 'stocks')
                    <div class="warehouse-panel">
                        <div class="warehouse-head">
                            <div>
                                <h3>Saldo Stok per Lokasi</h3>
                                <p>Pantau stok riil per lokasi. Ini jadi basis pengiriman ke unit, rayon, atau cabang berikutnya.</p>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table class="warehouse-table">
                                <thead>
                                    <tr>
                                        <th>Barang</th>
                                        <th>Lokasi</th>
                                        <th>Saldo</th>
                                        <th>Unit/Rayon</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($stocks as $stock)
                                        <tr>
                                            <td>
                                                <strong>{{ $stock->item?->name ?? '-' }}</strong>
                                                <div class="muted-note">{{ $stock->item?->sku ?? 'Tanpa SKU' }}</div>
                                            </td>
                                            <td>{{ $stock->location?->name ?? '-' }}</td>
                                            <td>{{ number_format($stock->on_hand, 0, ',', '.') }} {{ $stock->item?->unit ?? 'pcs' }}</td>
                                            <td>{{ $stock->location?->serviceArea?->name ?? 'Global / non-rayon' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">Belum ada saldo stok. Catat mutasi barang masuk dulu.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif ($tab === 'items')
                    <div class="warehouse-panel">
                        <div class="warehouse-head">
                            <div>
                                <h3>Master Barang</h3>
                                <p>Daftarkan material operasional seperti pipa, water meter, stop kran, fitting, clamp, dan perlengkapan lapangan lain.</p>
                            </div>
                        </div>

                        @if ($canManageWarehouseMaster)
                            <form method="POST" action="{{ route('tenant.tirta.warehouse.items.store') }}" class="warehouse-form">
                                @csrf
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="item-sku">SKU</label>
                                        <input id="item-sku" class="field" type="text" name="sku" value="{{ old('sku') }}" placeholder="Contoh: WM-15MM">
                                    </div>
                                    <div>
                                        <label class="field-label" for="item-name">Nama Barang</label>
                                        <input id="item-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Water Meter 1/2 inch">
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="item-category">Kategori</label>
                                        <input id="item-category" class="field" type="text" name="category" value="{{ old('category') }}" placeholder="Contoh: meter, pipa, fitting">
                                    </div>
                                    <div>
                                        <label class="field-label" for="item-unit">Satuan</label>
                                        <input id="item-unit" class="field" type="text" name="unit" value="{{ old('unit', 'pcs') }}">
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="item-minimum">Minimum Stok</label>
                                        <input id="item-minimum" class="field" type="number" min="0" name="minimum_stock" value="{{ old('minimum_stock', 0) }}">
                                    </div>
                                    <div>
                                        <label class="field-label" for="item-notes">Catatan</label>
                                        <input id="item-notes" class="field" type="text" name="notes" value="{{ old('notes') }}">
                                    </div>
                                </div>
                                <label class="remember" style="color: var(--text); display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="is_serialized" value="1" @checked(old('is_serialized') === '1') style="accent-color: var(--primary);">
                                    <span>Barang serialized (contoh: water meter dengan SN unik)</span>
                                </label>
                                <button class="tenant-btn" type="submit">Tambah Barang</button>
                            </form>
                        @else
                            <div class="warehouse-note">
                                Akun ini hanya bisa melihat master barang. Pengelolaan item dibatasi untuk owner, admin, staff operasional, dan role gudang/logistik.
                            </div>
                        @endif
                    </div>

                    <div class="warehouse-list">
                        @foreach ($items as $item)
                            <div class="warehouse-item">
                                <div class="warehouse-head">
                                    <div>
                                        <h3>{{ $item->name }}</h3>
                                        <p>{{ $item->category ?: 'Tanpa kategori' }}{{ $item->sku ? ' • ' . $item->sku : '' }}</p>
                                    </div>
                                    <span class="meta-pill">{{ number_format((int) ($item->total_stock ?? 0), 0, ',', '.') }} {{ $item->unit }}</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-pill"><i class="fas fa-box-open"></i> Minimum {{ number_format($item->minimum_stock, 0, ',', '.') }} {{ $item->unit }}</span>
                                    @if ($item->is_serialized)
                                        <span class="metric-pill"><i class="fas fa-barcode"></i> Tracking SN</span>
                                    @endif
                                    @if ((int) ($item->total_stock ?? 0) <= (int) $item->minimum_stock && (int) $item->minimum_stock > 0)
                                        <span class="metric-pill low-stock"><i class="fas fa-triangle-exclamation"></i> Butuh restock</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="warehouse-panel">
                        <div class="warehouse-head">
                            <div>
                                <h3>Lokasi Warehouse</h3>
                                <p>Buat titik stok seperti gudang pusat, unit operasional, rayon, atau cabang. Lokasi ini nanti jadi sumber dan tujuan mutasi barang.</p>
                            </div>
                        </div>

                        @if ($canManageWarehouseMaster)
                            <form method="POST" action="{{ route('tenant.tirta.warehouse.locations.store') }}" class="warehouse-form">
                                @csrf
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="location-name">Nama Lokasi</label>
                                        <input id="location-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Gudang Pusat Tirta">
                                    </div>
                                    <div>
                                        <label class="field-label" for="location-code">Kode</label>
                                        <input id="location-code" class="field" type="text" name="code" value="{{ old('code') }}" placeholder="Contoh: GDPST">
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="location-type">Tipe Lokasi</label>
                                        <select id="location-type" name="location_type">
                                            @foreach ($locationTypeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(old('location_type', 'warehouse') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label" for="location-area">Terkait Unit/Rayon</label>
                                        <select id="location-area" name="service_area_id">
                                            <option value="">Tidak terkait rayon</option>
                                            @foreach ($serviceAreas as $area)
                                                <option value="{{ $area->id }}" @selected(old('service_area_id') === $area->id)>{{ $area->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div>
                                        <label class="field-label" for="location-manager">Penanggung Jawab</label>
                                        <input id="location-manager" class="field" type="text" name="manager_name" value="{{ old('manager_name') }}">
                                    </div>
                                    <div>
                                        <label class="field-label" for="location-address">Alamat</label>
                                        <input id="location-address" class="field" type="text" name="address" value="{{ old('address') }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label" for="location-notes">Catatan</label>
                                    <textarea id="location-notes" name="notes">{{ old('notes') }}</textarea>
                                </div>
                                <button class="tenant-btn" type="submit">Tambah Lokasi</button>
                            </form>
                        @else
                            <div class="warehouse-note">
                                Akun ini hanya bisa melihat daftar lokasi. Pengelolaan lokasi warehouse dibatasi untuk owner, admin, staff operasional, dan role gudang/logistik.
                            </div>
                        @endif
                    </div>

                    <div class="warehouse-list">
                        @foreach ($locations as $location)
                            <div class="warehouse-item">
                                <div class="warehouse-head">
                                    <div>
                                        <h3>{{ $location->name }}</h3>
                                        <p>{{ $locationTypeOptions[$location->location_type] ?? ucfirst($location->location_type) }}{{ $location->code ? ' • ' . $location->code : '' }}</p>
                                    </div>
                                    <span class="meta-pill">{{ number_format((int) ($location->total_stock ?? 0), 0, ',', '.') }} item</span>
                                </div>
                                <div class="metric-row">
                                    <span class="metric-pill"><i class="fas fa-map-pin"></i> {{ $location->serviceArea?->name ?? 'Global / non-rayon' }}</span>
                                    @if ($location->manager_name)
                                        <span class="metric-pill"><i class="fas fa-user"></i> {{ $location->manager_name }}</span>
                                    @endif
                                    @if ($location->is_default)
                                        <span class="metric-pill"><i class="fas fa-star"></i> Lokasi utama</span>
                                    @endif
                                </div>
                                @if ($location->notes)
                                    <p style="margin: 12px 0 0; font-size: 0.84rem; color: var(--muted);">{{ $location->notes }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="warehouse-stack">
                <div class="warehouse-panel">
                    <div class="warehouse-head">
                        <div>
                            <h3>Flow Operasional</h3>
                            <p>Biar stok lapangan tetap rapih dan jejak barang gampang dilacak.</p>
                        </div>
                    </div>
                    <div class="stock-list">
                        <div class="stock-item">
                            <div class="stock-head">
                                <div>
                                    <h3>1. Daftarkan lokasi</h3>
                                    <p>Buat gudang pusat, unit, rayon, atau cabang yang memang pegang stok.</p>
                                </div>
                            </div>
                        </div>
                        <div class="stock-item">
                            <div class="stock-head">
                                <div>
                                    <h3>2. Daftarkan barang</h3>
                                    <p>Masukkan master item seperti pipa, water meter, fitting, stop kran, atau seal meter.</p>
                                </div>
                            </div>
                        </div>
                        <div class="stock-item">
                            <div class="stock-head">
                                <div>
                                    <h3>3. Catat mutasi</h3>
                                    <p>Mulai dari barang masuk ke gudang, lalu transfer atau keluarkan sesuai operasional lapangan.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="warehouse-panel">
                    <div class="warehouse-head">
                        <div>
                            <h3>Barang Menipis</h3>
                            <p>Item dengan total stok sudah menyentuh batas minimum.</p>
                        </div>
                    </div>
                    <div class="warehouse-list">
                        @forelse ($items->filter(fn ($item) => (int) ($item->minimum_stock ?? 0) > 0 && (int) ($item->total_stock ?? 0) <= (int) $item->minimum_stock)->take(8) as $item)
                            <div class="warehouse-item">
                                <strong>{{ $item->name }}</strong>
                                <div class="metric-row">
                                    <span class="metric-pill low-stock">{{ number_format((int) ($item->total_stock ?? 0), 0, ',', '.') }} {{ $item->unit }}</span>
                                    <span class="metric-pill">Minimum {{ number_format((int) $item->minimum_stock, 0, ',', '.') }} {{ $item->unit }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="warehouse-item">Belum ada barang yang menyentuh batas minimum.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-movement-form]');
            const typeSelect = document.querySelector('[data-movement-type]');

            if (!form || !typeSelect) {
                return;
            }

            const sourceWrap = form.querySelector('[data-source-wrap]');
            const destinationWrap = form.querySelector('[data-destination-wrap]');

            const syncMovementFields = () => {
                const type = typeSelect.value;

                if (sourceWrap) {
                    sourceWrap.style.display = type === 'receipt' ? 'none' : '';
                }

                if (destinationWrap) {
                    destinationWrap.style.display = type === 'issue' ? 'none' : '';
                }
            };

            typeSelect.addEventListener('change', syncMovementFields);
            syncMovementFields();
        })();
    </script>
@endpush
