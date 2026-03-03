<?php

namespace Src\Inventory\Application\Contracts;

interface ProductRepositoryInterface
{
    public function listByClinic(string $clinicId, bool $activeOnly = true): array;

    public function findByIdAndClinic(string $id, string $clinicId): ?object;

    public function existsBySku(string $clinicId, string $sku, ?string $excludeId = null): bool;

    public function create(array $data): object;

    public function update(string $id, string $clinicId, array $data): void;

    public function deactivate(string $id, string $clinicId): void;
}
