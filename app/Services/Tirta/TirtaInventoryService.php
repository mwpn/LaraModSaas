<?php

declare(strict_types=1);

namespace App\Services\Tirta;

use App\Models\Tirta\InventoryItem;
use App\Models\Tirta\InventoryLocation;
use App\Models\Tirta\InventoryMovement;
use App\Models\Tirta\InventoryStock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TirtaInventoryService
{
    public function recordReceipt(
        InventoryItem $item,
        InventoryLocation $destination,
        int $quantity,
        ?Carbon $movementDate = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?User $actor = null,
        array $meta = []
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah barang masuk harus lebih besar dari nol.',
            ]);
        }

        return DB::transaction(function () use ($item, $destination, $quantity, $movementDate, $referenceNumber, $notes, $actor, $meta): InventoryMovement {
            $destinationStock = $this->lockStock($destination, $item);
            $destinationStock->increment('on_hand', $quantity);

            return InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'destination_location_id' => $destination->id,
                'created_by_user_id' => $actor?->id,
                'movement_type' => 'receipt',
                'quantity' => $quantity,
                'movement_date' => ($movementDate ?? now())->toDateString(),
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'meta' => $meta,
            ]);
        });
    }

    public function recordIssue(
        InventoryItem $item,
        InventoryLocation $source,
        int $quantity,
        ?Carbon $movementDate = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?User $actor = null,
        array $meta = []
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah barang keluar harus lebih besar dari nol.',
            ]);
        }

        return DB::transaction(function () use ($item, $source, $quantity, $movementDate, $referenceNumber, $notes, $actor, $meta): InventoryMovement {
            $sourceStock = $this->lockStock($source, $item);

            if ((int) $sourceStock->on_hand < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf('Stok %s di %s tidak cukup. Saldo tersedia %d.', $item->name, $source->name, (int) $sourceStock->on_hand),
                ]);
            }

            $sourceStock->decrement('on_hand', $quantity);

            return InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'source_location_id' => $source->id,
                'created_by_user_id' => $actor?->id,
                'movement_type' => 'issue',
                'quantity' => $quantity,
                'movement_date' => ($movementDate ?? now())->toDateString(),
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'meta' => $meta,
            ]);
        });
    }

    public function recordTransfer(
        InventoryItem $item,
        InventoryLocation $source,
        InventoryLocation $destination,
        int $quantity,
        ?Carbon $movementDate = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?User $actor = null,
        array $meta = []
    ): InventoryMovement {
        if ($source->is($destination)) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Lokasi asal dan tujuan transfer tidak boleh sama.',
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah transfer harus lebih besar dari nol.',
            ]);
        }

        return DB::transaction(function () use ($item, $source, $destination, $quantity, $movementDate, $referenceNumber, $notes, $actor, $meta): InventoryMovement {
            $sourceStock = $this->lockStock($source, $item);

            if ((int) $sourceStock->on_hand < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf('Stok %s di %s tidak cukup untuk transfer. Saldo tersedia %d.', $item->name, $source->name, (int) $sourceStock->on_hand),
                ]);
            }

            $destinationStock = $this->lockStock($destination, $item);

            $sourceStock->decrement('on_hand', $quantity);
            $destinationStock->increment('on_hand', $quantity);

            return InventoryMovement::query()->create([
                'inventory_item_id' => $item->id,
                'source_location_id' => $source->id,
                'destination_location_id' => $destination->id,
                'created_by_user_id' => $actor?->id,
                'movement_type' => 'transfer',
                'quantity' => $quantity,
                'movement_date' => ($movementDate ?? now())->toDateString(),
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'meta' => $meta,
            ]);
        });
    }

    protected function lockStock(InventoryLocation $location, InventoryItem $item): InventoryStock
    {
        $stock = InventoryStock::query()
            ->where('inventory_location_id', $location->id)
            ->where('inventory_item_id', $item->id)
            ->lockForUpdate()
            ->first();

        if ($stock instanceof InventoryStock) {
            return $stock;
        }

        return InventoryStock::query()->create([
            'inventory_location_id' => $location->id,
            'inventory_item_id' => $item->id,
            'on_hand' => 0,
        ]);
    }
}

