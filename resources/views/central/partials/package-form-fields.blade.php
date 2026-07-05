@php
    $namePrefix = $namePrefix ?? '';
    $package = $package ?? [];
    $showCodeField = $showCodeField ?? false;
    $fieldName = static fn (string $field): string => $namePrefix !== '' ? "{$namePrefix}[{$field}]" : $field;
    $oldKey = static fn (string $field): string => $namePrefix !== '' ? "{$namePrefix}.{$field}" : $field;
    $fieldValue = static fn (string $key, mixed $fallback = null): mixed => data_get($package, $key, $fallback);
    $oldValue = static fn (string $key, mixed $fallback = null): mixed => old($oldKey($key), $fallback);

    $enabledFeatureCount = collect((array) $oldValue('features', $fieldValue('features', [])))->filter()->count();
    $selectedModules = (array) $oldValue('modules', $fieldValue('modules', []));
    $billingValues = [
        'setup_fee' => [
            'enabled' => (bool) $oldValue('billing_components.setup_fee.enabled', $fieldValue('billing_components.setup_fee.enabled', false)),
            'amount' => (int) $oldValue('billing_components.setup_fee.amount', $fieldValue('billing_components.setup_fee.amount', 0)),
        ],
        'monthly_base' => [
            'enabled' => (bool) $oldValue('billing_components.monthly_base.enabled', $fieldValue('billing_components.monthly_base.enabled', false)),
            'amount' => (int) $oldValue('billing_components.monthly_base.amount', $fieldValue('billing_components.monthly_base.amount', 0)),
        ],
        'per_customer' => [
            'enabled' => (bool) $oldValue('billing_components.per_customer.enabled', $fieldValue('billing_components.per_customer.enabled', false)),
            'amount' => (int) $oldValue('billing_components.per_customer.amount', $fieldValue('billing_components.per_customer.amount', 0)),
            'sample_qty' => (int) $oldValue('billing_components.per_customer.sample_qty', $fieldValue('billing_components.per_customer.sample_qty', 0)),
        ],
        'per_success_transaction' => [
            'enabled' => (bool) $oldValue('billing_components.per_success_transaction.enabled', $fieldValue('billing_components.per_success_transaction.enabled', false)),
            'amount' => (int) $oldValue('billing_components.per_success_transaction.amount', $fieldValue('billing_components.per_success_transaction.amount', 0)),
            'sample_qty' => (int) $oldValue('billing_components.per_success_transaction.sample_qty', $fieldValue('billing_components.per_success_transaction.sample_qty', 0)),
        ],
        'per_checkout' => [
            'enabled' => (bool) $oldValue('billing_components.per_checkout.enabled', $fieldValue('billing_components.per_checkout.enabled', false)),
            'amount' => (int) $oldValue('billing_components.per_checkout.amount', $fieldValue('billing_components.per_checkout.amount', 0)),
            'sample_qty' => (int) $oldValue('billing_components.per_checkout.sample_qty', $fieldValue('billing_components.per_checkout.sample_qty', 0)),
        ],
        'transaction_percentage' => [
            'enabled' => (bool) $oldValue('billing_components.transaction_percentage.enabled', $fieldValue('billing_components.transaction_percentage.enabled', false)),
            'rate' => (float) $oldValue('billing_components.transaction_percentage.rate', $fieldValue('billing_components.transaction_percentage.rate', 0)),
            'sample_amount' => (int) $oldValue('billing_components.transaction_percentage.sample_amount', $fieldValue('billing_components.transaction_percentage.sample_amount', 0)),
        ],
    ];
    $estimatedMonthlyBill = 0;
    $estimatedSetupFee = $billingValues['setup_fee']['enabled'] ? $billingValues['setup_fee']['amount'] : 0;
    if ($billingValues['monthly_base']['enabled']) {
        $estimatedMonthlyBill += $billingValues['monthly_base']['amount'];
    }
    if ($billingValues['per_customer']['enabled']) {
        $estimatedMonthlyBill += $billingValues['per_customer']['amount'] * $billingValues['per_customer']['sample_qty'];
    }
    if ($billingValues['per_success_transaction']['enabled']) {
        $estimatedMonthlyBill += $billingValues['per_success_transaction']['amount'] * $billingValues['per_success_transaction']['sample_qty'];
    }
    if ($billingValues['per_checkout']['enabled']) {
        $estimatedMonthlyBill += $billingValues['per_checkout']['amount'] * $billingValues['per_checkout']['sample_qty'];
    }
    if ($billingValues['transaction_percentage']['enabled']) {
        $estimatedMonthlyBill += (int) round(($billingValues['transaction_percentage']['sample_amount'] * $billingValues['transaction_percentage']['rate']) / 100);
    }
    $estimatedFirstInvoice = $estimatedSetupFee + $estimatedMonthlyBill;
