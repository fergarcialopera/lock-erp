<?php

namespace Src\OpenOrders\Application\Contracts;

interface OpenOrderRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $status = null): array;

    /** @return array Lista de órdenes con product, locker, compartment y requested_by resueltos para vista. */
    public function listByClinicForDisplay(string $clinicId, ?string $status = null): array;

    /** @return array Últimas N órdenes enriquecidas para dashboard. */
    public function listLatestByClinicForDisplay(string $clinicId, int $limit = 5): array;

    public function listLatestByClinic(string $clinicId, int $limit = 5): array;

    public function findByExternalRef(string $clinicId, string $externalRef): ?object;

    public function findByIdAndClinicForUpdate(string $id, string $clinicId): ?object;

    /** @return array|null Orden con product, locker, compartment y requested_by para vista detalle. */
    public function findByIdAndClinicForDisplay(string $id, string $clinicId): ?array;

    public function create(array $data): object;

    public function markAsRetired(string $id, \DateTimeInterface $readAt): void;
}
