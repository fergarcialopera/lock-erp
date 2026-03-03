<?php

namespace Src\Lockers\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Lockers\Application\Contracts\ClinicRepositoryInterface;

class ClinicRepository implements ClinicRepositoryInterface
{
    public function findById(string $id): ?object
    {
        return DB::table('clinics')->where('id', $id)->first();
    }

    public function updateSettings(string $id, array $settings): void
    {
        DB::table('clinics')->where('id', $id)->update([
            'settings' => json_encode($settings),
        ]);
    }
}
