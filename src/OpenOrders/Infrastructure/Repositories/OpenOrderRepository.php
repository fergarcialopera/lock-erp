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

    public function listByClinicForDisplay(string $clinicId, ?string $status = null): array
    {
        $query = DB::table('open_orders as o')
            ->where('o.clinic_id', $clinicId)
            ->leftJoin('products as p', 'o.product_id', '=', 'p.id')
            ->leftJoin('lockers as l', 'o.locker_id', '=', 'l.id')
            ->leftJoin('compartments as c', 'o.compartment_id', '=', 'c.id')
            ->leftJoin('users as u', 'o.requested_by_user_id', '=', 'u.id')
            ->select(
                'o.id',
                'o.status',
                'o.quantity',
                'o.requested_at',
                'o.read_at',
                'o.external_ref',
                'o.created_at',
                'p.id as p_id', 'p.sku as p_sku', 'p.name as p_name', 'p.barcode as p_barcode',
                'l.id as l_id', 'l.code as l_code', 'l.name as l_name',
                'c.id as c_id', 'c.code as c_code',
                'u.id as u_id', 'u.name as u_name', 'u.email as u_email'
            );

        if ($status !== null) {
            $query->where('o.status', strtoupper($status));
        }

        $rows = $query->orderByDesc('o.created_at')->get();

        return array_map([$this, 'mapRowToOrderForDisplay'], $rows->all());
    }

    public function listLatestByClinic(string $clinicId, int $limit = 5): array
    {
        return DB::table('open_orders')
            ->where('clinic_id', $clinicId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function listLatestByClinicForDisplay(string $clinicId, int $limit = 5): array
    {
        $query = DB::table('open_orders as o')
            ->where('o.clinic_id', $clinicId)
            ->leftJoin('products as p', 'o.product_id', '=', 'p.id')
            ->leftJoin('lockers as l', 'o.locker_id', '=', 'l.id')
            ->leftJoin('compartments as c', 'o.compartment_id', '=', 'c.id')
            ->leftJoin('users as u', 'o.requested_by_user_id', '=', 'u.id')
            ->select(
                'o.id',
                'o.status',
                'o.quantity',
                'o.requested_at',
                'o.read_at',
                'o.created_at',
                'p.id as p_id', 'p.sku as p_sku', 'p.name as p_name',
                'l.id as l_id', 'l.code as l_code', 'l.name as l_name',
                'c.id as c_id', 'c.code as c_code',
                'u.id as u_id', 'u.name as u_name'
            )
            ->orderByDesc('o.created_at')
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
        $row = DB::table('open_orders as o')
            ->where('o.id', $id)
            ->where('o.clinic_id', $clinicId)
            ->leftJoin('products as p', 'o.product_id', '=', 'p.id')
            ->leftJoin('lockers as l', 'o.locker_id', '=', 'l.id')
            ->leftJoin('compartments as c', 'o.compartment_id', '=', 'c.id')
            ->leftJoin('users as u', 'o.requested_by_user_id', '=', 'u.id')
            ->select(
                'o.id',
                'o.status',
                'o.quantity',
                'o.requested_at',
                'o.read_at',
                'o.external_ref',
                'o.meta',
                'o.created_at',
                'o.updated_at',
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

    private function mapRowToOrderForDisplay(object $row): array
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
