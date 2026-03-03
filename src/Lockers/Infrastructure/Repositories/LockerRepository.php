<?php

namespace Src\Lockers\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Lockers\Application\Contracts\LockerRepositoryInterface;

class LockerRepository implements LockerRepositoryInterface
{
    public function listByClinic(string $clinicId, bool $activeOnly = true): array
    {
        $query = DB::table('lockers')->where('clinic_id', $clinicId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('code')->get()->all();
    }

    public function findByIdAndClinic(string $id, string $clinicId): ?object
    {
        return DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();
    }

    public function existsByCode(string $clinicId, string $code, ?string $excludeId = null): bool
    {
        $query = DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('code', $code);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function create(array $data): object
    {
        $id = Str::ulid()->toString();
        $data['id'] = $id;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('lockers')->insert($data);

        return DB::table('lockers')->where('id', $id)->first();
    }

    public function update(string $id, string $clinicId, array $data): void
    {
        $data['updated_at'] = now();

        DB::table('lockers')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update($data);
    }

    public function deactivate(string $id, string $clinicId): void
    {
        $this->update($id, $clinicId, ['is_active' => false]);
    }
}