@endphp

<div class="package-editor-main" data-package-tab-root="{{ $formId }}">
    <div class="package-tab-nav" role="tablist" aria-label="Package sections">
        <button class="package-tab-btn is-active" type="button" role="tab" aria-selected="true" data-package-tab-trigger="general">General</button>
        <button class="package-tab-btn" type="button" role="tab" aria-selected="false" data-package-tab-trigger="limits">Limits</button>
        <button class="package-tab-btn" type="button" role="tab" aria-selected="false" data-package-tab-trigger="features">Features</button>
        <button class="package-tab-btn" type="button" role="tab" aria-selected="false" data-package-tab-trigger="billing">Billing</button>
        <button class="package-tab-btn" type="button" role="tab" aria-selected="false" data-package-tab-trigger="modules">Modules</button>
    </div>

    <div class="package-tab-panels">
        <section class="form-block package-form-card package-tab-panel is-active" data-package-tab-panel="general">
            <div class="package-section-intro">
                <h4 class="form-title">General Setup</h4>
                <p>Isi identitas dasar package, harga, urutan tampil, dan status publikasinya.</p>
            </div>

            <div class="form-stack">
                @if ($showCodeField)
                    <div>
                        <label class="field-label" for="{{ $formId }}-code">Kode Package</label>
                        <input
                            id="{{ $formId }}-code"
                            class="field"
                            type="text"
                            name="{{ $fieldName('code') }}"
                            value="{{ $oldValue('code', $fieldValue('code')) }}"
                            placeholder="mis. pro, premium, bisnis-plus"
                        >
                        <span class="package-field-note">Dipakai sebagai identifier internal dan mapping tenant.</span>
                    </div>
                @endif

                <div class="package-form-grid two-col">
                    <div>
                        <label class="field-label" for="{{ $formId }}-label">Nama Package</label>
                        <input
                            id="{{ $formId }}-label"
                            class="field"
                            type="text"
                            name="{{ $fieldName('label') }}"
                            value="{{ $oldValue('label', $fieldValue('label')) }}"
                            data-package-preview-label
                            required
                        >
                    </div>

                    <div>
                        <label class="field-label" for="{{ $formId }}-sort">Sort Order</label>
                        <input
                            id="{{ $formId }}-sort"
                            class="field"
                            type="number"
                            min="1"
                            max="100"
                            name="{{ $fieldName('sort_order') }}"
                            value="{{ $oldValue('sort_order', $fieldValue('sort_order', 10)) }}"
                            required
                        >
                    </div>
                </div>

                <div>
                    <label class="field-label" for="{{ $formId }}-description">Deskripsi</label>
                    <textarea
                        id="{{ $formId }}-description"
                        name="{{ $fieldName('description') }}"
                        rows="3"
                        data-package-preview-description
                    >{{ $oldValue('description', $fieldValue('description')) }}</textarea>
                </div>

                <div class="package-form-grid two-col">
                    <div>
                        <label class="field-label" for="{{ $formId }}-price">Harga / Bulan</label>
                        <input
                            id="{{ $formId }}-price"
                            class="field"
                            type="number"
                            min="0"
                            name="{{ $fieldName('price_monthly') }}"
                            value="{{ $oldValue('price_monthly', $fieldValue('price_monthly', 0)) }}"
                            data-package-preview-legacy-price
                            required
                        >
                    </div>

                    <div>
                        <label class="field-label" for="{{ $formId }}-cycle">Billing Cycle</label>
                        <select id="{{ $formId }}-cycle" name="{{ $fieldName('billing_cycle') }}">
                            @foreach ($billingCycleLabels as $billingCycle => $billingCycleLabel)
                                <option value="{{ $billingCycle }}" @selected($oldValue('billing_cycle', $fieldValue('billing_cycle', 'monthly')) === $billingCycle)>
                                    {{ $billingCycleLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="package-form-grid two-col">
                    <label class="checkbox-row">
                        <input type="checkbox" name="{{ $fieldName('enabled') }}" value="1" @checked((bool) $oldValue('enabled', $fieldValue('enabled', true)))>
                        <span>
                            <strong style="display: block;">Aktif</strong>
                            <span class="muted">Package bisa dipakai</span>
                        </span>
                    </label>

                    <label class="checkbox-row">
                        <input type="checkbox" name="{{ $fieldName('highlight') }}" value="1" @checked((bool) $oldValue('highlight', $fieldValue('highlight', false)))>
                        <span>
                            <strong style="display: block;">Highlight</strong>
                            <span class="muted">Tampilkan sebagai package unggulan</span>
                        </span>
                    </label>
                </div>

                <label class="checkbox-row">
                    <input type="checkbox" name="{{ $fieldName('make_default') }}" value="1" @checked((bool) $oldValue('make_default', $fieldValue('is_default', false)))>
                    <span>
                        <strong style="display: block;">Jadikan Default</strong>
                        <span class="muted">Tenant baru otomatis pakai package ini</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="form-block package-form-card package-tab-panel" data-package-tab-panel="limits" hidden>
            <div class="package-section-intro">
                <h4 class="form-title">Operational Limits</h4>
                <p>Tentukan batas kapasitas utama yang dipakai tenant pada package ini.</p>
            </div>

            <div class="package-form-grid two-col">
                <div>
                    <label class="field-label" for="{{ $formId }}-admins">Max Admin Users</label>
                    <input id="{{ $formId }}-admins" class="field" type="number" min="1" name="{{ $fieldName('limits') }}[max_admin_users]" value="{{ $oldValue('limits.max_admin_users', $fieldValue('limits.max_admin_users')) }}" placeholder="Kosongkan untuk unlimited">
                </div>
                <div>
                    <label class="field-label" for="{{ $formId }}-staff">Max Staff Users</label>
                    <input id="{{ $formId }}-staff" class="field" type="number" min="1" name="{{ $fieldName('limits') }}[max_staff_users]" value="{{ $oldValue('limits.max_staff_users', $fieldValue('limits.max_staff_users')) }}" placeholder="Kosongkan untuk unlimited">
                </div>
                <div>
                    <label class="field-label" for="{{ $formId }}-customers">Max Customers</label>
                    <input id="{{ $formId }}-customers" class="field" type="number" min="1" name="{{ $fieldName('limits') }}[max_customers]" value="{{ $oldValue('limits.max_customers', $fieldValue('limits.max_customers')) }}" placeholder="Kosongkan untuk unlimited">
                </div>
                <div>
                    <label class="field-label" for="{{ $formId }}-transactions">Max Monthly Transactions</label>
                    <input id="{{ $formId }}-transactions" class="field" type="number" min="1" name="{{ $fieldName('limits') }}[max_monthly_transactions]" value="{{ $oldValue('limits.max_monthly_transactions', $fieldValue('limits.max_monthly_transactions')) }}" placeholder="Kosongkan untuk unlimited">
                </div>
            </div>
        </section>

        <section class="form-block package-form-card package-tab-panel" data-package-tab-panel="features" hidden>
            <div class="package-section-intro">
                <h4 class="form-title">Feature Flags</h4>
                <p>Aktifkan fitur premium yang boleh dipakai tenant pada package ini.</p>
            </div>

            <div class="package-check-grid">
                @foreach ($featureCatalog as $featureKey => $feature)
                    @php
                        $checkedFeature = (bool) $oldValue('features.' . $featureKey, $fieldValue('features.' . $featureKey, false));
                    @endphp
                    <label class="module-item{{ $checkedFeature ? ' module-item-active' : '' }}">
                        <input
                            type="checkbox"
                            name="{{ $fieldName('features') }}[{{ $featureKey }}]"
                            value="1"
                            @checked($checkedFeature)
                            style="margin-top: 3px;"
                        >
                        <span class="module-copy">
                            <strong>{{ $feature['label'] }}</strong>
                            <span>{{ $feature['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="form-block package-form-card package-tab-panel" data-package-tab-panel="billing" hidden>
            <div class="package-section-intro">
                <h4 class="form-title">Billing Components</h4>
                <p>Tambahkan komponen tagihan opsional tanpa mengubah harga package lama yang sudah ada.</p>
            </div>

            <div class="package-billing-stack">
                <div class="package-billing-card">
                    <div class="package-billing-head">
                        <div>
                            <strong>{{ $billingComponentCatalog['setup_fee']['label'] }}</strong>
                            <span>{{ $billingComponentCatalog['setup_fee']['description'] }}</span>
                        </div>
                        <label class="switch-row">
                            <input type="checkbox" name="{{ $fieldName('billing_components') }}[setup_fee][enabled]" value="1" @checked($billingValues['setup_fee']['enabled']) data-billing-enabled="setup_fee">
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="package-form-grid two-col">
                        <div>
                            <label class="field-label" for="{{ $formId }}-setup-fee">Biaya Setup</label>
                            <input id="{{ $formId }}-setup-fee" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[setup_fee][amount]" value="{{ $billingValues['setup_fee']['amount'] }}" data-billing-amount="setup_fee">
                        </div>
                    </div>
                </div>

                <div class="package-billing-card">
                    <div class="package-billing-head">
                        <div>
                            <strong>{{ $billingComponentCatalog['monthly_base']['label'] }}</strong>
                            <span>{{ $billingComponentCatalog['monthly_base']['description'] }}</span>
                        </div>
                        <label class="switch-row">
                            <input type="checkbox" name="{{ $fieldName('billing_components') }}[monthly_base][enabled]" value="1" @checked($billingValues['monthly_base']['enabled']) data-billing-enabled="monthly_base">
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="package-form-grid two-col">
                        <div>
                            <label class="field-label" for="{{ $formId }}-monthly-base">Biaya Bulanan</label>
                            <input id="{{ $formId }}-monthly-base" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[monthly_base][amount]" value="{{ $billingValues['monthly_base']['amount'] }}" data-billing-amount="monthly_base">
                        </div>
                    </div>
                </div>

                <div class="package-billing-card">
                    <div class="package-billing-head">
                        <div>
                            <strong>{{ $billingComponentCatalog['per_customer']['label'] }}</strong>
                            <span>{{ $billingComponentCatalog['per_customer']['description'] }}</span>
                        </div>
                        <label class="switch-row">
                            <input type="checkbox" name="{{ $fieldName('billing_components') }}[per_customer][enabled]" value="1" @checked($billingValues['per_customer']['enabled']) data-billing-enabled="per_customer">
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="package-form-grid two-col">
                        <div>
                            <label class="field-label" for="{{ $formId }}-per-customer-amount">Tarif / Pelanggan</label>
                            <input id="{{ $formId }}-per-customer-amount" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[per_customer][amount]" value="{{ $billingValues['per_customer']['amount'] }}" data-billing-amount="per_customer">
                        </div>
                        <div>
                            <label class="field-label" for="{{ $formId }}-per-customer-sample">Estimasi Jumlah Pelanggan</label>
                            <input id="{{ $formId }}-per-customer-sample" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[per_customer][sample_qty]" value="{{ $billingValues['per_customer']['sample_qty'] }}" data-billing-sample-qty="per_customer">
                        </div>
                    </div>
                </div>

                <div class="package-billing-card">
                    <div class="package-billing-head">
                        <div>
                            <strong>{{ $billingComponentCatalog['per_success_transaction']['label'] }}</strong>
                            <span>{{ $billingComponentCatalog['per_success_transaction']['description'] }}</span>
                        </div>
                        <label class="switch-row">
                            <input type="checkbox" name="{{ $fieldName('billing_components') }}[per_success_transaction][enabled]" value="1" @checked($billingValues['per_success_transaction']['enabled']) data-billing-enabled="per_success_transaction">
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="package-form-grid two-col">
                        <div>
                            <label class="field-label" for="{{ $formId }}-per-success-amount">Tarif / Transaksi</label>
                            <input id="{{ $formId }}-per-success-amount" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[per_success_transaction][amount]" value="{{ $billingValues['per_success_transaction']['amount'] }}" data-billing-amount="per_success_transaction">
                        </div>
                        <div>
                            <label class="field-label" for="{{ $formId }}-per-success-sample">Estimasi Transaksi Berhasil</label>
                            <input id="{{ $formId }}-per-success-sample" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[per_success_transaction][sample_qty]" value="{{ $billingValues['per_success_transaction']['sample_qty'] }}" data-billing-sample-qty="per_success_transaction">
                        </div>
                    </div>
                </div>

                <div class="package-billing-card">
                    <div class="package-billing-head">
                        <div>
                            <strong>{{ $billingComponentCatalog['per_checkout']['label'] }}</strong>
                            <span>{{ $billingComponentCatalog['per_checkout']['description'] }}</span>
                        </div>
                        <label class="switch-row">
                            <input type="checkbox" name="{{ $fieldName('billing_components') }}[per_checkout][enabled]" value="1" @checked($billingValues['per_checkout']['enabled']) data-billing-enabled="per_checkout">
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="package-form-grid two-col">
                        <div>
                            <label class="field-label" for="{{ $formId }}-per-checkout-amount">Tarif / Checkout</label>
                            <input id="{{ $formId }}-per-checkout-amount" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[per_checkout][amount]" value="{{ $billingValues['per_checkout']['amount'] }}" data-billing-amount="per_checkout">
                        </div>
                        <div>
                            <label class="field-label" for="{{ $formId }}-per-checkout-sample">Estimasi Checkout</label>
                            <input id="{{ $formId }}-per-checkout-sample" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[per_checkout][sample_qty]" value="{{ $billingValues['per_checkout']['sample_qty'] }}" data-billing-sample-qty="per_checkout">
                        </div>
                    </div>
                </div>

                <div class="package-billing-card">
                    <div class="package-billing-head">
                        <div>
                            <strong>{{ $billingComponentCatalog['transaction_percentage']['label'] }}</strong>
                            <span>{{ $billingComponentCatalog['transaction_percentage']['description'] }}</span>
                        </div>
                        <label class="switch-row">
                            <input type="checkbox" name="{{ $fieldName('billing_components') }}[transaction_percentage][enabled]" value="1" @checked($billingValues['transaction_percentage']['enabled']) data-billing-enabled="transaction_percentage">
                            <span>Aktif</span>
                        </label>
                    </div>
                    <div class="package-form-grid two-col">
                        <div>
                            <label class="field-label" for="{{ $formId }}-transaction-rate">Persentase</label>
                            <input id="{{ $formId }}-transaction-rate" class="field" type="number" min="0" step="0.01" name="{{ $fieldName('billing_components') }}[transaction_percentage][rate]" value="{{ $billingValues['transaction_percentage']['rate'] }}" data-billing-rate="transaction_percentage">
                        </div>
                        <div>
                            <label class="field-label" for="{{ $formId }}-transaction-sample">Estimasi Nominal Transaksi</label>
                            <input id="{{ $formId }}-transaction-sample" class="field" type="number" min="0" name="{{ $fieldName('billing_components') }}[transaction_percentage][sample_amount]" value="{{ $billingValues['transaction_percentage']['sample_amount'] }}" data-billing-sample-amount="transaction_percentage">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="form-block package-form-card package-tab-panel" data-package-tab-panel="modules" hidden>
            <div class="package-section-intro">
                <h4 class="form-title">Allowed Modules</h4>
                <p>Pilih modul yang boleh aktif untuk tenant dengan package ini.</p>
            </div>

            <div class="package-check-grid">
                @foreach ($moduleCatalog as $module)
                    @php
                        $checkedModule = in_array($module['name'], $selectedModules, true);
                    @endphp
                    <label class="module-item{{ $checkedModule ? ' module-item-active' : '' }}">
                        <input
                            type="checkbox"
                            name="{{ $fieldName('modules') }}[]"
                            value="{{ $module['name'] }}"
                            @checked($checkedModule)
                            @disabled($module['required'])
                            style="margin-top: 3px;"
                        >
                        <span class="module-copy">
                            <strong>{{ $module['label'] }}</strong>
                            <span>{{ $module['description'] }}</span>
                            <span class="badge-row">
                                @if ($module['required'])
                                    <span class="badge">Required</span>
                                @endif
                                @if ($module['recommended'])
                                    <span class="badge">{{ ucfirst($platformType) }}</span>
                                @endif
                                <span class="badge badge-neutral">{{ $module['installed'] ? 'Installed' : 'Not Installed' }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>
    </div>
</div>

<aside class="package-editor-aside">
    <div class="package-summary-card">
        <span class="package-summary-label">Package Preview</span>
        <strong data-package-preview-label-node>{{ $oldValue('label', $fieldValue('label', 'Package Baru')) ?: 'Package Baru' }}</strong>
        <p data-package-preview-description-node>{{ $oldValue('description', $fieldValue('description', 'Belum ada deskripsi package.')) ?: 'Belum ada deskripsi package.' }}</p>
    </div>

    <div class="package-summary-grid">
        <div class="package-summary-metric">
            <span>Legacy Price</span>
            <strong data-package-preview-legacy-price-node>Rp{{ number_format((int) $oldValue('price_monthly', $fieldValue('price_monthly', 0)), 0, ',', '.') }}</strong>
        </div>
        <div class="package-summary-metric">
            <span>Cycle</span>
            <strong>{{ $billingCycleLabels[$oldValue('billing_cycle', $fieldValue('billing_cycle', 'monthly'))] ?? 'Bulanan' }}</strong>
        </div>
        <div class="package-summary-metric">
            <span>Features</span>
            <strong>{{ $enabledFeatureCount }}</strong>
        </div>
        <div class="package-summary-metric">
            <span>Modules</span>
            <strong>{{ count($selectedModules) }}</strong>
        </div>
        <div class="package-summary-metric">
            <span>Setup Fee</span>
            <strong data-billing-summary-setup>Rp{{ number_format($estimatedSetupFee, 0, ',', '.') }}</strong>
        </div>
        <div class="package-summary-metric">
            <span>Total / Bulan</span>
            <strong data-billing-summary-monthly>Rp{{ number_format($estimatedMonthlyBill, 0, ',', '.') }}</strong>
        </div>
        <div class="package-summary-metric">
            <span>Invoice Pertama</span>
            <strong data-billing-summary-first>Rp{{ number_format($estimatedFirstInvoice, 0, ',', '.') }}</strong>
        </div>
        <div class="package-summary-metric">
            <span>Billing Rules</span>
            <strong data-billing-summary-active-rules>{{ collect($billingValues)->filter(fn (array $component) => (bool) ($component['enabled'] ?? false))->count() }}</strong>
        </div>
    </div>

    <div class="package-summary-card muted">
        <span class="package-summary-label">Catatan</span>
        <ul class="package-summary-list">
            <li>Kosongkan limit untuk mode unlimited.</li>
            <li>`BaseFeature` tetap ikut sebagai modul dasar.</li>
            <li>Checklist default akan assign package ini ke tenant baru.</li>
            <li>Billing components ini additive dan tidak mengubah field package lama.</li>
        </ul>
    </div>
</aside>
