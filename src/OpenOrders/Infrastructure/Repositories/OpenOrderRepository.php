<?php

namespace Src\OpenOrders\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\OpenOrders\Application\Contracts\OpenOrderRepositoryInterface;

class OpenOrderRepository implements OpenOrderRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $status = null): array
    {
        $query = DB::table('open_orders')->where('clinic_id', $clinicId);

        if ($status !== null) {
            $query->where('status', strtoupper($status));
        }

        return $query->get()->all();
    }

    public function findByExternalRef(string $clinicId, string $externalRef): ?object
    {
        return DB::table('open_orders')
            ->where('clinic_id', $clinicId)
            ->where('external_ref', $externalRef)
            ->first();
    }

    public function findByIdAndClinicForUpdate(string $id, string $clinicId): ?object
    {
        return DB::table('open_orders')
            ->where('clinic_id', $clinicId)
            ->where('id', $id)
            ->lockForUpdate()
            ->first();
    }

    public function create(array $data): object
    {
        $id = Str::ulid()->toString();
        $data['id'] = $id;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('open_orders')->insert($data);

        return DB::table('open_orders')->where('id', $id)->first();
    }

    public function markAsRetired(string $id, \DateTimeInterface $readAt): void
    {
        DB::table('open_orders')->where('id', $id)->update([
            'status' => 'RETIRED',
            'read_at' => $readAt,
            'updated_at' => now(),
        ]);
    }
}
