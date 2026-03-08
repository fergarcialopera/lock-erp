<?php

namespace Src\OpenOrders\Application\Services;

use Illuminate\Support\Facades\DB;
use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;
use Src\OpenOrders\Application\Contracts\OpenOrderRepositoryInterface;
use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;

class OpenOrderService
{
    public function __construct(
        private readonly OpenOrderRepositoryInterface $openOrderRepository,
        private readonly CompartmentInventoryRepositoryInterface $inventoryRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    public function list(string $clinicId, ?string $status = null): array
    {
        return $this->openOrderRepository->listByClinic($clinicId, $status);
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
