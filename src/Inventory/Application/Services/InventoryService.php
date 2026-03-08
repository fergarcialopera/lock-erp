<?php

namespace Src\Inventory\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;
use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;
use Src\Inventory\Application\Contracts\ProductRepositoryInterface;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;
use Src\OpenOrders\Application\Contracts\OpenOrderRepositoryInterface;

class InventoryService
{
    public function __construct(
        private readonly CompartmentInventoryRepositoryInterface $inventoryRepository,
        private readonly CompartmentRepositoryInterface $compartmentRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OpenOrderRepositoryInterface $openOrderRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    public function list(string $clinicId, ?string $compartmentId = null): array
    {
        return $this->inventoryRepository->listByClinic($clinicId, $compartmentId);
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
     * Retira unidades de un producto en un compartimento: las marca como reservadas y crea una orden de retiro (PENDING).
     *
     * @return array{inventory: object, order: object}
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

            $order = $this->openOrderRepository->create([
                'clinic_id' => $clinicId,
                'requested_by_user_id' => $userId, // usuario identificado = responsable de la retirada
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
                'action' => 'open_order_requested',
                'entity_type' => 'OpenOrder',
                'entity_id' => $order->id,
                'occurred_at' => now(),
            ]);

            return ['inventory' => $inventory, 'order' => $order];
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
