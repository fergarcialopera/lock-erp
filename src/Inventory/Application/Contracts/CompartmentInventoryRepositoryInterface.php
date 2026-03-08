<?php

namespace Src\Inventory\Application\Contracts;

interface CompartmentInventoryRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $compartmentId = null): array;

    /** @return array Lista para vista con product, compartment y locker resueltos. */
    public function listByClinicForDisplay(string $clinicId, ?string $compartmentId = null): array;

    public function updateOrCreate(string $clinicId, string $compartmentId, string $productId, int $qtyAvailable): void;

    public function find(string $clinicId, string $compartmentId, string $productId): ?object;

    public function findByIdAndClinic(string $id, string $clinicId): ?object;

    public function findForUpdate(string $clinicId, string $compartmentId, string $productId): ?object;

    public function delete(string $id, string $clinicId): void;

    public function addQuantity(string $clinicId, string $compartmentId, string $productId, int $quantity): object;

    /**
     * @throws \DomainException Si no existe inventario o no hay stock suficiente
     */
    public function removeQuantity(string $clinicId, string $compartmentId, string $productId, int $quantity): object;

    public function reserveStock(string $inventoryId, int $quantity): void;

    public function releaseReserved(string $clinicId, string $compartmentId, string $productId, int $quantity): void;
}
