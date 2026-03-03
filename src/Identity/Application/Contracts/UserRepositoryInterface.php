<?php

namespace Src\Identity\Application\Contracts;

interface UserRepositoryInterface
{
    public function listByClinic(string $clinicId, bool $activeOnly = true): array;

    public function findByIdAndClinic(string $id, string $clinicId): ?object;

    public function existsByEmail(string $email, ?string $excludeId = null): bool;

    public function create(array $data): object;

    public function update(string $id, string $clinicId, array $data): void;

    public function deactivate(string $id, string $clinicId): void;
}
