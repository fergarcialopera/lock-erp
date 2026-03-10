<?php

namespace Src\Dispenses\Application\Services;

use Illuminate\Support\Facades\DB;
use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;
use Src\Dispenses\Application\Contracts\DispenseRepositoryInterface;
use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;

class DispenseService
{
    public function __construct(
        private readonly DispenseRepositoryInterface $dispenseRepository,
        private readonly CompartmentInventoryRepositoryInterface $inventoryRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    /** Listado para vista principal: dispensaciones con product, locker, compartment y requested_by resueltos. */
    public function list(string $clinicId, ?string $status = null): array
    {
        return $this->dispenseRepository->listByClinicForDisplay($clinicId, $status);
    }

    /** Detalle de una dispensación listo para vista: misma estructura enriquecida. */
    public function getDetail(string $dispenseId, string $clinicId): ?array
    {
        return $this->dispenseRepository->findByIdAndClinicForDisplay($dispenseId, $clinicId);
    }

    public function confirmRead(string $dispenseId, string $clinicId, string $userId, ?\DateTimeInterface $occurredAt = null): array
    {
        $occurredAt = $occurredAt ?? now();

        return DB::transaction(function () use ($dispenseId, $clinicId, $userId, $occurredAt) {
            $dispense = $this->dispenseRepository->findByIdAndClinicForUpdate($dispenseId, $clinicId);

            if (!$dispense) {
                return ['found' => false, 'message' => 'Dispense not found'];
            }

            if ($dispense->status === 'RETIRED') {
                return ['found' => true, 'already_retired' => true, 'message' => 'Already confirmed read'];
            }

            $this->inventoryRepository->releaseReserved(
                $clinicId,
                $dispense->compartment_id,
                $dispense->product_id,
                $dispense->quantity
            );

            $this->dispenseRepository->markAsRetired($dispenseId, $occurredAt);

            $this->auditLogRepository->log([
                'clinic_id' => $clinicId,
                'actor_user_id' => $userId,
                'actor_type' => 'USER',
                'action' => 'dispense_read_confirmed',
                'entity_type' => 'Dispense',
                'entity_id' => $dispenseId,
                'occurred_at' => $occurredAt,
            ]);

            return ['found' => true, 'already_retired' => false, 'message' => 'Read confirmed'];
        });
    }
}
