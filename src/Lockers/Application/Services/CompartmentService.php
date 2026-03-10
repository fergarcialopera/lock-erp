<?php

namespace Src\Lockers\Application\Services;

use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;

class CompartmentService
{
    public function __construct(
        private readonly CompartmentRepositoryInterface $compartmentRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    public function list(string $clinicId, ?string $lockerId = null, bool $activeOnly = true): array
    {
        return $this->compartmentRepository->listByClinic($clinicId, $lockerId, $activeOnly);
    }

    public function find(string $id, string $clinicId): ?object
    {
        return $this->compartmentRepository->findByIdInClinic($id, $clinicId);
    }

    public function create(string $clinicId, string $userId, array $data): object
    {
        if (!$this->compartmentRepository->lockerBelongsToClinic($data['locker_id'], $clinicId)) {
            throw new \DomainException('Locker not found');
        }

        if ($this->compartmentRepository->existsByCodeInLocker($data['locker_id'], $data['code'])) {
            throw new \DomainException('A compartment with this code already exists in this locker');
        }

        $compartment = $this->compartmentRepository->create([
            'locker_id' => $data['locker_id'],
            'code' => $data['code'],
            'status' => $data['status'] ?? 'AVAILABLE',
        ]);

        $this->auditLogRepository->log([
            'clinic_id' => $clinicId,
            'actor_user_id' => $userId,
            'actor_type' => 'USER',
            'action' => 'compartment_created',
            'entity_type' => 'Compartment',
            'entity_id' => $compartment->id,
            'occurred_at' => now(),
        ]);

        return $compartment;
    }

    public function update(string $id, string $clinicId, string $userId, array $data): ?object
    {
        $compartment = $this->compartmentRepository->findByIdInClinic($id, $clinicId);

        if (!$compartment) {
            return null;
        }

        if (isset($data['code']) && $data['code'] !== $compartment->code) {
            if ($this->compartmentRepository->existsByCodeInLocker($compartment->locker_id, $data['code'], $id)) {
                throw new \DomainException('A compartment with this code already exists in this locker');
            }
        }

        $updateData = array_intersect_key($data, array_flip(['code', 'status', 'is_active']));
        if (!empty($updateData)) {
            $this->compartmentRepository->update($id, $updateData);
            $this->auditLogRepository->log([
                'clinic_id' => $clinicId,
                'actor_user_id' => $userId,
                'actor_type' => 'USER',
                'action' => 'compartment_updated',
                'entity_type' => 'Compartment',
                'entity_id' => $id,
                'occurred_at' => now(),
                'payload' => $updateData,
            ]);
        }

        return $this->compartmentRepository->findByIdInClinic($id, $clinicId);
    }

    public function deactivate(string $id, string $clinicId, string $userId): bool
    {
        $compartment = $this->compartmentRepository->findByIdInClinic($id, $clinicId);

        if (!$compartment) {
            return false;
        }

        $this->compartmentRepository->deactivate($id);
        $this->auditLogRepository->log([
            'clinic_id' => $clinicId,
            'actor_user_id' => $userId,
            'actor_type' => 'USER',
            'action' => 'compartment_deactivated',
            'entity_type' => 'Compartment',
            'entity_id' => $id,
            'occurred_at' => now(),
        ]);

        return true;
    }
}
