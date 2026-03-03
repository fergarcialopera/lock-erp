<?php

namespace Src\Audit\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Audit\Application\Contracts\AuditLogRepositoryInterface;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function listByClinic(string $clinicId, int $limit = 100): array
    {
        return DB::table('audit_logs')
            ->where('clinic_id', $clinicId)
            ->orderBy('occurred_at', 'desc')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function log(array $data): void
    {
        $data['id'] = $data['id'] ?? Str::ulid()->toString();
        $data['created_at'] = $data['created_at'] ?? now();
        $data['updated_at'] = $data['updated_at'] ?? now();

        DB::table('audit_logs')->insert($data);
    }
}
