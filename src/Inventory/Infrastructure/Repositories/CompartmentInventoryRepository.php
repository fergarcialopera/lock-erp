<?php

namespace Src\Inventory\Infrastructure\Repositories;

use DomainException;
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

    public function find(string $clinicId, string $compartmentId, string $productId): ?object
    {
        return DB::table('compartment_inventories')
            ->where('clinic_id', $clinicId)
            ->where('compartment_id', $compartmentId)
            ->where('product_id', $productId)
            ->first();
    }

    public function findByIdAndClinic(string $id, string $clinicId): ?object
    {
        return DB::table('compartment_inventories')
            ->where('id', $id)
            ->where('clinic_id', $clinicId)
            ->first();
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

    public function addQuantity(string $clinicId, string $compartmentId, string $productId, int $quantity): object
    {
        return DB::transaction(function () use ($clinicId, $compartmentId, $productId, $quantity) {
            $row = $this->findForUpdate($clinicId, $compartmentId, $productId);

            if ($row === null) {
                $id = (string) Str::ulid();
                DB::table('compartment_inventories')->insert([
                    'id' => $id,
                    'clinic_id' => $clinicId,
                    'compartment_id' => $compartmentId,
                    'product_id' => $productId,
                    'qty_available' => $quantity,
                    'qty_reserved' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('compartment_inventories')
                    ->where('id', $row->id)
                    ->update([
                        'qty_available' => $row->qty_available + $quantity,
                        'updated_at' => now(),
                    ]);
            }

            $updated = $this->find($clinicId, $compartmentId, $productId);
            assert($updated !== null);

            return $updated;
        });
    }

    public function removeQuantity(string $clinicId, string $compartmentId, string $productId, int $quantity): object
    {
        $row = $this->findForUpdate($clinicId, $compartmentId, $productId);

        if ($row === null) {
            throw new DomainException('No hay inventario para este producto en el compartimento');
        }

        if ($row->qty_available < $quantity) {
            throw new DomainException('Stock insuficiente en el compartimento');
        }

        DB::table('compartment_inventories')
            ->where('id', $row->id)
            ->update([
                'qty_available' => $row->qty_available - $quantity,
                'qty_reserved' => $row->qty_reserved + $quantity,
                'updated_at' => now(),
            ]);

        $updated = $this->find($clinicId, $compartmentId, $productId);
        assert($updated !== null);

        return $updated;
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

    public function delete(string $id, string $clinicId): void
    {
        $row = $this->findByIdAndClinic($id, $clinicId);
        if ($row === null) {
            throw new DomainException('Entrada de inventario no encontrada');
        }
        DB::table('compartment_inventories')->where('id', $id)->delete();
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
