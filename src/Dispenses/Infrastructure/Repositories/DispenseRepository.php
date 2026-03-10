<?php

namespace Src\Dispenses\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Dispenses\Application\Contracts\DispenseRepositoryInterface;

class DispenseRepository implements DispenseRepositoryInterface
{
    public function listByClinic(string $clinicId, ?string $status = null): array
    {
        $query = DB::table('dispenses')->where('clinic_id', $clinicId);

        if ($status !== null) {
            $query->where('status', strtoupper($status));
        }

        return $query->get()->all();
    }

    public function listByClinicForDisplay(string $clinicId, ?string $status = null): array
    {
        $query = DB::table('dispenses as d')
            ->where('d.clinic_id', $clinicId)
            ->leftJoin('products as p', 'd.product_id', '=', 'p.id')
            ->leftJoin('lockers as l', 'd.locker_id', '=', 'l.id')
            ->leftJoin('compartments as c', 'd.compartment_id', '=', 'c.id')
            ->leftJoin('users as u', 'd.requested_by_user_id', '=', 'u.id')
            ->select(
                'd.id',
                'd.status',
                'd.quantity',
                'd.requested_at',
                'd.read_at',
                'd.external_ref',
                'd.created_at',
                'p.id as p_id', 'p.sku as p_sku', 'p.name as p_name', 'p.barcode as p_barcode',
                'l.id as l_id', 'l.code as l_code', 'l.name as l_name',
                'c.id as c_id', 'c.code as c_code',
                'u.id as u_id', 'u.name as u_name', 'u.email as u_email'
            );

        if ($status !== null) {
            $query->where('d.status', strtoupper($status));
        }

        $rows = $query->orderByDesc('d.created_at')->get();

        return array_map([$this, 'mapRowToDispenseForDisplay'], $rows->all());
    }

    public function listLatestByClinic(string $clinicId, int $limit = 5): array
    {
        return DB::table('dispenses')
            ->where('clinic_id', $clinicId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function listLatestByClinicForDisplay(string $clinicId, int $limit = 5): array
    {
        $query = DB::table('dispenses as d')
            ->where('d.clinic_id', $clinicId)
            ->leftJoin('products as p', 'd.product_id', '=', 'p.id')
            ->leftJoin('lockers as l', 'd.locker_id', '=', 'l.id')
            ->leftJoin('compartments as c', 'd.compartment_id', '=', 'c.id')
            ->leftJoin('users as u', 'd.requested_by_user_id', '=', 'u.id')
            ->select(
                'd.id',
                'd.status',
                'd.quantity',
                'd.requested_at',
                'd.read_at',
                'd.created_at',
                'p.id as p_id', 'p.sku as p_sku', 'p.name as p_name',
                'l.id as l_id', 'l.code as l_code', 'l.name as l_name',
                'c.id as c_id', 'c.code as c_code',
                'u.id as u_id', 'u.name as u_name'
            )
            ->orderByDesc('d.created_at')
            ->limit($limit);

        $rows = $query->get();

        return array_map(function ($row) {
            return [
                'id' => $row->id,
                'status' => $row->status,
                'quantity' => (int) $row->quantity,
                'requested_at' => $row->requested_at,
                'read_at' => $row->read_at,
                'created_at' => $row->created_at,
                'product' => $row->p_id ? ['id' => $row->p_id, 'sku' => $row->p_sku, 'name' => $row->p_name] : null,
                'locker' => $row->l_id ? ['id' => $row->l_id, 'code' => $row->l_code, 'name' => $row->l_name] : null,
                'compartment' => $row->c_id ? ['id' => $row->c_id, 'code' => $row->c_code] : null,
                'requested_by' => $row->u_id ? ['id' => $row->u_id, 'name' => $row->u_name] : null,
            ];
        }, $rows->all());
    }

    public function findByIdAndClinicForDisplay(string $id, string $clinicId): ?array
    {
        $row = DB::table('dispenses as d')
            ->where('d.id', $id)
            ->where('d.clinic_id', $clinicId)
            ->leftJoin('products as p', 'd.product_id', '=', 'p.id')
            ->leftJoin('lockers as l', 'd.locker_id', '=', 'l.id')
            ->leftJoin('compartments as c', 'd.compartment_id', '=', 'c.id')
            ->leftJoin('users as u', 'd.requested_by_user_id', '=', 'u.id')
            ->select(
                'd.id',
                'd.status',
                'd.quantity',
                'd.requested_at',
                'd.read_at',
                'd.external_ref',
                'd.meta',
                'd.created_at',
                'd.updated_at',
                'p.id as p_id', 'p.sku as p_sku', 'p.name as p_name', 'p.barcode as p_barcode',
                'l.id as l_id', 'l.code as l_code', 'l.name as l_name', 'l.location as l_location',
                'c.id as c_id', 'c.code as c_code', 'c.status as c_status',
                'u.id as u_id', 'u.name as u_name', 'u.email as u_email'
            )
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'id' => $row->id,
            'status' => $row->status,
            'quantity' => (int) $row->quantity,
            'requested_at' => $row->requested_at,
            'read_at' => $row->read_at,
            'external_ref' => $row->external_ref,
            'meta' => $row->meta,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'product' => $row->p_id ? ['id' => $row->p_id, 'sku' => $row->p_sku, 'name' => $row->p_name, 'barcode' => $row->p_barcode] : null,
            'locker' => $row->l_id ? ['id' => $row->l_id, 'code' => $row->l_code, 'name' => $row->l_name, 'location' => $row->l_location] : null,
            'compartment' => $row->c_id ? ['id' => $row->c_id, 'code' => $row->c_code, 'status' => $row->c_status] : null,
            'requested_by' => $row->u_id ? ['id' => $row->u_id, 'name' => $row->u_name, 'email' => $row->u_email] : null,
        ];
    }

    private function mapRowToDispenseForDisplay(object $row): array
    {
        return [
            'id' => $row->id,
            'status' => $row->status,
            'quantity' => (int) $row->quantity,
            'requested_at' => $row->requested_at,
            'read_at' => $row->read_at,
            'external_ref' => $row->external_ref,
            'created_at' => $row->created_at,
            'product' => $row->p_id ? ['id' => $row->p_id, 'sku' => $row->p_sku, 'name' => $row->p_name, 'barcode' => $row->p_barcode] : null,
            'locker' => $row->l_id ? ['id' => $row->l_id, 'code' => $row->l_code, 'name' => $row->l_name] : null,
            'compartment' => $row->c_id ? ['id' => $row->c_id, 'code' => $row->c_code] : null,
            'requested_by' => $row->u_id ? ['id' => $row->u_id, 'name' => $row->u_name, 'email' => $row->u_email] : null,
        ];
    }

    public function findByExternalRef(string $clinicId, string $externalRef): ?object
    {
        return DB::table('dispenses')
            ->where('clinic_id', $clinicId)
            ->where('external_ref', $externalRef)
            ->first();
    }

    public function findByIdAndClinicForUpdate(string $id, string $clinicId): ?object
    {
        return DB::table('dispenses')
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

        DB::table('dispenses')->insert($data);

        return DB::table('dispenses')->where('id', $id)->first();
    }

    public function markAsRetired(string $id, \DateTimeInterface $readAt): void
    {
        DB::table('dispenses')->where('id', $id)->update([
            'status' => 'RETIRED',
            'read_at' => $readAt,
            'updated_at' => now(),
        ]);
    }
}
