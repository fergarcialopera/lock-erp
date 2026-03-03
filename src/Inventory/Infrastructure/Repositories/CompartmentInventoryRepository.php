<?php

namespace Src\Inventory\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;

class CompartmentInventoryRepository implements CompartmentInventoryRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $compartmentId = null): array
    {
        $query = DB::table('compartment_inventories')->where('clinic_id', $clinicId);

        if ($compartmentId !== null) {
            $query->where('compartment_id', $compartmentId);
        }

        return $query->get()->all();
    }

    public function updateOrCreate(string $clinicId, string $compartmentId, string $productId, int $qtyAvailable): void
    {
        DB::table('compartment_inventories')->updateOrInsert(
            [
                'clinic_id' => $clinicId,
                'compartment_id' => $compartmentId,
                'product_id' => $productId,
            ],
            [
                'id' => Str::ulid()->toString(),
                'qty_available' => $qtyAvailable,
                'updated_at' => now(),
            ]
        );
    }

    public function findForUpdate(string $clinicId, string $compartmentId, string $productId): ?object
    {
        return DB::table('compartment_inventories')
            ->where('clinic_id', $clinicId)
            ->where('compartment_id', $compartmentId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }

    public function reserveStock(string $inventoryId, int $quantity): void
    {
        $inv = DB::table('compartment_inventories')->where('id', $inventoryId)->first();
        if (!$inv) {
            return;
        }
        DB::table('compartment_inventories')->where('id', $inventoryId)->update([
            'qty_available' => $inv->qty_available - $quantity,
            'qty_reserved' => $inv->qty_reserved + $quantity,
            'updated_at' => now(),
        ]);
    }

    public function releaseReserved(string $clinicId, string $compartmentId, string $productId, int $quantity): void
    {
        $inv = DB::table('compartment_inventories')
            ->where('clinic_id', $clinicId)
            ->where('compartment_id', $compartmentId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
        if (!$inv) {
            return;
        }
        $newReserved = max(0, $inv->qty_reserved - $quantity);
        DB::table('compartment_inventories')->where('id', $inv->id)->update([
            'qty_reserved' => $newReserved,
            'updated_at' => now(),
        ]);
    }
}
