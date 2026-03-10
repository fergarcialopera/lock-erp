<?php

namespace Src\Inventory\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;
use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;
use Src\Inventory\Application\Contracts\ProductRepositoryInterface;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;
use Src\Dispenses\Application\Contracts\DispenseRepositoryInterface;

class InventoryService
{
    public function __construct(
        private readonly CompartmentInventoryRepositoryInterface $inventoryRepository,
        private readonly CompartmentRepositoryInterface $compartmentRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly DispenseRepositoryInterface $dispenseRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    /** Listado para vista: inventario con product, compartment y locker resueltos. */
    public function list(string $clinicId, ?string $compartmentId = null): array
    {
        return $this->inventoryRepository->listByClinicForDisplay($clinicId, $compartmentId);
    }

    public function adjust(string $clinicId, string $userId, string $compartmentId, string $productId, int $qtyAvailable): void
    {
        $this->inventoryRepository->updateOrCreate($clinicId, $compartmentId, $productId, $qtyAvailable);
        $this->auditLogRepository->log([
            'clinic_id' => $clinicId,
            'actor_user_id' => $userId,
            'actor_type' => 'USER',
            'action' => 'inventory_adjusted',
            'entity_type' => 'CompartmentInventory',
            'entity_id' => "{$compartmentId}:{$productId}",
            'occurred_at' => now(),
            'payload' => ['qty_available' => $qtyAvailable],
        ]);
    }

    /**
     * Amplía las unidades de un producto en un compartimento. Crea la fila de inventario si no existe.
     *
     * @throws DomainException Si el compartimento o el producto no pertenecen a la clínica
     */
    public function addUnits(string $clinicId, string $userId, string $compartmentId, string $productId, int $quantity): object
    {
        $this->ensureCompartmentBelongsToClinic($compartmentId, $clinicId);
        $this->ensureProductBelongsToClinic($productId, $clinicId);

        $inventory = $this->inventoryRepository->addQuantity($clinicId, $compartmentId, $productId, $quantity);
        $this->auditLogRepository->log([
            'clinic_id' => $clinicId,
            'actor_user_id' => $userId,
            'actor_type' => 'USER',
            'action' => 'inventory_units_added',
            'entity_type' => 'CompartmentInventory',
            'entity_id' => $inventory->id,
            'occurred_at' => now(),
            'payload' => ['quantity' => $quantity],
        ]);

        return $inventory;
    }

    /**
     * Retira unidades de un producto en un compartimento: las marca como reservadas y crea una dispensación (PENDING).
     *
     * @return array{inventory: object, dispense: object}
     * @throws DomainException Si no hay inventario o stock insuficiente
     */
    public function removeUnits(string $clinicId, string $userId, string $compartmentId, string $productId, int $quantity): array
    {
        $this->ensureCompartmentBelongsToClinic($compartmentId, $clinicId);
        $this->ensureProductBelongsToClinic($productId, $clinicId);

        return DB::transaction(function () use ($clinicId, $userId, $compartmentId, $productId, $quantity) {
            $inventory = $this->inventoryRepository->removeQuantity($clinicId, $compartmentId, $productId, $quantity);

            $compartment = $this->compartmentRepository->findByIdInClinic($compartmentId, $clinicId);
            assert($compartment !== null, 'Compartimento ya validado');

            $dispense = $this->dispenseRepository->create([
                'clinic_id' => $clinicId,
                'requested_by_user_id' => $userId,
                'locker_id' => $compartment->locker_id,
                'compartment_id' => $compartmentId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'status' => 'PENDING',
                'requested_at' => now(),
                'external_ref' => null,
            ]);

            $this->auditLogRepository->log([
                'clinic_id' => $clinicId,
                'actor_user_id' => $userId,
                'actor_type' => 'USER',
                'action' => 'dispense_requested',
                'entity_type' => 'Dispense',
                'entity_id' => $dispense->id,
                'occurred_at' => now(),
            ]);

            return ['inventory' => $inventory, 'dispense' => $dispense];
        });
    }

    /**
     * Elimina una entrada de inventario (fila compartimento+producto) de la clínica.
     *
     * @throws DomainException Si la entrada no existe o no pertenece a la clínica
     */
    public function deleteEntry(string $clinicId, string $userId, string $inventoryId): void
    {
        $this->inventoryRepository->delete($inventoryId, $clinicId);
        $this->auditLogRepository->log([
            'clinic_id' => $clinicId,
            'actor_user_id' => $userId,
            'actor_type' => 'USER',
            'action' => 'inventory_entry_deleted',
            'entity_type' => 'CompartmentInventory',
            'entity_id' => $inventoryId,
            'occurred_at' => now(),
        ]);
    }

    private function ensureCompartmentBelongsToClinic(string $compartmentId, string $clinicId): void
    {
        $compartment = $this->compartmentRepository->findByIdInClinic($compartmentId, $clinicId);
        if ($compartment === null) {
            throw new DomainException('Compartimento no encontrado');
        }
    }

    private function ensureProductBelongsToClinic(string $productId, string $clinicId): void
    {
        $product = $this->productRepository->findByIdAndClinic($productId, $clinicId);
        if ($product === null) {
            throw new DomainException('Producto no encontrado');
        }
    }
}
