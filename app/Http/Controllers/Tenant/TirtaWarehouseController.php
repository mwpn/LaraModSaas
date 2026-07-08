<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Tenant\Concerns\InteractsWithTirtaAreaScope;
use App\Http\Controllers\Controller;
use App\Models\Tirta\InventoryItem;
use App\Models\Tirta\InventoryLocation;
use App\Models\Tirta\InventoryMovement;
use App\Models\Tirta\InventoryRequest;
use App\Models\Tirta\InventoryStock;
use App\Models\Tirta\InventorySupplier;
use App\Models\Tirta\ServiceConnection;
use App\Models\Tirta\ServiceArea;
use App\Models\User;
use App\Services\Tirta\TirtaInventoryService;
use App\Services\Tirta\TirtaInventoryWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TirtaWarehouseController extends Controller
{
    use InteractsWithTirtaAreaScope;

    public function __construct(
        protected TirtaInventoryService $inventoryService,
        protected TirtaInventoryWorkflowService $inventoryWorkflowService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();
        $canCreateWarehouseRequest = $user instanceof User && $user->canCreateTirtaWarehouseRequest();
        $canManageWarehouseStock = $user instanceof User && $user->canManageTirtaWarehouseStock();
        $canManageWarehouseMaster = $user instanceof User && $user->canManageTirtaWarehouseMaster();
        $canManageWarehouseSuppliers = $user instanceof User && $user->canManageTirtaWarehouseSuppliers();
        $canApproveProcurementRequests = $user instanceof User && $user->canApproveTirtaProcurementRequest();
        $canApproveWarehouseRequests = $user instanceof User && $user->canApproveTirtaWarehouseRequest();
        $canCompleteWarehouseRequests = $user instanceof User && $user->canCompleteTirtaWarehouseRequest();

        $availableTabs = ['requests', 'stocks'];
        if ($canManageWarehouseStock) {
            $availableTabs[] = 'movements';
        }
        if ($canManageWarehouseMaster) {
            $availableTabs[] = 'items';
            $availableTabs[] = 'locations';
        }
        if ($canManageWarehouseSuppliers) {
            $availableTabs[] = 'suppliers';
        }
        $activeTab = (string) $request->query('tab', 'requests');
        if (! in_array($activeTab, $availableTabs, true)) {
            $activeTab = $availableTabs[0];
        }

        $serviceAreas = ServiceArea::query()
            ->where('is_active', true)
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereIn('id', $this->tirtaAllowedAreaIds()->all()))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $locations = InventoryLocation::query()
            ->with(['serviceArea'])
            ->withSum('stocks as total_stock', 'on_hand')
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaAreaScope($query))
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('location_type')
            ->orderBy('name')
            ->get();

        $items = InventoryItem::query()
            ->withSum([
                'stocks as total_stock' => fn ($query) => $this->tirtaAreaIsRestricted()
                    ? $query->whereHas('location', fn ($locationQuery) => $this->applyTirtaAreaScope($locationQuery))
                    : $query,
            ], 'on_hand')
            ->with(['stocks.location'])
            ->orderByDesc('is_active')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $stocks = InventoryStock::query()
            ->with(['item', 'location.serviceArea'])
            ->where('on_hand', '>', 0)
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $query->whereHas('location', fn ($locationQuery) => $this->applyTirtaAreaScope($locationQuery)))
            ->orderByDesc('on_hand')
            ->get();

        $connections = ServiceConnection::query()
            ->with(['customer', 'serviceArea'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaConnectionScope($query))
            ->orderBy('service_number')
            ->get();

        $movements = InventoryMovement::query()
            ->with(['item', 'sourceLocation.serviceArea', 'destinationLocation.serviceArea', 'createdBy'])
            ->when($this->tirtaAreaIsRestricted(), function ($query): void {
                $query->where(function ($builder): void {
                    $builder
                        ->whereHas('sourceLocation', fn ($locationQuery) => $this->applyTirtaAreaScope($locationQuery))
                        ->orWhereHas('destinationLocation', fn ($locationQuery) => $this->applyTirtaAreaScope($locationQuery));
                });
            })
            ->orderByDesc('movement_date')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        $requests = InventoryRequest::query()
            ->with([
                'lines.item',
                'sourceLocation.serviceArea',
                'destinationLocation.serviceArea',
                'serviceConnection.customer',
                'supplier',
                'requestedBy',
                'approvedBy',
                'completedBy',
            ])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaAreaScope($query))
            ->orderByRaw("case status when 'submitted' then 1 when 'approved' then 2 else 3 end")
            ->orderByDesc('created_at')
            ->limit(80)
            ->get();

        $suppliers = InventorySupplier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $movementStats = [
            'locations' => $locations->count(),
            'items' => $items->count(),
            'stock_total' => (int) $stocks->sum('on_hand'),
            'low_stock_items' => $items->filter(function (InventoryItem $item): bool {
                $minimum = max((int) $item->minimum_stock, 0);

                return $minimum > 0 && (int) ($item->total_stock ?? 0) <= $minimum;
            })->count(),
            'request_submitted' => $requests->where('status', 'submitted')->count(),
            'request_approved' => $requests->where('status', 'approved')->count(),
        ];

        return view('basefeature::tirta.warehouse', [
            'activeTab' => $activeTab,
            'availableTabs' => $availableTabs,
            'serviceAreas' => $serviceAreas,
            'locations' => $locations,
            'items' => $items,
            'stocks' => $stocks,
            'movements' => $movements,
            'requests' => $requests,
            'connections' => $connections,
            'suppliers' => $suppliers,
            'requestTypeOptions' => $this->requestTypeOptions(),
            'requestStatusOptions' => $this->requestStatusOptions(),
            'movementTypeOptions' => $this->movementTypeOptions(),
            'locationTypeOptions' => $this->locationTypeOptions(),
            'stats' => $movementStats,
            'areaScopeLabel' => $this->tirtaAreaScopeLabel(),
            'canCreateWarehouseRequest' => $canCreateWarehouseRequest,
            'canManageWarehouseStock' => $canManageWarehouseStock,
            'canManageWarehouseMaster' => $canManageWarehouseMaster,
            'canManageWarehouseSuppliers' => $canManageWarehouseSuppliers,
            'canApproveWarehouseRequests' => $canApproveWarehouseRequests,
            'canApproveProcurementRequests' => $canApproveProcurementRequests,
            'canCompleteWarehouseRequests' => $canCompleteWarehouseRequests,
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();
        $this->ensureCanManageWarehouseMaster();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('inventory_locations', 'code')],
            'location_type' => ['required', Rule::in(array_keys($this->locationTypeOptions()))],
            'service_area_id' => ['nullable', 'string', Rule::exists('service_areas', 'id')],
            'manager_name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['location_type'] === 'rayon' && blank($validated['service_area_id'] ?? null)) {
            throw ValidationException::withMessages([
                'service_area_id' => 'Lokasi tipe rayon harus dihubungkan ke unit/rayon yang sudah terdaftar.',
            ]);
        }

        if ($this->tirtaAreaIsRestricted()) {
            $this->ensureTirtaAreaAccessible(
                filled($validated['service_area_id'] ?? null) ? (string) $validated['service_area_id'] : null,
                'service_area_id',
                'Lokasi warehouse harus ditempatkan di area yang termasuk cakupan kerja Anda.'
            );
        }

        if ($request->boolean('is_default')) {
            InventoryLocation::query()->update(['is_default' => false]);
        }

        InventoryLocation::query()->create([
            ...$validated,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->redirectToTab('locations', 'Lokasi warehouse berhasil ditambahkan.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();
        $this->ensureCanManageWarehouseMaster();

        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('inventory_items', 'sku')],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', 'string', 'max:20'],
            'minimum_stock' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_serialized' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        InventoryItem::query()->create([
            ...$validated,
            'minimum_stock' => (int) ($validated['minimum_stock'] ?? 0),
            'is_serialized' => $request->boolean('is_serialized'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->redirectToTab('items', 'Barang warehouse berhasil ditambahkan.');
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();
        $this->ensureCanManageWarehouseStock();

        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(array_keys($this->movementTypeOptions()))],
            'inventory_item_id' => ['required', 'string', Rule::exists('inventory_items', 'id')],
            'source_location_id' => ['nullable', 'string', Rule::exists('inventory_locations', 'id')],
            'destination_location_id' => ['nullable', 'string', Rule::exists('inventory_locations', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'movement_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = InventoryItem::query()->findOrFail((string) $validated['inventory_item_id']);
        $source = filled($validated['source_location_id'] ?? null)
            ? InventoryLocation::query()->findOrFail((string) $validated['source_location_id'])
            : null;
        $destination = filled($validated['destination_location_id'] ?? null)
            ? InventoryLocation::query()->findOrFail((string) $validated['destination_location_id'])
            : null;

        if ($source instanceof InventoryLocation) {
            $this->abortIfOutsideTirtaArea($source->service_area_id, 'Lokasi asal berada di luar cakupan area kerja Anda.');
        }

        if ($destination instanceof InventoryLocation) {
            $this->abortIfOutsideTirtaArea($destination->service_area_id, 'Lokasi tujuan berada di luar cakupan area kerja Anda.');
        }

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();
        $movementDate = filled($validated['movement_date'] ?? null)
            ? now()->parse((string) $validated['movement_date'])
            : now();

        match ($validated['movement_type']) {
            'receipt' => $this->inventoryService->recordReceipt(
                $item,
                $this->requiredLocation($destination, 'destination_location_id', 'Lokasi tujuan wajib dipilih untuk barang masuk.'),
                (int) $validated['quantity'],
                $movementDate,
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null,
                $user,
            ),
            'issue' => $this->inventoryService->recordIssue(
                $item,
                $this->requiredLocation($source, 'source_location_id', 'Lokasi asal wajib dipilih untuk barang keluar.'),
                (int) $validated['quantity'],
                $movementDate,
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null,
                $user,
            ),
            'transfer' => $this->inventoryService->recordTransfer(
                $item,
                $this->requiredLocation($source, 'source_location_id', 'Lokasi asal wajib dipilih untuk transfer.'),
                $this->requiredLocation($destination, 'destination_location_id', 'Lokasi tujuan wajib dipilih untuk transfer.'),
                (int) $validated['quantity'],
                $movementDate,
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null,
                $user,
            ),
        };

        return $this->redirectToTab('movements', 'Mutasi stok warehouse berhasil dicatat.');
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();
        $this->ensureCanCreateWarehouseRequest();

        $validated = $request->validate([
            'request_type' => ['required', Rule::in(array_keys($this->requestTypeOptions()))],
            'title' => ['required', 'string', 'max:150'],
            'source_location_id' => ['nullable', 'string', Rule::exists('inventory_locations', 'id')],
            'destination_location_id' => ['nullable', 'string', Rule::exists('inventory_locations', 'id')],
            'supplier_id' => ['nullable', 'string', Rule::exists('inventory_suppliers', 'id')],
            'service_connection_id' => ['nullable', 'string', Rule::exists('service_connections', 'id')],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array'],
            'lines.*.inventory_item_id' => ['nullable', 'string', Rule::exists('inventory_items', 'id')],
            'lines.*.quantity_requested' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);

        $source = filled($validated['source_location_id'] ?? null)
            ? InventoryLocation::query()->findOrFail((string) $validated['source_location_id'])
            : null;
        $destination = filled($validated['destination_location_id'] ?? null)
            ? InventoryLocation::query()->findOrFail((string) $validated['destination_location_id'])
            : null;
        $connection = filled($validated['service_connection_id'] ?? null)
            ? ServiceConnection::query()->with('customer')->findOrFail((string) $validated['service_connection_id'])
            : null;

        if ($source instanceof InventoryLocation) {
            $this->abortIfOutsideTirtaArea($source->service_area_id, 'Lokasi asal berada di luar cakupan area kerja Anda.');
        }

        if ($destination instanceof InventoryLocation) {
            $this->abortIfOutsideTirtaArea($destination->service_area_id, 'Lokasi tujuan berada di luar cakupan area kerja Anda.');
        }

        if ($connection instanceof ServiceConnection) {
            $this->abortIfOutsideTirtaArea($this->tirtaConnectionAreaId($connection), 'Sambungan PSB berada di luar cakupan area kerja Anda.');
        }

        $serviceAreaId = match ((string) $validated['request_type']) {
            'procurement' => $destination?->service_area_id,
            'distribution' => $destination?->service_area_id ?? $source?->service_area_id,
            'installation' => $this->tirtaConnectionAreaId($connection),
        };

        if ($validated['request_type'] === 'procurement' && ! $destination instanceof InventoryLocation) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Pengadaan pusat wajib punya lokasi penerimaan barang.',
            ]);
        }
        if ($validated['request_type'] === 'procurement' && blank($validated['supplier_id'] ?? null)) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Pengadaan pusat wajib memilih supplier.',
            ]);
        }

        if ($validated['request_type'] === 'distribution' && (! $source instanceof InventoryLocation || ! $destination instanceof InventoryLocation)) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Permintaan cabang/unit wajib punya lokasi asal dan tujuan.',
            ]);
        }

        if ($validated['request_type'] === 'installation' && (! $source instanceof InventoryLocation || ! $connection instanceof ServiceConnection)) {
            throw ValidationException::withMessages([
                'service_connection_id' => 'Request PSB wajib memilih sambungan dan gudang asal barang.',
            ]);
        }

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        $this->inventoryWorkflowService->createRequest([
            ...$validated,
            'service_area_id' => $serviceAreaId,
            'source_location_id' => $source?->getKey(),
            'destination_location_id' => $destination?->getKey(),
            'supplier_id' => filled($validated['supplier_id'] ?? null) ? (string) $validated['supplier_id'] : null,
            'service_connection_id' => $connection?->getKey(),
        ], $user);

        return $this->redirectToTab('requests', 'Dokumen permintaan warehouse berhasil dibuat.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();
        $this->ensureCanManageWarehouseSuppliers();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        InventorySupplier::query()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->redirectToTab('suppliers', 'Supplier berhasil ditambahkan.');
    }

    public function approveRequest(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string'],
        ]);

        $inventoryRequest = $this->findRequest($id);
        $this->ensureCanApproveRequest($inventoryRequest);

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        $this->inventoryWorkflowService->approveRequest(
            $inventoryRequest,
            $user,
            $validated['approval_notes'] ?? null,
        );

        return $this->redirectToTab('requests', 'Dokumen permintaan warehouse berhasil di-approve.');
    }

    public function completeRequest(Request $request, string $id): RedirectResponse
    {
        $this->ensureTirtaTenant();
        $this->ensureSchemaReady();
        $this->ensureCanAccessWarehouse();

        $validated = $request->validate([
            'completion_notes' => ['nullable', 'string'],
            'meter_serial_number' => ['nullable', 'string', 'max:50'],
        ]);

        $inventoryRequest = $this->findRequest($id);
        $this->ensureCanCompleteRequest();

        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        $this->inventoryWorkflowService->completeRequest(
            $inventoryRequest,
            $user,
            $validated['completion_notes'] ?? null,
            $validated['meter_serial_number'] ?? null,
        );

        return $this->redirectToTab('requests', 'Dokumen permintaan warehouse berhasil diselesaikan dan stok sudah diperbarui.');
    }

    protected function requiredLocation(?InventoryLocation $location, string $field, string $message): InventoryLocation
    {
        if ($location instanceof InventoryLocation) {
            return $location;
        }

        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    protected function findRequest(string $id): InventoryRequest
    {
        return InventoryRequest::query()
            ->with(['lines.item', 'sourceLocation', 'destinationLocation', 'serviceConnection.customer', 'supplier'])
            ->when($this->tirtaAreaIsRestricted(), fn ($query) => $this->applyTirtaAreaScope($query))
            ->findOrFail($id);
    }

    protected function movementTypeOptions(): array
    {
        return [
            'receipt' => 'Barang Masuk',
            'issue' => 'Barang Keluar',
            'transfer' => 'Transfer Antar Lokasi',
        ];
    }

    protected function requestTypeOptions(): array
    {
        return [
            'procurement' => 'Pengadaan Pusat',
            'distribution' => 'Permintaan Cabang/Unit',
            'installation' => 'Request Barang PSB/Teknik',
        ];
    }

    protected function requestStatusOptions(): array
    {
        return [
            'submitted' => 'Menunggu Approval',
            'approved' => 'Siap Dieksekusi',
            'completed' => 'Selesai',
        ];
    }

    protected function locationTypeOptions(): array
    {
        return [
            'warehouse' => 'Gudang Pusat',
            'unit' => 'Unit Operasional',
            'rayon' => 'Rayon',
            'branch' => 'Cabang',
        ];
    }

    protected function ensureTirtaTenant(): void
    {
        if ((string) (tenant('saas_type') ?? '') !== 'tirta') {
            abort(404);
        }
    }

    protected function ensureCanAccessWarehouse(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canAccessTirtaWarehouse()) {
            abort(403, 'Akun ini tidak punya akses ke warehouse Tirta.');
        }
    }

    protected function ensureCanCreateWarehouseRequest(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canCreateTirtaWarehouseRequest()) {
            abort(403, 'Akun ini tidak punya akses untuk membuat dokumen request warehouse.');
        }
    }

    protected function ensureCanManageWarehouseStock(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canManageTirtaWarehouseStock()) {
            abort(403, 'Akun ini tidak punya akses untuk mengelola mutasi atau stok warehouse.');
        }
    }

    protected function ensureCanManageWarehouseMaster(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canManageTirtaWarehouseMaster()) {
            abort(403, 'Akun ini tidak punya akses untuk mengelola master barang atau lokasi warehouse.');
        }
    }

    protected function ensureCanManageWarehouseSuppliers(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canManageTirtaWarehouseSuppliers()) {
            abort(403, 'Akun ini tidak punya akses untuk mengelola master supplier.');
        }
    }

    protected function ensureCanApproveRequest(InventoryRequest $request): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canApproveTirtaWarehouseRequest($request->request_type)) {
            abort(403, $request->request_type === 'procurement'
                ? 'Approval pengadaan barang hanya boleh dilakukan oleh owner atau user role keuangan.'
                : 'Akun ini tidak punya akses untuk approve request warehouse.');
        }
    }

    protected function ensureCanCompleteRequest(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('tenant')->user();

        if (! ($user instanceof User) || ! $user->canCompleteTirtaWarehouseRequest()) {
            abort(403, 'Akun ini tidak punya akses untuk menyelesaikan request warehouse dan update stok.');
        }
    }

    protected function ensureSchemaReady(): void
    {
        $requiredTables = [
            'service_areas',
            'inventory_locations',
            'inventory_items',
            'inventory_stocks',
            'inventory_movements',
            'inventory_requests',
            'inventory_request_lines',
            'inventory_suppliers',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::connection('tenant')->hasTable($table)) {
                throw ValidationException::withMessages([
                    'schema' => 'Schema warehouse Tirta belum siap. Jalankan migrasi tenant terbaru dulu.',
                ]);
            }
        }
    }

    protected function redirectToTab(string $tab, string $status): RedirectResponse
    {
        return redirect()
            ->route('tenant.tirta.warehouse', ['tab' => $tab])
            ->with('status', $status);
    }
}
