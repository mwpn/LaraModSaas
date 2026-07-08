<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\Concerns\InteractsWithTirtaAreaScope;
use App\Http\Controllers\Controller;
use App\Models\Tirta\Customer;
use App\Models\Tirta\ServiceArea;
use App\Models\Tirta\ServiceCategory;
use App\Models\Tirta\ServiceConnection;
use App\Models\Tirta\TariffScheme;
use App\Models\User;
use App\Services\Tirta\TirtaConnectionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BaseFeature\Models\TenantSetting;

class TirtaMasterDataController extends Controller
{
    use InteractsWithTirtaAreaScope;

    public function index(Request $request): View
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $serviceAreas = ServiceArea::query()
            ->with('parent')
            ->withCount(['customers', 'connections'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereIn('id', $this->tirtaAllowedAreaIds()->all()))
            ->orderByDesc('is_active')
            ->orderByRaw("case area_type when 'branch' then 1 when 'unit' then 2 when 'rayon' then 3 else 4 end")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $serviceAreaOptions = $this->serviceAreaOptions($serviceAreas);

        $serviceCategories = ServiceCategory::query()
            ->withCount(['connections', 'tariffSchemes'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->with(['serviceArea.parent'])
            ->withCount('connections')
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaAreaScope($query))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $connections = ServiceConnection::query()
            ->with(['customer', 'serviceArea.parent', 'serviceCategory', 'tariffScheme'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaConnectionScope($query))
            ->orderBy('service_number')
            ->get();

        $tariffSchemes = TariffScheme::query()
            ->with(['serviceCategory', 'tiers', 'connections'])
            ->withCount('connections')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('basefeature::tirta.master-data', [
            'activeTab' => (string) $request->query('tab', 'service-areas'),
            'serviceAreas' => $serviceAreas,
            'serviceAreaOptions' => $serviceAreaOptions,
            'serviceAreaTypeOptions' => $this->serviceAreaTypeOptions(),
            'serviceCategories' => $serviceCategories,
            'customers' => $customers,
            'connections' => $connections,
            'connectionStatusOptions' => $this->connectionStatusOptions(),
            'installationWorkflowStatusOptions' => $this->installationWorkflowStatusOptions(),
            'tariffSchemes' => $tariffSchemes,
            'areaScopeLabel' => $this->tirtaAreaScopeLabel(),
            'stats' => [
                'service_areas' => $serviceAreas->count(),
                'service_categories' => $serviceCategories->count(),
                'customers' => $customers->count(),
                'connections' => $connections->count(),
                'default_tariff' => $tariffSchemes->firstWhere('is_default', true)?->name ?? 'Belum ada',
            ],
        ]);
    }

    public function storeServiceArea(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        ServiceArea::query()->create($this->validatedServiceArea($request));

        return $this->redirectToTab('service-areas', 'Area / wilayah berhasil ditambahkan.');
    }

    public function updateServiceArea(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $serviceArea = ServiceArea::query()->findOrFail($id);
        $this->abortIfOutsideTirtaArea((string) $serviceArea->getKey(), 'Area ini berada di luar cakupan area kerja Anda.');
        $serviceArea->fill($this->validatedServiceArea($request, $serviceArea))->save();

        return $this->redirectToTab('service-areas', 'Area / wilayah berhasil diperbarui.');
    }

    public function storeServiceCategory(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        ServiceCategory::query()->create($this->validatedServiceCategory($request));

        return $this->redirectToTab('service-categories', 'Golongan berhasil ditambahkan.');
    }

    public function updateServiceCategory(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $serviceCategory = ServiceCategory::query()->findOrFail($id);
        $serviceCategory->fill($this->validatedServiceCategory($request, $serviceCategory))->save();

        return $this->redirectToTab('service-categories', 'Golongan berhasil diperbarui.');
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        Customer::query()->create($this->validatedCustomer($request));

        return $this->redirectToTab('customers', 'Pelanggan berhasil ditambahkan.');
    }

    public function updateCustomer(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $customer = Customer::query()->findOrFail($id);
        $this->abortIfOutsideTirtaArea($this->tirtaCustomerAreaId($customer), 'Pelanggan ini berada di luar cakupan area kerja Anda.');
        $customer->fill($this->validatedCustomer($request))->save();

        return $this->redirectToTab('customers', 'Pelanggan berhasil diperbarui.');
    }

    public function storeConnection(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $validated = $this->validatedConnection($request);
        $validated['service_number'] = $validated['service_number'] ?? $this->generateUniqueServiceNumber();

        ServiceConnection::query()->create($validated);

        return $this->redirectToTab('connections', 'Sambungan berhasil ditambahkan.');
    }

    public function updateConnection(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $connection = ServiceConnection::query()->findOrFail($id);
        $connection->loadMissing('customer');
        $this->abortIfOutsideTirtaArea($this->tirtaConnectionAreaId($connection), 'Sambungan ini berada di luar cakupan area kerja Anda.');
        $validated = $this->validatedConnection($request, $connection);
        $validated['service_number'] = $validated['service_number'] ?? $connection->service_number;

        $connection->fill($validated)->save();

        return $this->redirectToTab('connections', 'Sambungan berhasil diperbarui.');
    }

    public function requestInstallation(Request $request, string $id, TirtaConnectionLifecycleService $connectionLifecycleService): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $requiredTables = [
            'billing_periods',
            'billing_invoices',
            'billing_invoice_lines',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                throw ValidationException::withMessages([
                    'schema' => 'Schema billing belum siap. Jalankan migrasi tenant terbaru dulu.',
                ]);
            }
        }

        $validated = $request->validate([
            'payment_scheme' => ['required', 'string', Rule::in(['cash', 'installment'])],
            'installment_months' => ['nullable', 'integer', 'min:2', 'max:24'],
        ]);

        $connection = ServiceConnection::query()->findOrFail($id);
        $connection->loadMissing('customer');
        $this->abortIfOutsideTirtaArea($this->tirtaConnectionAreaId($connection), 'Sambungan ini berada di luar cakupan area kerja Anda.');

        $workflowStatus = (string) ($connection->installation_workflow_status ?? 'requested');
        if (! in_array($workflowStatus, ['survey_ok', 'material_requested', 'installation', 'active'], true)) {
            throw ValidationException::withMessages([
                'installation' => 'Invoice pasang baru baru bisa dibuat setelah survey dinyatakan OK atau request barang PSB sudah diajukan.',
            ]);
        }

        $tenantSetting = TenantSetting::query()->firstOrCreate(
            [],
            [
                'brand_name' => (string) (tenant('name') ?? tenant('id') ?? config('app.name')),
                'description' => 'Workspace Tirta belum dikustomisasi.',
                'theme_color' => '#0891b2',
            ]
        );

        try {
            $invoice = DB::connection('tenant')->transaction(function () use ($connection, $tenantSetting, $validated, $connectionLifecycleService): \App\Models\Tirta\BillingInvoice {
                $connection->refresh();

                return $connectionLifecycleService->requestInstallation(
                    $connection,
                    $tenantSetting,
                    (string) $validated['payment_scheme'],
                    $validated['installment_months'] ?? null,
                    now()
                );
            });
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'installation' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('tenant.tirta.billing', ['period' => $invoice->billing_period_id])
            ->with('status', sprintf('Invoice pasang baru %s berhasil dibuat.', $invoice->invoice_number));
    }

    public function storeTariffScheme(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        [$validated, $tiers] = $this->validatedTariffScheme($request);

        $scheme = TariffScheme::query()->create($validated);
        $this->syncTariffTiers($scheme, $tiers);
        $this->syncDefaultTariff($scheme);

        return $this->redirectToTab('tariffs', 'Skema tarif berhasil ditambahkan.');
    }

    public function updateTariffScheme(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessMasterData();

        $scheme = TariffScheme::query()->with('tiers')->findOrFail($id);
        [$validated, $tiers] = $this->validatedTariffScheme($request);

        $scheme->fill($validated)->save();
        $this->syncTariffTiers($scheme, $tiers);
        $this->syncDefaultTariff($scheme);

        return $this->redirectToTab('tariffs', 'Skema tarif berhasil diperbarui.');
    }

    protected function validatedServiceArea(Request $request, ?ServiceArea $serviceArea = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('service_areas', 'code')->ignore($serviceArea?->getKey(), $serviceArea?->getKeyName() ?? 'id')],
            'area_type' => ['required', 'string', Rule::in(array_keys($this->serviceAreaTypeOptions()))],
            'parent_id' => ['nullable', 'string', Rule::exists('service_areas', 'id')],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['parent_id'] = filled($validated['parent_id'] ?? null) ? (string) $validated['parent_id'] : null;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $parent = $validated['parent_id'] !== null
            ? ServiceArea::query()->findOrFail($validated['parent_id'])
            : null;

        if (in_array($validated['area_type'], ['general', 'branch'], true) && $parent !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Tipe area ini harus berada di level paling atas tanpa induk.',
            ]);
        }

        if ($serviceArea instanceof ServiceArea && $parent && $parent->is($serviceArea)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Induk area tidak boleh menunjuk area itu sendiri.',
            ]);
        }

        if ($serviceArea instanceof ServiceArea && $parent && $this->serviceAreaDescendantIds($serviceArea)->contains((string) $parent->getKey())) {
            throw ValidationException::withMessages([
                'parent_id' => 'Induk area tidak valid karena membentuk loop hirarki.',
            ]);
        }

        if ($parent !== null) {
            $parentType = (string) ($parent->area_type ?? 'rayon');

            if ($validated['area_type'] === 'unit' && $parentType !== 'branch') {
                throw ValidationException::withMessages([
                    'parent_id' => 'Unit hanya bisa ditempatkan di bawah cabang.',
                ]);
            }

            if ($validated['area_type'] === 'rayon' && ! in_array($parentType, ['branch', 'unit'], true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Rayon hanya bisa ditempatkan di bawah cabang atau unit.',
                ]);
            }
        }

        if ($this->tirtaAreaIsRestricted()) {
            if (! $serviceArea instanceof ServiceArea && $validated['parent_id'] === null) {
                throw ValidationException::withMessages([
                    'parent_id' => sprintf('Area baru harus dibuat di bawah area %s atau turunannya.', $this->tirtaAreaScopeLabel() ?? 'kerja Anda'),
                ]);
            }

            if ($validated['parent_id'] !== null) {
                $this->ensureTirtaAreaAccessible($validated['parent_id'], 'parent_id', 'Induk area harus berada di dalam cakupan area kerja Anda.');
            }
        }

        return $validated;
    }

    protected function serviceAreaTypeOptions(): array
    {
        return [
            'general' => 'Area Umum',
            'branch' => 'Cabang',
            'unit' => 'Unit',
            'rayon' => 'Rayon',
        ];
    }

    protected function serviceAreaOptions(Collection $serviceAreas): Collection
    {
        $indexed = $serviceAreas->keyBy(fn (ServiceArea $serviceArea): string => (string) $serviceArea->getKey());

        return $serviceAreas
            ->mapWithKeys(function (ServiceArea $serviceArea) use ($indexed): array {
                return [
                    (string) $serviceArea->getKey() => $this->serviceAreaHierarchyLabel($serviceArea, $indexed),
                ];
            });
    }

    protected function serviceAreaHierarchyLabel(ServiceArea $serviceArea, Collection $indexed): string
    {
        $segments = [$serviceArea->name];
        $parentId = $serviceArea->parent_id;
        $visited = [(string) $serviceArea->getKey()];

        while ($parentId !== null && $indexed->has((string) $parentId)) {
            /** @var ServiceArea $parent */
            $parent = $indexed->get((string) $parentId);
            $parentKey = (string) $parent->getKey();

            if (in_array($parentKey, $visited, true)) {
                break;
            }

            array_unshift($segments, $parent->name);
            $visited[] = $parentKey;
            $parentId = $parent->parent_id;
        }

        return implode(' / ', $segments);
    }

    protected function serviceAreaDescendantIds(ServiceArea $serviceArea): Collection
    {
        $children = ServiceArea::query()
            ->where('parent_id', $serviceArea->getKey())
            ->pluck('id');

        $all = collect($children->all());

        foreach ($children as $childId) {
            $child = ServiceArea::query()->find($childId);

            if ($child instanceof ServiceArea) {
                $all = $all->merge($this->serviceAreaDescendantIds($child));
            }
        }

        return $all->unique()->values();
    }

    protected function validatedServiceCategory(Request $request, ?ServiceCategory $serviceCategory = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('service_categories', 'code')->ignore($serviceCategory?->getKey(), $serviceCategory?->getKeyName() ?? 'id')],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        if ($this->tirtaAreaIsRestricted()) {
            $this->ensureTirtaAreaAccessible(
                filled($validated['service_area_id'] ?? null) ? (string) $validated['service_area_id'] : null,
                'service_area_id',
                'Pelanggan harus ditempatkan di area yang termasuk cakupan kerja Anda.'
            );
        }

        return $validated;
    }

    protected function validatedCustomer(Request $request): array
    {
        $validated = $request->validate([
            'service_area_id' => ['nullable', 'string', Rule::exists('service_areas', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    protected function validatedConnection(Request $request, ?ServiceConnection $connection = null): array
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string', Rule::exists('customers', 'id')],
            'service_area_id' => ['nullable', 'string', Rule::exists('service_areas', 'id')],
            'service_category_id' => ['nullable', 'string', Rule::exists('service_categories', 'id')],
            'tariff_scheme_id' => ['nullable', 'string', Rule::exists('tariff_schemes', 'id')],
            'service_number' => ['nullable', 'regex:/^\d{4,6}$/', Rule::unique('service_connections', 'service_number')->ignore($connection?->getKey(), $connection?->getKeyName() ?? 'id')],
            'service_label' => ['nullable', 'string', 'max:100'],
            'meter_number' => ['nullable', 'string', 'max:50'],
            'service_address' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(array_keys($this->connectionStatusOptions()))],
            'installation_workflow_status' => ['nullable', 'string', Rule::in(array_keys($this->installationWorkflowStatusOptions()))],
            'installed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($validated['service_number'] ?? null) === '') {
            $validated['service_number'] = null;
        }

        if (($validated['installation_workflow_status'] ?? null) === null || $validated['installation_workflow_status'] === '') {
            $validated['installation_workflow_status'] = match ((string) $validated['status']) {
                'requested' => 'requested',
                'survey' => 'survey',
                'installation' => 'installation',
                'active' => 'active',
                default => 'requested',
            };
        }

        $customer = Customer::query()->findOrFail((string) $validated['customer_id']);

        if ($this->tirtaAreaIsRestricted()) {
            $customerAreaId = $this->tirtaCustomerAreaId($customer);
            $effectiveAreaId = filled($validated['service_area_id'] ?? null)
                ? (string) $validated['service_area_id']
                : $customerAreaId;

            $this->ensureTirtaAreaAccessible(
                $customerAreaId,
                'customer_id',
                'Pelanggan yang dipilih berada di luar cakupan area kerja Anda.'
            );
            $this->ensureTirtaAreaAccessible(
                $effectiveAreaId,
                'service_area_id',
                'Sambungan harus ditempatkan di area yang termasuk cakupan kerja Anda.'
            );
        }

        return $validated;
    }

    protected function connectionStatusOptions(): array
    {
        return [
            'requested' => 'Permohonan PSB',
            'survey' => 'Survey',
            'inactive' => 'Belum Aktif',
            'installation' => 'Pasang',
            'active' => 'Aktif',
            'blocked' => 'Diblokir',
            'disconnected' => 'Putus',
        ];
    }

    protected function installationWorkflowStatusOptions(): array
    {
        return [
            'requested' => 'Permohonan Masuk',
            'survey' => 'Proses Survey',
            'survey_ok' => 'Survey OK',
            'material_requested' => 'Request Barang',
            'installation' => 'Proses Pasang',
            'active' => 'Aktif',
        ];
    }

    protected function validatedTariffScheme(Request $request): array
    {
        $validated = $request->validate([
            'service_category_id' => ['nullable', 'string', Rule::exists('service_categories', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'calculation_mode' => ['required', 'string', Rule::in(['flat', 'tiered'])],
            'base_price_per_m3' => ['nullable', 'numeric', 'min:0'],
            'minimum_charge' => ['nullable', 'numeric', 'min:0'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'tiers' => ['nullable', 'array'],
            'tiers.*.start_usage' => ['nullable', 'integer', 'min:1'],
            'tiers.*.end_usage' => ['nullable', 'integer', 'min:1'],
            'tiers.*.charge_type' => ['nullable', 'string', Rule::in(['per_m3', 'flat_block'])],
            'tiers.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['is_default'] = $request->boolean('is_default');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['minimum_charge'] = (float) ($validated['minimum_charge'] ?? 0);
        $validated['admin_fee'] = (float) ($validated['admin_fee'] ?? 0);

        if ($validated['calculation_mode'] === 'flat') {
            if (($validated['base_price_per_m3'] ?? null) === null) {
                throw ValidationException::withMessages([
                    'base_price_per_m3' => 'Tarif flat wajib punya harga per m3.',
                ]);
            }

            return [$validated, collect()];
        }

        $tiers = $this->sanitizeTiers(collect($validated['tiers'] ?? []));

        if ($tiers->isEmpty()) {
            throw ValidationException::withMessages([
                'tiers' => 'Tarif bertingkat wajib punya minimal satu tier.',
            ]);
        }

        return [$validated, $tiers];
    }

    protected function sanitizeTiers(Collection $tiers): Collection
    {
        $sanitized = $tiers
            ->map(function (mixed $tier): ?array {
                if (! is_array($tier)) {
                    return null;
                }

                $startUsage = $tier['start_usage'] ?? null;
                $endUsage = $tier['end_usage'] ?? null;
                $price = $tier['price'] ?? null;
                $chargeType = $tier['charge_type'] ?? 'per_m3';

                if ($startUsage === null || $price === null) {
                    return null;
                }

                return [
                    'start_usage' => (int) $startUsage,
                    'end_usage' => $endUsage !== null && $endUsage !== '' ? (int) $endUsage : null,
                    'charge_type' => (string) $chargeType,
                    'price' => (float) $price,
                ];
            })
            ->filter()
            ->sortBy('start_usage')
            ->values();

        $previousEnd = 0;

        foreach ($sanitized as $index => $tier) {
            $row = $index + 1;

            if ($tier['start_usage'] < 1) {
                throw ValidationException::withMessages([
                    "tiers.$index.start_usage" => "Tier {$row} harus mulai dari minimal 1 m3.",
                ]);
            }

            if ($tier['end_usage'] !== null && $tier['end_usage'] < $tier['start_usage']) {
                throw ValidationException::withMessages([
                    "tiers.$index.end_usage" => "Tier {$row} punya batas akhir yang tidak valid.",
                ]);
            }

            if ($previousEnd === null) {
                throw ValidationException::withMessages([
                    'tiers' => 'Tier open ended hanya boleh berada di baris terakhir.',
                ]);
            }

            if ($tier['start_usage'] <= $previousEnd) {
                throw ValidationException::withMessages([
                    "tiers.$index.start_usage" => "Tier {$row} bertabrakan dengan tier sebelumnya.",
                ]);
            }

            $previousEnd = $tier['end_usage'];
        }

        return $sanitized;
    }

    protected function syncTariffTiers(TariffScheme $scheme, Collection $tiers): void
    {
        $scheme->tiers()->delete();

        foreach ($tiers->values() as $index => $tier) {
            $scheme->tiers()->create([
                'start_usage' => $tier['start_usage'],
                'end_usage' => $tier['end_usage'],
                'charge_type' => $tier['charge_type'],
                'price' => $tier['price'],
                'sort_order' => $index,
            ]);
        }
    }

    protected function syncDefaultTariff(TariffScheme $scheme): void
    {
        if (! $scheme->is_default) {
            return;
        }

        TariffScheme::query()
            ->whereKeyNot($scheme->getKey())
            ->update(['is_default' => false]);
    }

    protected function generateUniqueServiceNumber(): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = (string) random_int(100000, 999999);

            if (! ServiceConnection::query()->where('service_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'service_number' => 'Gagal membuat nomor sambungan otomatis. Coba isi manual 4-6 digit.',
        ]);
    }

    protected function ensureTirtaTenant(): void
    {
        if ((string) (tenant('saas_type') ?? '') !== 'tirta') {
            abort(404);
        }
    }

    protected function ensureCanAccessMasterData(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canAccessTirtaMasterData()) {
            abort(403, 'Akun ini tidak punya akses ke Master Tirta.');
        }
    }

    protected function ensureSchemaReady(): void
    {
        $requiredTables = [
            'service_areas',
            'service_categories',
            'customers',
            'service_connections',
            'tariff_schemes',
            'tariff_scheme_tiers',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                throw ValidationException::withMessages([
                    'schema' => 'Schema Tirta belum siap. Jalankan migrasi tenant terbaru dulu.',
                ]);
            }
        }
    }

    protected function redirectToTab(string $tab, string $status): RedirectResponse
    {
        return redirect()
            ->route('tenant.tirta.master-data', ['tab' => $tab])
            ->with('status', $status);
    }
}
