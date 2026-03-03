<?php

namespace Src\Inventory\Application\Services;

use Src\Inventory\Application\Contracts\CompartmentInventoryRepositoryInterface;

class InventoryService
{
    public function __construct(
        private readonly CompartmentInventoryRepositoryInterface $inventoryRepository
    ) {
    }

    public function list(string $clinicId, ?string $compartmentId = null): array
    {
        return $this->inventoryRepository->listByClinic($clinicId, $compartmentId);
    }

    public function adjust(string $clinicId, string $compartmentId, string $productId, int $qtyAvailable): void
    {
        $this->inventoryRepository->updateOrCreate($clinicId, $compartmentId, $productId, $qtyAvailable);
    }
}
