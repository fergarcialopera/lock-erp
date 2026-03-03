<?php

namespace Src\Identity\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Application\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function listByClinic(string $clinicId, bool $activeOnly = true): array
    {
        $query = DB::table('users')->where('clinic_id', $clinicId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get()->all();
    }

    public function findByIdAndClinic(string $id, string $clinicId): ?object
    {
        return DB::table('users')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->first();
    }

    public function existsByEmail(string $email, ?string $excludeId = null): bool
    {
        $query = DB::table('users')->where('email', $email);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function create(array $data): object
    {
        $id = Str::ulid()->toString();
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('users')->insert($data);

        $user = DB::table('users')->where('id', $id)->first();
        return (object) (array) $user;
    }

    public function update(string $id, string $clinicId, array $data): void
    {
        $data['updated_at'] = now();

        DB::table('users')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->update($data);
    }

    public function deactivate(string $id, string $clinicId): void
    {
        $this->update($id, $clinicId, ['is_active' => false]);
    }
}
