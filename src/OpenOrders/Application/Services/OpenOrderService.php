<?php

namespace Src\OpenOrders\Application\Services;

use Illuminate\Support\Facades\DB;
use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;
use Src\OpenOrders\Application\Contracts\OpenOrderRepositoryInterface;
use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;

class OpenOrderService
{
    public function __construct(
        private readonly OpenOrderRepositoryInterface $openOrderRepository,
        private readonly CompartmentInventoryRepositoryInterface $inventoryRepository,
        private readonly CompartmentRepositoryInterface $compartmentRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    public function list(string $clinicId, ?string $status = null): array
    {
        return $this->openOrderRepository->listByClinic($clinicId, $status);
    }

    /**
     * @return array{order: object, created: bool}
     */
    public function create(
        string $clinicId,
        string $userId,
        string $compartmentId,
        string $productId,
        int $quantity,
        ?string $externalRef
    ): array {
        if ($externalRef) {
            $existing = $this->openOrderRepository->findByExternalRef($clinicId, $externalRef);
            if ($existing) {
                return ['order' => $existing, 'created' => false];
            }
        }

        $order = DB::transaction(function () use ($clinicId, $userId, $compartmentId, $productId, $quantity, $externalRef) {
            $inventory = $this->inventoryRepository->findForUpdate($clinicId, $compartmentId, $productId);

            if (!$inventory || $inventory->qty_available < $quantity) {
                throw new \DomainException('Not enough inventory');
            }

            $this->inventoryRepository->reserveStock($inventory->id, $quantity);

            $compartment = $this->compartmentRepository->findById($compartmentId);
            if (!$compartment) {
                throw new \DomainException('Compartment not found');
            }

            $order = $this->openOrderRepository->create([
                'clinic_id' => $clinicId,
                'requested_by_user_id' => $userId,
                'locker_id' => $compartment->locker_id,
                'compartment_id' => $compartmentId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'status' => 'PENDING',
                'requested_at' => now(),
                'external_ref' => $externalRef,
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

            return $order;
        });

        return ['order' => $order, 'created' => true];
    }

    public function confirmRead(string $orderId, string $clinicId, string $userId, ?\DateTimeInterface $occurredAt = null): array
    {
        $occurredAt = $occurredAt ?? now();

        return DB::transaction(function () use ($orderId, $clinicId, $userId, $occurredAt) {
            $order = $this->openOrderRepository->findByIdAndClinicForUpdate($orderId, $clinicId);

            if (!$order) {
                return ['found' => false, 'message' => 'Order not found'];
            }

            if ($order->status === 'RETIRED') {
                return ['found' => true, 'already_retired' => true, 'message' => 'Already confirmed read'];
            }

            $this->inventoryRepository->releaseReserved(
                $clinicId,
                $order->compartment_id,
                $order->product_id,
                $order->quantity
            );

            $this->openOrderRepository->markAsRetired($orderId, $occurredAt);

            $this->auditLogRepository->log([
                'clinic_id' => $clinicId,
                'actor_user_id' => $userId,
                'actor_type' => 'USER',
                'action' => 'open_order_read_confirmed',
                'entity_type' => 'OpenOrder',
                'entity_id' => $orderId,
                'occurred_at' => $occurredAt,
            ]);

            return ['found' => true, 'already_retired' => false, 'message' => 'Read confirmed'];
        });
    }
}
