<?php

namespace Src\Audit\Application\Services;

use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;

class AuditService
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository
    ) {
    }

    public function list(string $clinicId, int $limit = 100): array
    {
        return $this->auditLogRepository->listByClinic($clinicId, $limit);
    }
}
