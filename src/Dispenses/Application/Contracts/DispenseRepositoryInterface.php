<?php

namespace Src\Dispenses\Application\Contracts;

interface DispenseRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $status = null): array;

    /** @return array Lista de dispensaciones con product, locker, compartment y requested_by resueltos para vista. */
    public function listByClinicForDisplay(string $clinicId, ?string $status = null): array;

    /** @return array Últimas N dispensaciones enriquecidas para dashboard. */
    public function listLatestByClinicForDisplay(string $clinicId, int $limit = 5): array;

    public function listLatestByClinic(string $clinicId, int $limit = 5): array;

    public function findByExternalRef(string $clinicId, string $externalRef): ?object;

    public function findByIdAndClinicForUpdate(string $id, string $clinicId): ?object;

    /** @return array|null Dispensación con product, locker, compartment y requested_by para vista detalle. */
    public function findByIdAndClinicForDisplay(string $id, string $clinicId): ?array;

    public function create(array $data): object;

    public function markAsRetired(string $id, \DateTimeInterface $readAt): void;
}
