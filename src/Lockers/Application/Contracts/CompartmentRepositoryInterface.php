<?php

namespace Src\Lockers\Application\Contracts;

interface CompartmentRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $lockerId = null, bool $activeOnly = true): array;

    public function listByLocker(string $lockerId, bool $activeOnly = true): array;

    public function findById(string $id): ?object;

    public function findByIdInClinic(string $id, string $clinicId): ?object;

    public function lockerBelongsToClinic(string $lockerId, string $clinicId): bool;

    public function existsByCodeInLocker(string $lockerId, string $code, ?string $excludeId = null): bool;

    public function create(array $data): object;

    public function update(string $id, array $data): void;

    public function deactivate(string $id): void;
}
