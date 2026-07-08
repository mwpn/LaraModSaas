<?php

declare(strict_types=1);

namespace App\Services\Tirta;

use App\Models\Tirta\InventoryItem;
use App\Models\Tirta\InventoryLocation;
use App\Models\Tirta\InventoryRequest;
use App\Models\Tirta\InventoryRequestLine;
use App\Models\Tirta\ServiceConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TirtaInventoryWorkflowService
{
    public function __construct(
        protected TirtaInventoryService $inventoryService,
    ) {
    }

    public function createRequest(array $payload, ?User $actor = null): InventoryRequest
    {
        $lines = collect($payload['lines'] ?? [])
            ->filter(fn (array $line): bool => filled($line['inventory_item_id'] ?? null) && (int) ($line['quantity_requested'] ?? 0) > 0)
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Minimal isi satu barang pada dokumen permintaan warehouse.',
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($payload, $actor, $lines): InventoryRequest {
            $request = InventoryRequest::query()->create([
                'request_number' => $this->nextRequestNumber(),
                'request_type' => (string) $payload['request_type'],
                'status' => 'submitted',
                'service_area_id' => $payload['service_area_id'] ?? null,
                'service_connection_id' => $payload['service_connection_id'] ?? null,
                'source_location_id' => $payload['source_location_id'] ?? null,
                'destination_location_id' => $payload['destination_location_id'] ?? null,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'requested_by_user_id' => $actor?->getKey(),
                'title' => trim((string) $payload['title']),
                'reference_number' => $payload['reference_number'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'requested_at' => now(),
                'meta' => $payload['meta'] ?? null,
            ]);

            foreach ($lines as $line) {
                InventoryRequestLine::query()->create([
                    'inventory_request_id' => $request->getKey(),
                    'inventory_item_id' => (string) $line['inventory_item_id'],
                    'quantity_requested' => (int) $line['quantity_requested'],
                    'quantity_approved' => 0,
                    'quantity_completed' => 0,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            if ($request->request_type === 'installation' && $request->serviceConnection instanceof ServiceConnection) {
                $request->serviceConnection->forceFill([
                    'installation_workflow_status' => 'material_requested',
                ])->save();
            }

            return $request->load(['lines.item', 'serviceConnection.customer', 'supplier']);
        });
    }

    public function approveRequest(InventoryRequest $request, ?User $actor = null, ?string $notes = null): InventoryRequest
    {
        if ($request->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Dokumen ini tidak lagi menunggu approval.',
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($request, $actor, $notes): InventoryRequest {
            $request->loadMissing('lines');

            foreach ($request->lines as $line) {
                $line->forceFill([
                    'quantity_approved' => (int) $line->quantity_requested,
                ])->save();
            }

            $request->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor?->getKey(),
                'approved_at' => now(),
                'approval_notes' => filled($notes) ? trim((string) $notes) : null,
            ])->save();

            return $request->load(['lines.item', 'serviceConnection.customer']);
        });
    }

    public function completeRequest(
        InventoryRequest $request,
        ?User $actor = null,
        ?string $notes = null,
        ?string $meterSerialNumber = null
    ): InventoryRequest {
        if ($request->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Dokumen ini harus di-approve dulu sebelum diselesaikan.',
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($request, $actor, $notes, $meterSerialNumber): InventoryRequest {
            $request->loadMissing(['lines.item', 'sourceLocation', 'destinationLocation', 'serviceConnection.customer', 'supplier']);

            if ($request->request_type === 'procurement' && ! $request->destinationLocation instanceof InventoryLocation) {
                throw ValidationException::withMessages([
                    'destination_location_id' => 'Pengadaan pusat wajib punya lokasi penerimaan barang.',
                ]);
            }
            if ($request->request_type === 'procurement' && ! filled($request->supplier_id ?? null)) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Pengadaan pusat wajib memilih supplier.',
                ]);
            }

            if (in_array($request->request_type, ['distribution', 'installation'], true) && ! $request->sourceLocation instanceof InventoryLocation) {
                throw ValidationException::withMessages([
                    'source_location_id' => 'Permintaan distribusi/PSB wajib punya lokasi asal barang.',
                ]);
            }

            if ($request->request_type === 'distribution' && ! $request->destinationLocation instanceof InventoryLocation) {
                throw ValidationException::withMessages([
                    'destination_location_id' => 'Distribusi ke cabang/unit wajib punya lokasi tujuan.',
                ]);
            }

            if ($request->request_type === 'installation' && ! $request->serviceConnection instanceof ServiceConnection) {
                throw ValidationException::withMessages([
                    'service_connection_id' => 'Request PSB wajib terhubung ke sambungan/pelanggan.',
                ]);
            }

            $serializedLineExists = $request->lines->contains(fn (InventoryRequestLine $line): bool => (bool) ($line->item?->is_serialized ?? false));
            if ($request->request_type === 'installation' && $serializedLineExists && blank($meterSerialNumber)) {
                throw ValidationException::withMessages([
                    'meter_serial_number' => 'Nomor seri water meter wajib diisi untuk request PSB yang memakai barang serialized.',
                ]);
            }

            foreach ($request->lines as $line) {
                $qty = max((int) $line->quantity_approved, 0);

                if ($qty < 1) {
                    continue;
                }

                $meta = [
                    'request_id' => (string) $request->getKey(),
                    'request_number' => (string) $request->request_number,
                    'request_type' => (string) $request->request_type,
                ];
                if (filled($request->supplier_id ?? null)) {
                    $meta['supplier_id'] = (string) $request->supplier_id;
                    $meta['supplier_name'] = (string) ($request->supplier?->name ?? '');
                }

                if ($request->serviceConnection instanceof ServiceConnection) {
                    $meta['service_connection_id'] = (string) $request->serviceConnection->getKey();
                    $meta['customer_name'] = (string) ($request->serviceConnection->customer?->name ?? '');
                }

                if (filled($meterSerialNumber) && (bool) ($line->item?->is_serialized ?? false)) {
                    $meta['meter_serial_number'] = trim((string) $meterSerialNumber);
                }

                match ($request->request_type) {
                    'procurement' => $this->inventoryService->recordReceipt(
                        $line->item,
                        $request->destinationLocation,
                        $qty,
                        now(),
                        $request->reference_number,
                        $notes ?? $request->notes,
                        $actor,
                        $meta,
                    ),
                    'distribution' => $this->inventoryService->recordTransfer(
                        $line->item,
                        $request->sourceLocation,
                        $request->destinationLocation,
                        $qty,
                        now(),
                        $request->reference_number,
                        $notes ?? $request->notes,
                        $actor,
                        $meta,
                    ),
                    'installation' => $this->inventoryService->recordIssue(
                        $line->item,
                        $request->sourceLocation,
                        $qty,
                        now(),
                        $request->reference_number,
                        $notes ?? $request->notes,
                        $actor,
                        $meta,
                    ),
                    default => throw ValidationException::withMessages([
                        'request_type' => 'Jenis dokumen warehouse tidak dikenali.',
                    ]),
                };

                $line->forceFill([
                    'quantity_completed' => $qty,
                ])->save();
            }

            $request->forceFill([
                'status' => 'completed',
                'completed_by_user_id' => $actor?->getKey(),
                'completed_at' => now(),
                'completion_notes' => filled($notes) ? trim((string) $notes) : null,
                'meta' => array_filter([
                    ...((array) ($request->meta ?? [])),
                    'meter_serial_number' => filled($meterSerialNumber) ? trim((string) $meterSerialNumber) : null,
                ], fn ($value) => $value !== null && $value !== ''),
            ])->save();

            if ($request->request_type === 'installation' && $request->serviceConnection instanceof ServiceConnection) {
                $request->serviceConnection->forceFill([
                    'status' => 'installation',
                    'installation_workflow_status' => 'installation',
                    'meter_number' => filled($meterSerialNumber)
                        ? trim((string) $meterSerialNumber)
                        : $request->serviceConnection->meter_number,
                ])->save();
            }

            return $request->load(['lines.item', 'sourceLocation', 'destinationLocation', 'serviceConnection.customer', 'requestedBy', 'approvedBy', 'completedBy']);
        });
    }

    protected function nextRequestNumber(): string
    {
        return sprintf('WH-%s-%s', now()->format('YmdHis'), Str::upper(Str::random(4)));
    }
}
