@extends('basefeature::layouts.master')

@section('page_title', 'Master Tirta')
@section('page_subtitle', 'Kelola struktur area, golongan, pelanggan, sambungan, dan skema tarif tenant')

@push('styles')
    <style>
        .tirta-workspace {
            display: grid;
            gap: 24px;
        }
        .tab-shell,
        .tab-actions,
        .panel-stack,
        .list-stack,
        .tier-list {
            display: grid;
            gap: 16px;
        }
        .tab-shell {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            box-shadow: var(--shadow-sm);
        }
        .tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tab-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #ffffff;
            color: var(--muted);
            cursor: pointer;
            font-weight: 700;
            transition: border-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
        }
        .tab-button.active {
            color: var(--primary);
            border-color: color-mix(in srgb, var(--primary) 24%, var(--border));
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary) 14%, transparent);
            background: color-mix(in srgb, var(--primary) 8%, #ffffff);
        }
        .workspace-grid {
            display: grid;
            grid-template-columns: minmax(360px, 420px) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }
        .workspace-panel,
        .record-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
        }
        .workspace-panel {
            padding: 20px;
        }
        .panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }
        .panel-head h3 {
            margin: 0;
            font-size: 1rem;
        }
        .panel-head p {
            margin: 6px 0 0;
            font-size: 0.825rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .field-grid {
            display: grid;
            gap: 14px;
        }
        .field-grid.two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .checkbox-stack {
            display: grid;
            gap: 10px;
        }
        .checkbox-inline {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.875rem;
            color: var(--text);
        }
        .checkbox-inline input {
            margin-top: 3px;
        }
        .record-card {
            padding: 18px;
        }
        .record-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .record-title {
            margin: 0;
            font-size: 0.95rem;
            color: var(--text);
        }
        .record-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #f8fafc;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .record-copy {
            margin-top: 10px;
            font-size: 0.84rem;
            line-height: 1.7;
            color: var(--muted);
        }
        .details-edit {
            margin-top: 16px;
            border-top: 1px dashed var(--border);
            padding-top: 16px;
        }
        .details-edit summary {
            cursor: pointer;
            font-weight: 700;
            color: var(--primary);
            list-style: none;
        }
        .details-edit summary::-webkit-details-marker {
            display: none;
        }
        .hierarchy-box {
            margin-top: 18px;
            padding: 16px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .hierarchy-box h4 {
            margin: 0 0 6px;
            font-size: 0.92rem;
        }
        .hierarchy-box p {
            margin: 0 0 14px;
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.6;
        }
        .hierarchy-tree {
            display: grid;
            gap: 10px;
        }
        .hierarchy-node {
            position: relative;
            padding-left: 18px;
        }
        .hierarchy-node::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 0;
            bottom: -10px;
            width: 1px;
            background: #cbd5e1;
        }
        .hierarchy-node.root {
            padding-left: 0;
        }
        .hierarchy-node.root::before {
            display: none;
        }
        .hierarchy-node > .hierarchy-line {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 28px;
            font-size: 0.82rem;
            color: var(--text);
        }
        .hierarchy-node:not(.root) > .hierarchy-line::before {
            content: '';
            position: absolute;
            left: -12px;
            top: 50%;
            width: 12px;
            height: 1px;
            background: #cbd5e1;
        }
        .hierarchy-children {
            margin-top: 8px;
            display: grid;
            gap: 8px;
        }
        .hierarchy-tag {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .subtable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        .subtable th,
        .subtable td {
            padding: 10px 12px;
            font-size: 0.8rem;
            border-bottom: 1px solid var(--border);
        }
        .subtable th {
            background: #f8fafc;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .tier-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
            gap: 10px;
            align-items: end;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #f8fafc;
        }
        .tier-help {
            padding: 12px 14px;
            border-radius: 12px;
            background: #ecfeff;
            border: 1px solid #bae6fd;
            color: #0f172a;
            font-size: 0.82rem;
            line-height: 1.65;
        }
        .empty-state {
            padding: 24px;
            border: 1px dashed var(--border);
            border-radius: 16px;
            color: var(--muted);
            font-size: 0.875rem;
            background: #ffffff;
        }
        .legend-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
        }
        .legend-card {
            padding: 16px 18px;
            border-radius: 14px;
            background: #ffffff;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        .legend-card span {
            display: block;
            font-size: 0.78rem;
            color: var(--muted);
        }
        .legend-card strong {
            display: block;
            margin-top: 8px;
            font-size: 1rem;
            color: var(--text);
        }
        @media (max-width: 1279px) {
            .legend-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 1023px) {
            .workspace-grid,
            .field-grid.two,
            .tier-row {
                grid-template-columns: 1fr;
            }
            .legend-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        function tierEditor(initialMode, initialTiers) {
            const normalizeTier = (tier) => ({
                start_usage: tier?.start_usage ?? 1,
                end_usage: tier?.end_usage ?? '',
                charge_type: tier?.charge_type ?? 'per_m3',
                price: tier?.price ?? '',
            });

            return {
                mode: initialMode || 'flat',
                tiers: Array.isArray(initialTiers) && initialTiers.length > 0
                    ? initialTiers.map(normalizeTier)
                    : [normalizeTier({ start_usage: 1, end_usage: 5, charge_type: 'per_m3', price: '' })],
                addTier() {
                    this.tiers.push(normalizeTier({}));
                },
                removeTier(index) {
                    if (this.tiers.length === 1) {
                        this.tiers = [normalizeTier({})];
                        return;
                    }

                    this.tiers.splice(index, 1);
                },
            };
        }
    </script>
@endpush

@section('content')
    @php
        $serviceAreaTreeGroups = $serviceAreas->groupBy(fn ($area) => (string) ($area->parent_id ?? '__root__'));
        $renderServiceAreaTree = function ($parentId = '__root__', $depth = 0) use (&$renderServiceAreaTree, $serviceAreaTreeGroups) {
            $nodes = $serviceAreaTreeGroups->get((string) $parentId, collect());

            if ($nodes->isEmpty()) {
                return '';
            }

            $html = '<div class="'.($depth === 0 ? 'hierarchy-tree' : 'hierarchy-children').'">';

            foreach ($nodes as $node) {
                $html .= '<div class="hierarchy-node '.($depth === 0 ? 'root' : '').'">';
                $html .= '<div class="hierarchy-line">';
                $html .= '<span class="hierarchy-tag">'.e($node->areaTypeLabel()).'</span>';
                $html .= '<strong>'.e($node->name).'</strong>';

                if (filled($node->code)) {
                    $html .= '<span style="color: var(--muted); font-size: 0.75rem;">('.e($node->code).')</span>';
                }

                $html .= '</div>';
                $html .= $renderServiceAreaTree((string) $node->id, $depth + 1);
                $html .= '</div>';
            }

            $html .= '</div>';

            return $html;
        };
    @endphp

    <div class="page-grid tirta-workspace" x-data="{ tab: '{{ $activeTab }}' }">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if (! empty($areaScopeLabel))
            <div class="alert alert-info">
                Tampilan dibatasi ke area kerja <strong>{{ $areaScopeLabel }}</strong> dan turunannya.
            </div>
        @endif

        <section class="hero-card">
            <div>
                <span class="hero-badge"><i class="fas fa-screwdriver-wrench"></i> Operasional Tirta</span>
                <h2>Master Data Tenant</h2>
                <p>Tenant bisa atur sendiri struktur area seperti cabang, unit, atau rayon, plus pelanggan, sambungan langganan, dan skema tarif air langsung dari workspace ini.</p>
            </div>

            <div class="hero-meta">
                <div class="hero-meta-card">
                    <span>Nomor Sambungan</span>
                    <strong>4-6 Digit</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Relasi</span>
                    <strong>1 Pelanggan > Banyak Sambungan</strong>
                </div>
                <div class="hero-meta-card">
                    <span>Tarif</span>
                    <strong>Flat / Bertingkat</strong>
                </div>
            </div>
        </section>

        <section class="legend-grid">
            <div class="legend-card">
                <span>Area / Wilayah</span>
                <strong>{{ $stats['service_areas'] }}</strong>
            </div>
            <div class="legend-card">
                <span>Golongan</span>
                <strong>{{ $stats['service_categories'] }}</strong>
            </div>
            <div class="legend-card">
                <span>Pelanggan</span>
                <strong>{{ $stats['customers'] }}</strong>
            </div>
            <div class="legend-card">
                <span>Sambungan</span>
                <strong>{{ $stats['connections'] }}</strong>
            </div>
            <div class="legend-card">
                <span>Tarif Default</span>
                <strong>{{ $stats['default_tariff'] }}</strong>
            </div>
        </section>

        <section class="tab-shell">
            <div class="panel-head">
                <div>
                    <h3>Workbench Tirta</h3>
                    <p>Pilih panel kerja yang mau kamu kelola. Semua data ini tetap berada di database tenant aktif.</p>
                </div>
            </div>

            <div class="tab-bar">
                <button type="button" class="tab-button" :class="{ 'active': tab === 'service-areas' }" @click="tab = 'service-areas'">
                    <i class="fas fa-map"></i> Area / Wilayah
                </button>
                <button type="button" class="tab-button" :class="{ 'active': tab === 'service-categories' }" @click="tab = 'service-categories'">
                    <i class="fas fa-layer-group"></i> Golongan
                </button>
                <button type="button" class="tab-button" :class="{ 'active': tab === 'customers' }" @click="tab = 'customers'">
                    <i class="fas fa-address-card"></i> Pelanggan
                </button>
                <button type="button" class="tab-button" :class="{ 'active': tab === 'connections' }" @click="tab = 'connections'">
                    <i class="fas fa-faucet-drip"></i> Sambungan
                </button>
                <button type="button" class="tab-button" :class="{ 'active': tab === 'tariffs' }" @click="tab = 'tariffs'">
                    <i class="fas fa-file-invoice-dollar"></i> Skema Tarif
                </button>
            </div>
        </section>

        <section class="workspace-grid" x-show="tab === 'service-areas'" x-cloak>
            <div class="workspace-panel">
                <div class="panel-head">
                    <div>
                        <h3>Tambah Area / Wilayah</h3>
                        <p>Tenant bebas pakai struktur global, cabang, unit, atau rayon sesuai skala operasional masing-masing.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tenant.tirta.service-areas.store') }}" class="field-grid">
                    @csrf
                    <input type="hidden" name="tab" value="service-areas">

                    <div>
                        <label class="field-label" for="service-area-name">Nama Area</label>
                        <input id="service-area-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Cabang Barat atau Rayon Barat">
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="service-area-type">Tipe Area</label>
                            <select id="service-area-type" name="area_type">
                                @foreach ($serviceAreaTypeOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('area_type', 'rayon') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="service-area-code">Kode</label>
                            <input id="service-area-code" class="field" type="text" name="code" value="{{ old('code') }}" placeholder="RBRT">
                        </div>
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="service-area-parent">Induk Area</label>
                            <select id="service-area-parent" name="parent_id">
                                <option value="">Tanpa induk / level atas</option>
                                @foreach ($serviceAreas as $serviceArea)
                                    <option value="{{ $serviceArea->id }}" @selected(old('parent_id') === $serviceArea->id)>{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="service-area-sort-order">Urutan</label>
                            <input id="service-area-sort-order" class="field" type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}">
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="service-area-description">Deskripsi</label>
                        <textarea id="service-area-description" name="description" rows="3">{{ old('description') }}</textarea>
                    </div>

                    <label class="checkbox-inline">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span>Area aktif dan bisa dipakai di pelanggan, sambungan, dan assignment petugas.</span>
                    </label>

                    <button class="tenant-btn" type="submit">Simpan Area / Wilayah</button>
                </form>

                @if ($serviceAreas->isNotEmpty())
                    <div class="hierarchy-box">
                        <h4>Preview Struktur Area</h4>
                        <p>Struktur ini nunjukin urutan induk dan turunan. Cabang bisa punya unit atau rayon, dan tenant kecil tetap bisa pakai satu area umum saja.</p>
                        {!! $renderServiceAreaTree() !!}
                    </div>
                @endif
            </div>

            <div class="panel-stack">
                @forelse ($serviceAreas as $serviceArea)
                    <div class="record-card">
                        <div class="record-head">
                            <div>
                                <h4 class="record-title">{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</h4>
                                <div class="record-meta">
                                    <span class="meta-pill">{{ $serviceArea->areaTypeLabel() }}</span>
                                    <span class="meta-pill">{{ $serviceArea->code ?: 'Tanpa kode' }}</span>
                                    <span class="meta-pill">{{ $serviceArea->customers_count }} pelanggan</span>
                                    <span class="meta-pill">{{ $serviceArea->connections_count }} sambungan</span>
                                    <span class="meta-pill">{{ $serviceArea->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="record-copy">
                            @if ($serviceArea->parent)
                                Induk: {{ $serviceAreaOptions->get($serviceArea->parent->id, $serviceArea->parent->name) }}
                            @else
                                Level atas / tanpa induk
                            @endif
                            @if (filled($serviceArea->description))
                                <br>{{ $serviceArea->description }}
                            @endif
                        </div>

                        <details class="details-edit">
                            <summary>Edit Area / Wilayah</summary>
                            <form method="POST" action="{{ route('tenant.tirta.service-areas.update', $serviceArea->id) }}" class="field-grid" style="margin-top: 14px;">
                                @csrf
                                @method('PATCH')
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Nama</label>
                                        <input class="field" type="text" name="name" value="{{ $serviceArea->name }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Kode</label>
                                        <input class="field" type="text" name="code" value="{{ $serviceArea->code }}">
                                    </div>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Tipe Area</label>
                                        <select name="area_type">
                                            @foreach ($serviceAreaTypeOptions as $value => $label)
                                                <option value="{{ $value }}" @selected(($serviceArea->area_type ?? 'rayon') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label">Induk Area</label>
                                        <select name="parent_id">
                                            <option value="">Tanpa induk / level atas</option>
                                            @foreach ($serviceAreas as $candidateArea)
                                                @continue($candidateArea->id === $serviceArea->id)
                                                <option value="{{ $candidateArea->id }}" @selected($serviceArea->parent_id === $candidateArea->id)>{{ $serviceAreaOptions->get($candidateArea->id, $candidateArea->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Urutan</label>
                                        <input class="field" type="number" min="0" name="sort_order" value="{{ $serviceArea->sort_order }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Status</label>
                                        <select name="is_active">
                                            <option value="1" @selected($serviceArea->is_active)>Aktif</option>
                                            <option value="0" @selected(! $serviceArea->is_active)>Nonaktif</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Deskripsi</label>
                                    <textarea name="description" rows="3">{{ $serviceArea->description }}</textarea>
                                </div>
                                <button class="tenant-btn-secondary" type="submit">Update Area / Wilayah</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <div class="empty-state">Belum ada area / wilayah. Tambahkan struktur global, cabang, unit, atau rayon dulu supaya data pelanggan lebih rapi.</div>
                @endforelse
            </div>
        </section>

        <section class="workspace-grid" x-show="tab === 'service-categories'" x-cloak>
            <div class="workspace-panel">
                <div class="panel-head">
                    <div>
                        <h3>Tambah Golongan</h3>
                        <p>Pakai golongan untuk membedakan tarif seperti rumah tangga, toko, atau industri.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tenant.tirta.service-categories.store') }}" class="field-grid">
                    @csrf

                    <div>
                        <label class="field-label" for="service-category-name">Nama Golongan</label>
                        <input id="service-category-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Rumah Tangga">
                    </div>

                    <div>
                        <label class="field-label" for="service-category-code">Kode</label>
                        <input id="service-category-code" class="field" type="text" name="code" value="{{ old('code') }}" placeholder="RT">
                    </div>

                    <div>
                        <label class="field-label" for="service-category-description">Deskripsi</label>
                        <textarea id="service-category-description" name="description" rows="3">{{ old('description') }}</textarea>
                    </div>

                    <label class="checkbox-inline">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span>Golongan aktif dan tersedia untuk sambungan serta skema tarif.</span>
                    </label>

                    <button class="tenant-btn" type="submit">Simpan Golongan</button>
                </form>
            </div>

            <div class="panel-stack">
                @forelse ($serviceCategories as $serviceCategory)
                    <div class="record-card">
                        <div class="record-head">
                            <div>
                                <h4 class="record-title">{{ $serviceCategory->name }}</h4>
                                <div class="record-meta">
                                    <span class="meta-pill">{{ $serviceCategory->code ?: 'Tanpa kode' }}</span>
                                    <span class="meta-pill">{{ $serviceCategory->connections_count }} sambungan</span>
                                    <span class="meta-pill">{{ $serviceCategory->tariff_schemes_count }} tarif</span>
                                    <span class="meta-pill">{{ $serviceCategory->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </div>
                        </div>

                        @if (filled($serviceCategory->description))
                            <div class="record-copy">{{ $serviceCategory->description }}</div>
                        @endif

                        <details class="details-edit">
                            <summary>Edit Golongan</summary>
                            <form method="POST" action="{{ route('tenant.tirta.service-categories.update', $serviceCategory->id) }}" class="field-grid" style="margin-top: 14px;">
                                @csrf
                                @method('PATCH')
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Nama</label>
                                        <input class="field" type="text" name="name" value="{{ $serviceCategory->name }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Kode</label>
                                        <input class="field" type="text" name="code" value="{{ $serviceCategory->code }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Deskripsi</label>
                                    <textarea name="description" rows="3">{{ $serviceCategory->description }}</textarea>
                                </div>
                                <div>
                                    <label class="field-label">Status</label>
                                    <select name="is_active">
                                        <option value="1" @selected($serviceCategory->is_active)>Aktif</option>
                                        <option value="0" @selected(! $serviceCategory->is_active)>Nonaktif</option>
                                    </select>
                                </div>
                                <button class="tenant-btn-secondary" type="submit">Update Golongan</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <div class="empty-state">Belum ada golongan. Tambahkan dulu supaya skema tarif bisa dibedakan untuk rumah tangga, toko, industri, dan lainnya.</div>
                @endforelse
            </div>
        </section>

        <section class="workspace-grid" x-show="tab === 'customers'" x-cloak>
            <div class="workspace-panel">
                <div class="panel-head">
                    <div>
                        <h3>Tambah Pelanggan</h3>
                        <p>Core data pelanggan berisi nama, alamat, dan area default. Satu pelanggan nanti bisa punya lebih dari satu sambungan.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tenant.tirta.customers.store') }}" class="field-grid">
                    @csrf

                    <div>
                        <label class="field-label" for="customer-name">Nama Pelanggan</label>
                        <input id="customer-name" class="field" type="text" name="name" value="{{ old('name') }}">
                    </div>

                    <div>
                        <label class="field-label" for="customer-address">Alamat</label>
                        <textarea id="customer-address" name="address" rows="3">{{ old('address') }}</textarea>
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="customer-service-area">Area / Wilayah</label>
                            <select id="customer-service-area" name="service_area_id">
                                <option value="">Tanpa area</option>
                                @foreach ($serviceAreas as $serviceArea)
                                    <option value="{{ $serviceArea->id }}" @selected(old('service_area_id') === $serviceArea->id)>{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="customer-phone">No. HP</label>
                            <input id="customer-phone" class="field" type="text" name="phone" value="{{ old('phone') }}">
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="customer-email">Email</label>
                        <input id="customer-email" class="field" type="email" name="email" value="{{ old('email') }}">
                    </div>

                    <div>
                        <label class="field-label" for="customer-notes">Catatan</label>
                        <textarea id="customer-notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    </div>

                    <label class="checkbox-inline">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span>Pelanggan aktif dan bisa dipakai untuk sambungan baru.</span>
                    </label>

                    <button class="tenant-btn" type="submit">Simpan Pelanggan</button>
                </form>
            </div>

            <div class="panel-stack">
                @forelse ($customers as $customer)
                    <div class="record-card">
                        <div class="record-head">
                            <div>
                                <h4 class="record-title">{{ $customer->name }}</h4>
                                <div class="record-meta">
                                    <span class="meta-pill">{{ $customer->service_area_id ? $serviceAreaOptions->get($customer->service_area_id, $customer->serviceArea?->name ?? '-') : 'Tanpa area' }}</span>
                                    <span class="meta-pill">{{ $customer->connections_count }} sambungan</span>
                                    <span class="meta-pill">{{ $customer->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="record-copy">
                            {{ $customer->address }}
                            @if ($customer->phone)
                                <br>HP: {{ $customer->phone }}
                            @endif
                            @if ($customer->email)
                                <br>Email: {{ $customer->email }}
                            @endif
                        </div>

                        <details class="details-edit">
                            <summary>Edit Pelanggan</summary>
                            <form method="POST" action="{{ route('tenant.tirta.customers.update', $customer->id) }}" class="field-grid" style="margin-top: 14px;">
                                @csrf
                                @method('PATCH')
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Nama</label>
                                        <input class="field" type="text" name="name" value="{{ $customer->name }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Area / Wilayah</label>
                                        <select name="service_area_id">
                                            <option value="">Tanpa area</option>
                                            @foreach ($serviceAreas as $serviceArea)
                                                <option value="{{ $serviceArea->id }}" @selected($customer->service_area_id === $serviceArea->id)>{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Alamat</label>
                                    <textarea name="address" rows="3">{{ $customer->address }}</textarea>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">No. HP</label>
                                        <input class="field" type="text" name="phone" value="{{ $customer->phone }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Email</label>
                                        <input class="field" type="email" name="email" value="{{ $customer->email }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Catatan</label>
                                    <textarea name="notes" rows="3">{{ $customer->notes }}</textarea>
                                </div>
                                <div>
                                    <label class="field-label">Status</label>
                                    <select name="is_active">
                                        <option value="1" @selected($customer->is_active)>Aktif</option>
                                        <option value="0" @selected(! $customer->is_active)>Nonaktif</option>
                                    </select>
                                </div>
                                <button class="tenant-btn-secondary" type="submit">Update Pelanggan</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <div class="empty-state">Belum ada pelanggan. Tambahkan data pelanggan dulu sebelum bikin sambungan langganan.</div>
                @endforelse
            </div>
        </section>

        <section class="workspace-grid" x-show="tab === 'connections'" x-cloak>
            <div class="workspace-panel">
                <div class="panel-head">
                    <div>
                        <h3>Tambah Sambungan</h3>
                        <p>Nomor sambungan memakai 4-6 digit angka. Kalau dikosongkan, sistem akan generate otomatis 6 digit unik untuk tenant aktif.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tenant.tirta.connections.store') }}" class="field-grid">
                    @csrf

                    <div>
                        <label class="field-label" for="connection-customer">Pelanggan</label>
                        <select id="connection-customer" name="customer_id">
                            <option value="">Pilih pelanggan</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id') === $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="connection-number">Nomor Sambungan</label>
                            <input id="connection-number" class="field" type="text" name="service_number" value="{{ old('service_number') }}" placeholder="Opsional, 4-6 digit">
                        </div>
                        <div>
                            <label class="field-label" for="connection-meter-number">Nomor Meter</label>
                            <input id="connection-meter-number" class="field" type="text" name="meter_number" value="{{ old('meter_number') }}">
                        </div>
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="connection-service-area">Area / Wilayah</label>
                            <select id="connection-service-area" name="service_area_id">
                                <option value="">Ikuti data pelanggan / kosong</option>
                                @foreach ($serviceAreas as $serviceArea)
                                    <option value="{{ $serviceArea->id }}" @selected(old('service_area_id') === $serviceArea->id)>{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="connection-service-category">Golongan</label>
                            <select id="connection-service-category" name="service_category_id">
                                <option value="">Pilih golongan</option>
                                @foreach ($serviceCategories as $serviceCategory)
                                    <option value="{{ $serviceCategory->id }}" @selected(old('service_category_id') === $serviceCategory->id)>{{ $serviceCategory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="connection-tariff">Skema Tarif</label>
                            <select id="connection-tariff" name="tariff_scheme_id">
                                <option value="">Pilih skema tarif</option>
                                @foreach ($tariffSchemes as $tariffScheme)
                                    <option value="{{ $tariffScheme->id }}" @selected(old('tariff_scheme_id') === $tariffScheme->id)>{{ $tariffScheme->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="connection-status">Status</label>
                            <select id="connection-status" name="status">
                                @foreach ($connectionStatusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'requested') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="field-label" for="connection-installation-status">Workflow PSB</label>
                        <select id="connection-installation-status" name="installation_workflow_status">
                            @foreach ($installationWorkflowStatusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('installation_workflow_status', 'requested') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="field-label" for="connection-label">Label Sambungan</label>
                        <input id="connection-label" class="field" type="text" name="service_label" value="{{ old('service_label') }}" placeholder="Contoh: Ruko Depan / Rumah Induk">
                    </div>

                    <div>
                        <label class="field-label" for="connection-service-address">Alamat Layanan</label>
                        <textarea id="connection-service-address" name="service_address" rows="3">{{ old('service_address') }}</textarea>
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="connection-installed-at">Tanggal Pasang</label>
                            <input id="connection-installed-at" class="field" type="date" name="installed_at" value="{{ old('installed_at') }}">
                        </div>
                        <div>
                            <label class="field-label" for="connection-notes">Catatan</label>
                            <textarea id="connection-notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <button class="tenant-btn" type="submit">Simpan Sambungan</button>
                </form>
            </div>

            <div class="panel-stack">
                @php
                    $installationAllowInstallment = (bool) data_get($tenantSetting ?? null, 'billing_installation_allow_installment', false);
                    $installationDefaultInstallmentMonths = (int) data_get($tenantSetting ?? null, 'billing_installation_default_installment_months', 3);
                @endphp

                @forelse ($connections as $connection)
                    <div class="record-card">
                        <div class="record-head">
                            <div>
                                <h4 class="record-title">{{ $connection->service_number }} - {{ $connection->customer?->name ?? 'Pelanggan tidak ditemukan' }}</h4>
                                <div class="record-meta">
                                    <span class="meta-pill">{{ $connection->serviceCategory?->name ?? 'Tanpa golongan' }}</span>
                                    <span class="meta-pill">{{ $connection->service_area_id ? $serviceAreaOptions->get($connection->service_area_id, $connection->serviceArea?->name ?? '-') : 'Tanpa area' }}</span>
                                    <span class="meta-pill">{{ $connection->tariffScheme?->name ?? 'Tarif belum dipilih' }}</span>
                                    <span class="meta-pill">{{ $connectionStatusOptions[$connection->status] ?? ucfirst($connection->status) }}</span>
                                    <span class="meta-pill">{{ $installationWorkflowStatusOptions[$connection->installation_workflow_status ?: 'requested'] ?? ucfirst($connection->installation_workflow_status ?: 'requested') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="record-copy">
                            Meter: {{ $connection->meter_number ?: '-' }}
                            @if ($connection->service_label)
                                <br>Label: {{ $connection->service_label }}
                            @endif
                            @if ($connection->service_address)
                                <br>Alamat layanan: {{ $connection->service_address }}
                            @endif
                        </div>

                        <details class="details-edit">
                            <summary>Pasang Baru</summary>
                            <form
                                method="POST"
                                action="{{ route('tenant.tirta.service-connections.installation', $connection->id) }}"
                                class="field-grid"
                                style="margin-top: 14px;"
                                x-data="{ scheme: '{{ old('payment_scheme', $installationAllowInstallment ? 'installment' : 'cash') }}' }"
                            >
                                @csrf

                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Skema Bayar</label>
                                        <select name="payment_scheme" x-model="scheme">
                                            <option value="cash">Tunai</option>
                                            @if ($installationAllowInstallment)
                                                <option value="installment">Cicilan</option>
                                            @endif
                                        </select>
                                    </div>

                                    <div x-show="scheme === 'installment'" x-cloak>
                                        <label class="field-label">Bulan Cicilan</label>
                                        <input class="field" type="number" min="2" max="24" name="installment_months" value="{{ old('installment_months', $installationDefaultInstallmentMonths) }}">
                                    </div>
                                </div>

                                <button class="tenant-btn-secondary" type="submit">Buat Invoice Pasang Baru</button>
                            </form>
                        </details>

                        <details class="details-edit">
                            <summary>Edit Sambungan</summary>
                            <form method="POST" action="{{ route('tenant.tirta.connections.update', $connection->id) }}" class="field-grid" style="margin-top: 14px;">
                                @csrf
                                @method('PATCH')
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Pelanggan</label>
                                        <select name="customer_id">
                                            @foreach ($customers as $customer)
                                                <option value="{{ $customer->id }}" @selected($connection->customer_id === $customer->id)>{{ $customer->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label">Nomor Sambungan</label>
                                        <input class="field" type="text" name="service_number" value="{{ $connection->service_number }}">
                                    </div>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Area / Wilayah</label>
                                        <select name="service_area_id">
                                            <option value="">Tanpa area</option>
                                            @foreach ($serviceAreas as $serviceArea)
                                                <option value="{{ $serviceArea->id }}" @selected($connection->service_area_id === $serviceArea->id)>{{ $serviceAreaOptions->get($serviceArea->id, $serviceArea->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label">Golongan</label>
                                        <select name="service_category_id">
                                            <option value="">Tanpa golongan</option>
                                            @foreach ($serviceCategories as $serviceCategory)
                                                <option value="{{ $serviceCategory->id }}" @selected($connection->service_category_id === $serviceCategory->id)>{{ $serviceCategory->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Skema Tarif</label>
                                        <select name="tariff_scheme_id">
                                            <option value="">Tanpa skema</option>
                                            @foreach ($tariffSchemes as $tariffScheme)
                                                <option value="{{ $tariffScheme->id }}" @selected($connection->tariff_scheme_id === $tariffScheme->id)>{{ $tariffScheme->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label">Status</label>
                                        <select name="status">
                                            @foreach ($connectionStatusOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($connection->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Workflow PSB</label>
                                    <select name="installation_workflow_status">
                                        @foreach ($installationWorkflowStatusOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(($connection->installation_workflow_status ?: 'requested') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Nomor Meter</label>
                                        <input class="field" type="text" name="meter_number" value="{{ $connection->meter_number }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Tanggal Pasang</label>
                                        <input class="field" type="date" name="installed_at" value="{{ optional($connection->installed_at)->format('Y-m-d') }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="field-label">Label Sambungan</label>
                                    <input class="field" type="text" name="service_label" value="{{ $connection->service_label }}">
                                </div>
                                <div>
                                    <label class="field-label">Alamat Layanan</label>
                                    <textarea name="service_address" rows="3">{{ $connection->service_address }}</textarea>
                                </div>
                                <div>
                                    <label class="field-label">Catatan</label>
                                    <textarea name="notes" rows="3">{{ $connection->notes }}</textarea>
                                </div>
                                <button class="tenant-btn-secondary" type="submit">Update Sambungan</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <div class="empty-state">Belum ada sambungan. Tambahkan pelanggan terlebih dulu, lalu buat sambungan dengan golongan dan skema tarif yang sesuai.</div>
                @endforelse
            </div>
        </section>

        <section class="workspace-grid" x-show="tab === 'tariffs'" x-cloak>
            <div class="workspace-panel">
                <div class="panel-head">
                    <div>
                        <h3>Tambah Skema Tarif</h3>
                        <p>Skema tarif bisa flat atau bertingkat. Untuk pola “awal blok lalu lanjut per kubik”, gunakan mode bertingkat lalu set tier awal sebagai `flat block`.</p>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('tenant.tirta.tariffs.store') }}"
                    class="field-grid"
                    x-data="tierEditor('{{ old('calculation_mode', 'flat') }}', {{ \Illuminate\Support\Js::from(old('tiers', [['start_usage' => 1, 'end_usage' => 5, 'charge_type' => 'per_m3', 'price' => '']])) }})"
                >
                    @csrf

                    <div>
                        <label class="field-label" for="tariff-name">Nama Skema</label>
                        <input id="tariff-name" class="field" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Tarif Rumah Tangga A">
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="tariff-category">Golongan Terkait</label>
                            <select id="tariff-category" name="service_category_id">
                                <option value="">Semua golongan / umum</option>
                                @foreach ($serviceCategories as $serviceCategory)
                                    <option value="{{ $serviceCategory->id }}" @selected(old('service_category_id') === $serviceCategory->id)>{{ $serviceCategory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="field-label" for="tariff-mode">Mode Hitung</label>
                            <select id="tariff-mode" name="calculation_mode" x-model="mode">
                                <option value="flat">Flat per m3</option>
                                <option value="tiered">Bertingkat</option>
                            </select>
                        </div>
                    </div>

                    <div x-show="mode === 'flat'" x-cloak>
                        <label class="field-label" for="tariff-base-price">Harga per m3</label>
                        <input id="tariff-base-price" class="field" type="number" step="0.01" min="0" name="base_price_per_m3" value="{{ old('base_price_per_m3') }}" placeholder="2500">
                    </div>

                    <div class="field-grid two">
                        <div>
                            <label class="field-label" for="tariff-minimum-charge">Beban Minimum</label>
                            <input id="tariff-minimum-charge" class="field" type="number" step="0.01" min="0" name="minimum_charge" value="{{ old('minimum_charge', 0) }}">
                        </div>
                        <div>
                            <label class="field-label" for="tariff-admin-fee">Beban Tetap</label>
                            <input id="tariff-admin-fee" class="field" type="number" step="0.01" min="0" name="admin_fee" value="{{ old('admin_fee', 0) }}">
                        </div>
                    </div>

                    <div class="tier-help" x-show="mode === 'tiered'" x-cloak>
                        `Per m3` cocok untuk tarif bertingkat biasa. `Flat block` cocok untuk blok awal yang nominalnya fix, lalu blok selanjutnya tetap bisa dihitung per m3.
                    </div>

                    <div class="tier-list" x-show="mode === 'tiered'" x-cloak>
                        <template x-for="(tier, index) in tiers" :key="index">
                            <div class="tier-row">
                                <div>
                                    <label class="field-label">Mulai</label>
                                    <input class="field" type="number" min="1" :name="`tiers[${index}][start_usage]`" x-model="tier.start_usage">
                                </div>
                                <div>
                                    <label class="field-label">Sampai</label>
                                    <input class="field" type="number" min="1" :name="`tiers[${index}][end_usage]`" x-model="tier.end_usage" placeholder="Kosong = tanpa batas">
                                </div>
                                <div>
                                    <label class="field-label">Jenis Hitung</label>
                                    <select :name="`tiers[${index}][charge_type]`" x-model="tier.charge_type">
                                        <option value="per_m3">Per m3</option>
                                        <option value="flat_block">Flat block</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="field-label">Harga</label>
                                    <input class="field" type="number" step="0.01" min="0" :name="`tiers[${index}][price]`" x-model="tier.price">
                                </div>
                                <button class="tenant-btn-secondary" type="button" @click="removeTier(index)">Hapus</button>
                            </div>
                        </template>

                        <button class="tenant-btn-secondary" type="button" @click="addTier()">Tambah Tier</button>
                    </div>

                    <div>
                        <label class="field-label" for="tariff-notes">Catatan</label>
                        <textarea id="tariff-notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    </div>

                    <div class="checkbox-stack">
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))>
                            <span>Jadikan skema default tenant.</span>
                        </label>
                        <label class="checkbox-inline">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                            <span>Skema aktif dan bisa dipilih di sambungan baru.</span>
                        </label>
                    </div>

                    <button class="tenant-btn" type="submit">Simpan Skema Tarif</button>
                </form>
            </div>

            <div class="panel-stack">
                @forelse ($tariffSchemes as $tariffScheme)
                    <div class="record-card">
                        <div class="record-head">
                            <div>
                                <h4 class="record-title">{{ $tariffScheme->name }}</h4>
                                <div class="record-meta">
                                    <span class="meta-pill">{{ $tariffScheme->serviceCategory?->name ?? 'Umum' }}</span>
                                    <span class="meta-pill">{{ $tariffScheme->calculation_mode === 'flat' ? 'Flat per m3' : 'Bertingkat' }}</span>
                                    <span class="meta-pill">{{ $tariffScheme->connections_count }} sambungan</span>
                                    @if ($tariffScheme->is_default)
                                        <span class="meta-pill">Default</span>
                                    @endif
                                    <span class="meta-pill">{{ $tariffScheme->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="record-copy">
                            Beban minimum: Rp{{ number_format((float) $tariffScheme->minimum_charge, 0, ',', '.') }}
                            <br>Beban tetap: Rp{{ number_format((float) $tariffScheme->admin_fee, 0, ',', '.') }}
                            @if ($tariffScheme->calculation_mode === 'flat')
                                <br>Tarif flat: Rp{{ number_format((float) $tariffScheme->base_price_per_m3, 0, ',', '.') }}/m3
                            @endif
                            @if ($tariffScheme->notes)
                                <br>{{ $tariffScheme->notes }}
                            @endif
                        </div>

                        @if ($tariffScheme->calculation_mode === 'tiered')
                            <table class="subtable">
                                <thead>
                                    <tr>
                                        <th>Mulai</th>
                                        <th>Sampai</th>
                                        <th>Jenis</th>
                                        <th>Harga</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tariffScheme->tiers as $tier)
                                        <tr>
                                            <td>{{ $tier->start_usage }}</td>
                                            <td>{{ $tier->end_usage ?? 'Tanpa batas' }}</td>
                                            <td>{{ $tier->charge_type === 'flat_block' ? 'Flat block' : 'Per m3' }}</td>
                                            <td>Rp{{ number_format((float) $tier->price, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <details class="details-edit">
                            <summary>Edit Skema Tarif</summary>
                            <form
                                method="POST"
                                action="{{ route('tenant.tirta.tariffs.update', $tariffScheme->id) }}"
                                class="field-grid"
                                style="margin-top: 14px;"
                                x-data="tierEditor('{{ $tariffScheme->calculation_mode }}', {{ \Illuminate\Support\Js::from($tariffScheme->tiers->map(fn ($tier) => ['start_usage' => $tier->start_usage, 'end_usage' => $tier->end_usage, 'charge_type' => $tier->charge_type, 'price' => (float) $tier->price])->values()) }})"
                            >
                                @csrf
                                @method('PATCH')
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Nama</label>
                                        <input class="field" type="text" name="name" value="{{ $tariffScheme->name }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Golongan</label>
                                        <select name="service_category_id">
                                            <option value="">Semua golongan / umum</option>
                                            @foreach ($serviceCategories as $serviceCategory)
                                                <option value="{{ $serviceCategory->id }}" @selected($tariffScheme->service_category_id === $serviceCategory->id)>{{ $serviceCategory->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Mode Hitung</label>
                                        <select name="calculation_mode" x-model="mode">
                                            <option value="flat">Flat per m3</option>
                                            <option value="tiered">Bertingkat</option>
                                        </select>
                                    </div>
                                    <div x-show="mode === 'flat'" x-cloak>
                                        <label class="field-label">Harga per m3</label>
                                        <input class="field" type="number" step="0.01" min="0" name="base_price_per_m3" value="{{ $tariffScheme->base_price_per_m3 }}">
                                    </div>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Beban Minimum</label>
                                        <input class="field" type="number" step="0.01" min="0" name="minimum_charge" value="{{ $tariffScheme->minimum_charge }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Beban Tetap</label>
                                        <input class="field" type="number" step="0.01" min="0" name="admin_fee" value="{{ $tariffScheme->admin_fee }}">
                                    </div>
                                </div>
                                <div class="tier-list" x-show="mode === 'tiered'" x-cloak>
                                    <template x-for="(tier, index) in tiers" :key="index">
                                        <div class="tier-row">
                                            <div>
                                                <label class="field-label">Mulai</label>
                                                <input class="field" type="number" min="1" :name="`tiers[${index}][start_usage]`" x-model="tier.start_usage">
                                            </div>
                                            <div>
                                                <label class="field-label">Sampai</label>
                                                <input class="field" type="number" min="1" :name="`tiers[${index}][end_usage]`" x-model="tier.end_usage">
                                            </div>
                                            <div>
                                                <label class="field-label">Jenis Hitung</label>
                                                <select :name="`tiers[${index}][charge_type]`" x-model="tier.charge_type">
                                                    <option value="per_m3">Per m3</option>
                                                    <option value="flat_block">Flat block</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="field-label">Harga</label>
                                                <input class="field" type="number" step="0.01" min="0" :name="`tiers[${index}][price]`" x-model="tier.price">
                                            </div>
                                            <button class="tenant-btn-secondary" type="button" @click="removeTier(index)">Hapus</button>
                                        </div>
                                    </template>

                                    <button class="tenant-btn-secondary" type="button" @click="addTier()">Tambah Tier</button>
                                </div>
                                <div>
                                    <label class="field-label">Catatan</label>
                                    <textarea name="notes" rows="3">{{ $tariffScheme->notes }}</textarea>
                                </div>
                                <div class="field-grid two">
                                    <div>
                                        <label class="field-label">Status</label>
                                        <select name="is_active">
                                            <option value="1" @selected($tariffScheme->is_active)>Aktif</option>
                                            <option value="0" @selected(! $tariffScheme->is_active)>Nonaktif</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="field-label">Default</label>
                                        <select name="is_default">
                                            <option value="1" @selected($tariffScheme->is_default)>Ya</option>
                                            <option value="0" @selected(! $tariffScheme->is_default)>Tidak</option>
                                        </select>
                                    </div>
                                </div>
                                <button class="tenant-btn-secondary" type="submit">Update Skema Tarif</button>
                            </form>
                        </details>
                    </div>
                @empty
                    <div class="empty-state">Belum ada skema tarif. Tambahkan skema flat atau bertingkat supaya sambungan bisa dihitung sesuai golongan tenant.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
