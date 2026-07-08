<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tirta\InventoryItem;
use App\Models\Tirta\InventoryLocation;
use App\Models\Tirta\InventoryStock;
use App\Services\Tirta\TirtaInventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TirtaInventoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_area_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('location_type')->default('warehouse');
            $table->string('manager_name')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit')->default('pcs');
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('inventory_location_id');
            $table->uuid('inventory_item_id');
            $table->integer('on_hand')->default(0);
            $table->timestamps();
            $table->unique(['inventory_location_id', 'inventory_item_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('inventory_item_id');
            $table->uuid('source_location_id')->nullable();
            $table->uuid('destination_location_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->string('movement_type');
            $table->unsignedInteger('quantity');
            $table->date('movement_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_records_receipt_and_updates_destination_stock(): void
    {
        $service = new TirtaInventoryService();
        $item = InventoryItem::query()->create(['name' => 'Water Meter 1/2', 'unit' => 'pcs']);
        $location = InventoryLocation::query()->create(['name' => 'Gudang Pusat', 'location_type' => 'warehouse']);

        $movement = $service->recordReceipt($item, $location, 30, Carbon::parse('2026-07-06'));

        self::assertSame('receipt', $movement->movement_type);
        self::assertSame(30, $movement->quantity);
        self::assertSame(30, InventoryStock::query()->where('inventory_item_id', $item->id)->where('inventory_location_id', $location->id)->value('on_hand'));
    }

    public function test_it_transfers_stock_between_locations(): void
    {
        $service = new TirtaInventoryService();
        $item = InventoryItem::query()->create(['name' => 'Pipa PVC 3 inch', 'unit' => 'batang']);
        $source = InventoryLocation::query()->create(['name' => 'Gudang Pusat', 'location_type' => 'warehouse']);
        $destination = InventoryLocation::query()->create(['name' => 'Rayon A', 'location_type' => 'rayon']);

        $service->recordReceipt($item, $source, 50, Carbon::parse('2026-07-06'));
        $movement = $service->recordTransfer($item, $source, $destination, 20, Carbon::parse('2026-07-07'));

        self::assertSame('transfer', $movement->movement_type);
        self::assertSame(30, InventoryStock::query()->where('inventory_item_id', $item->id)->where('inventory_location_id', $source->id)->value('on_hand'));
        self::assertSame(20, InventoryStock::query()->where('inventory_item_id', $item->id)->where('inventory_location_id', $destination->id)->value('on_hand'));
    }

    public function test_it_rejects_issue_when_stock_is_not_enough(): void
    {
        $service = new TirtaInventoryService();
        $item = InventoryItem::query()->create(['name' => 'Clamp Saddle', 'unit' => 'pcs']);
        $source = InventoryLocation::query()->create(['name' => 'Unit Lapangan', 'location_type' => 'unit']);

        $service->recordReceipt($item, $source, 5, Carbon::parse('2026-07-06'));

        $this->expectException(ValidationException::class);

        $service->recordIssue($item, $source, 8, Carbon::parse('2026-07-07'));
    }
}

