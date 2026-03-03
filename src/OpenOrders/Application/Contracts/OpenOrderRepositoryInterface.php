<?php

namespace Src\OpenOrders\Application\Contracts;

interface OpenOrderRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $status = null): array;

    public function findByExternalRef(string $clinicId, string $externalRef): ?object;

    public function findByIdAndClinicForUpdate(string $id, string $clinicId): ?object;

    public function create(array $data): object;

    public function markAsRetired(string $id, \DateTimeInterface $readAt): void;
}
