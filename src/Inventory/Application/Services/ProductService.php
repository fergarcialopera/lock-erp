<?php

namespace Src\Inventory\Application\Services;

use Src\Inventory\Application\Contracts\ProductRepositoryInterface;

class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function list(string $clinicId, bool $activeOnly = true): array
    {
        return $this->productRepository->listByClinic($clinicId, $activeOnly);
    }

    public function find(string $id, string $clinicId): ?object
    {
        return $this->productRepository->findByIdAndClinic($id, $clinicId);
    }

    public function create(string $clinicId, array $data): object
    {
        if ($this->productRepository->existsBySku($clinicId, $data['sku'])) {
            throw new \DomainException('A product with this SKU already exists');
        }

        return $this->productRepository->create([
            'clinic_id' => $clinicId,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'barcode' => $data['barcode'] ?? null,
            'is_active' => true,
        ]);
    }

    public function update(string $id, string $clinicId, array $data): ?object
    {
        $product = $this->productRepository->findByIdAndClinic($id, $clinicId);

        if (!$product) {
            return null;
        }

        if (isset($data['sku']) && $data['sku'] !== $product->sku) {
            if ($this->productRepository->existsBySku($clinicId, $data['sku'], $id)) {
                throw new \DomainException('A product with this SKU already exists');
            }
        }

        $updateData = array_intersect_key($data, array_flip(['sku', 'name', 'barcode', 'is_active']));
        if (!empty($updateData)) {
            $this->productRepository->update($id, $clinicId, $updateData);
        }

        return $this->productRepository->findByIdAndClinic($id, $clinicId);
    }

    public function deactivate(string $id, string $clinicId): bool
    {
        $product = $this->productRepository->findByIdAndClinic($id, $clinicId);

        if (!$product) {
            return false;
        }

        $this->productRepository->deactivate($id, $clinicId);

        return true;
    }
}
