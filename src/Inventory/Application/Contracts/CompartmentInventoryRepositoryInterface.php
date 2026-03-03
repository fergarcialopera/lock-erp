<?php

namespace Src\Inventory\Application\Contracts;

interface CompartmentInventoryRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $compartmentId = null): array;

    public function updateOrCreate(string $clinicId, string $compartmentId, string $productId, int $qtyAvailable): void;

    public function findForUpdate(string $clinicId, string $compartmentId, string $productId): ?object;

    public function reserveStock(string $inventoryId, int $quantity): void;

    public function releaseReserved(string $clinicId, string $compartmentId, string $productId, int $quantity): void;
}
