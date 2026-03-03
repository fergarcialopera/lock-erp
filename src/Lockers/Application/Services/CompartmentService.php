<?php

namespace Src\Lockers\Application\Services;

use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;
use Src\Lockers\Application\Contracts\LockerRepositoryInterface;

class CompartmentService
{
    public function __construct(
        private readonly CompartmentRepositoryInterface $compartmentRepository,
        private readonly LockerRepositoryInterface $lockerRepository
    ) {
    }

    public function list(string $clinicId, ?string $lockerId = null, bool $activeOnly = true): array
    {
        return $this->compartmentRepository->listByClinic($clinicId, $lockerId, $activeOnly);
    }

    public function listByLocker(string $lockerId, string $clinicId, bool $activeOnly = true): ?array
    {
        if (!$this->lockerRepository->findByIdAndClinic($lockerId, $clinicId)) {
            return null;
        }

        return $this->compartmentRepository->listByLocker($lockerId, $activeOnly);
    }

    public function find(string $id, string $clinicId): ?object
    {
        return $this->compartmentRepository->findByIdInClinic($id, $clinicId);
    }

    public function create(string $clinicId, array $data): object
    {
        if (!$this->compartmentRepository->lockerBelongsToClinic($data['locker_id'], $clinicId)) {
            throw new \DomainException('Locker not found');
        }

        if ($this->compartmentRepository->existsByCodeInLocker($data['locker_id'], $data['code'])) {
            throw new \DomainException('A compartment with this code already exists in this locker');
        }

        return $this->compartmentRepository->create([
            'locker_id' => $data['locker_id'],
            'code' => $data['code'],
            'status' => $data['status'] ?? 'AVAILABLE',
        ]);
    }

    public function update(string $id, string $clinicId, array $data): ?object
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
        }

        return $this->compartmentRepository->findByIdInClinic($id, $clinicId);
    }

    public function deactivate(string $id, string $clinicId): bool
    {
        $compartment = $this->compartmentRepository->findByIdInClinic($id, $clinicId);

        if (!$compartment) {
            return false;
        }

        $this->compartmentRepository->deactivate($id);

        return true;
    }
}
