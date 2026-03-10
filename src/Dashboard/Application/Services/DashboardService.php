<?php

namespace Src\Dashboard\Application\Services;

use Illuminate\Support\Facades\DB;
use Src\Inventory\Application\Contracts\ProductRepositoryInterface;
use Src\Lockers\Application\Contracts\LockerRepositoryInterface;
use Src\Dispenses\Application\Contracts\DispenseRepositoryInterface;

class DashboardService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LockerRepositoryInterface $lockerRepository,
        private readonly DispenseRepositoryInterface $dispenseRepository
    ) {
    }

    /**
     * @return array{
     *   active_products_count: int,
     *   available_lockers_count: int,
     *   pending_dispenses_count: int,
     *   has_low_stock: bool,
     *   latest_dispenses: array
     * }
     */
    public function getDashboardData(string $clinicId): array
    {
        $activeProducts = $this->productRepository->listByClinic($clinicId, true);
        $availableLockers = $this->lockerRepository->listByClinic($clinicId, true);
        $pendingDispenses = $this->dispenseRepository->listByClinic($clinicId, 'PENDING');
        $latestDispenses = $this->dispenseRepository->listLatestByClinicForDisplay($clinicId, 5);

        $hasLowStock = $this->checkHasLowStock($clinicId);

        return [
            'active_products_count' => count($activeProducts),
            'available_lockers_count' => count($availableLockers),
            'pending_dispenses_count' => count($pendingDispenses),
            'has_low_stock' => $hasLowStock,
            'latest_dispenses' => $latestDispenses,
        ];
    }

    private function checkHasLowStock(string $clinicId): bool
    {
        $invSub = DB::table('compartment_inventories')
            ->select('product_id', DB::raw('SUM(qty_available) as total'))
            ->where('clinic_id', $clinicId)
            ->groupBy('product_id');

        return DB::table('products as p')
            ->leftJoinSub($invSub, 'inv', 'inv.product_id', '=', 'p.id')
            ->where('p.clinic_id', $clinicId)
            ->where('p.is_active', true)
            ->whereRaw('COALESCE(inv.total, 0) <= 0')
            ->exists();
    }
}
