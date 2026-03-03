<?php

namespace Src\Lockers\Application\Services;

use Src\Lockers\Application\Contracts\LockerRepositoryInterface;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;

class LockerService
{
    public function __construct(
        private readonly LockerRepositoryInterface $lockerRepository,
        private readonly CompartmentRepositoryInterface $compartmentRepository
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

    public function create(string $clinicId, array $data): object
    {
        if ($this->lockerRepository->existsByCode($clinicId, $data['code'])) {
            throw new \DomainException('A locker with this code already exists');
        }

        return $this->lockerRepository->create([
            'clinic_id' => $clinicId,
            'code' => $data['code'],
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'is_active' => true,
        ]);
    }

    public function update(string $id, string $clinicId, array $data): ?object
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
        }

        return $this->lockerRepository->findByIdAndClinic($id, $clinicId);
    }

    public function deactivate(string $id, string $clinicId): bool
    {
        $locker = $this->lockerRepository->findByIdAndClinic($id, $clinicId);

        if (!$locker) {
            return false;
        }

        $this->lockerRepository->deactivate($id, $clinicId);

        return true;
    }
}
