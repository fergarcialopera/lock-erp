<?php

namespace Src\Audit\Application\Contracts;

interface AuditLogRepositoryInterface
{
    public function listByClinic(string $clinicId, int $limit = 100): array;

    public function log(array $data): void;
}
