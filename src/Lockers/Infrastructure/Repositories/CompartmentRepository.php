<?php

namespace Src\Lockers\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Lockers\Application\Contracts\CompartmentRepositoryInterface;

class CompartmentRepository implements CompartmentRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $lockerId = null, bool $activeOnly = true): array
    {
        $query = DB::table('compartments')
            ->join('lockers', 'compartments.locker_id', '=', 'lockers.id')
            ->where('lockers.clinic_id', $clinicId)
            ->select('compartments.*');

        if ($lockerId !== null) {
            $query->where('compartments.locker_id', $lockerId);
        }

        if ($activeOnly) {
            $query->where('compartments.is_active', true);
        }

        return $query->orderBy('compartments.code')->get()->all();
    }

    public function listByLocker(string $lockerId, bool $activeOnly = true): array
    {
        $query = DB::table('compartments')->where('locker_id', $lockerId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('code')->get()->all();
    }

    public function findById(string $id): ?object
    {
        return DB::table('compartments')->where('id', $id)->first();
    }

    public function findByIdInClinic(string $id, string $clinicId): ?object
    {
        return DB::table('compartments')
            ->join('lockers', 'compartments.locker_id', '=', 'lockers.id')
            ->where('lockers.clinic_id', $clinicId)
            ->where('compartments.id', $id)
            ->select('compartments.*')
            ->first();
    }

    public function lockerBelongsToClinic(string $lockerId, string $clinicId): bool
    {
        return DB::table('lockers')
            ->where('id', $lockerId)
            ->where('clinic_id', $clinicId)
            ->exists();
    }

    public function existsByCodeInLocker(string $lockerId, string $code, ?string $excludeId = null): bool
    {
        $query = DB::table('compartments')
            ->where('locker_id', $lockerId)
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
        $data['status'] = $data['status'] ?? 'AVAILABLE';
        $data['is_active'] = true;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('compartments')->insert($data);

        return DB::table('compartments')->where('id', $id)->first();
    }

    public function update(string $id, array $data): void
    {
        $data['updated_at'] = now();

        DB::table('compartments')->where('id', $id)->update($data);
    }

    public function deactivate(string $id): void
    {
        $this->update($id, ['is_active' => false]);
    }
}
