<?php

namespace Src\Lockers\Application\Services;

use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;
use Src\Lockers\Application\Contracts\LockerRepositoryInterface;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;

class LockerService
{
    public function __construct(
        private readonly LockerRepositoryInterface $lockerRepository,
        private readonly CompartmentRepositoryInterface $compartmentRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    public function list(string $clinicId, bool $activeOnly = true): array
    {
        return $this->lockerRepository->listByClinic($clinicId, $activeOnly);
    }

    public function findWithCompartments(string $id, string $clinicId): ?array
    {
        $locker = $this->lockerRepository->findByIdAndClinic($id, $clinicId);

        if (!$locker) {
            return null;
        }

        $compartments = $this->compartmentRepository->listByLocker($id, false);
        $lockerArray = (array) $locker;
        $lockerArray['compartments'] = $compartments;

        return $lockerArray;
    }

    public function create(string $clinicId, string $userId, array $data): object
    {
        if ($this->lockerRepository->existsByCode($clinicId, $data['code'])) {
            throw new \DomainException('A locker with this code already exists');
        }

        $locker = $this->lockerRepository->create([
            'clinic_id' => $clinicId,
            'code' => $data['code'],
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'is_active' => true,
        ]);

        $this->auditLogRepository->log([
            'clinic_id' => $clinicId,
            'actor_user_id' => $userId,
            'actor_type' => 'USER',
            'action' => 'locker_created',
            'entity_type' => 'Locker',
            'entity_id' => $locker->id,
            'occurred_at' => now(),
        ]);

        return $locker;
    }

    public function update(string $id, string $clinicId, string $userId, array $data): ?object
    {
        $locker = $this->lockerRepository->findByIdAndClinic($id, $clinicId);

        if (!$locker) {
            return null;
        }

        if (isset($data['code']) && $data['code'] !== $locker->code) {
            if ($this->lockerRepository->existsByCode($clinicId, $data['code'], $id)) {
                throw new \DomainException('A locker with this code already exists');
            }
        }

        $updateData = array_intersect_key($data, array_flip(['code', 'name', 'location', 'is_active']));
        if (!empty($updateData)) {
            $this->lockerRepository->update($id, $clinicId, $updateData);
            $this->auditLogRepository->log([
                'clinic_id' => $clinicId,
                'actor_user_id' => $userId,
                'actor_type' => 'USER',
                'action' => 'locker_updated',
                'entity_type' => 'Locker',
                'entity_id' => $id,
                'occurred_at' => now(),
                'payload' => $updateData,
            ]);
        }

        return $this->lockerRepository->findByIdAndClinic($id, $clinicId);
    }

    public function deactivate(string $id, string $clinicId, string $userId): bool
    {
        $locker = $this->lockerRepository->findByIdAndClinic($id, $clinicId);

        if (!$locker) {
            return false;
        }

        $this->lockerRepository->deactivate($id, $clinicId);
        $this->auditLogRepository->log([
            'clinic_id' => $clinicId,
            'actor_user_id' => $userId,
            'actor_type' => 'USER',
            'action' => 'locker_deactivated',
            'entity_type' => 'Locker',
            'entity_id' => $id,
            'occurred_at' => now(),
        ]);

        return true;
    }
}
