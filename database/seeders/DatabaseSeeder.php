<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $clinicId = Str::ulid()->toString();

        DB::table('clinics')->insert([
            'id' => $clinicId,
            'name' => 'Main Clinic',
            'settings' => json_encode(['open_latency_ms' => 200]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => Str::ulid()->toString(),
            'clinic_id' => $clinicId,
            'name' => 'Admin User',
            'email' => 'admin@lockerp.com',
            'password' => Hash::make('password123'),
            'role' => 'ADMIN',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lockerId = Str::ulid()->toString();
        DB::table('lockers')->insert([
            'id' => $lockerId,
            'clinic_id' => $clinicId,
            'code' => 'LCK-01',
            'name' => 'Locker Principal',
            'location' => 'Recepción',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $comp1Id = Str::ulid()->toString();
        $comp2Id = Str::ulid()->toString();
        DB::table('compartments')->insert([
            [
                'id' => $comp1Id,
                'locker_id' => $lockerId,
                'code' => 'P-01',
                'status' => 'AVAILABLE',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $comp2Id,
                'locker_id' => $lockerId,
                'code' => 'P-02',
                'status' => 'AVAILABLE',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $prod1Id = Str::ulid()->toString();
        $prod2Id = Str::ulid()->toString();
        DB::table('products')->insert([
            [
                'id' => $prod1Id,
                'clinic_id' => $clinicId,
                'sku' => 'MED-001',
                'name' => 'Paracetamol',
                'barcode' => '1234567890',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $prod2Id,
                'clinic_id' => $clinicId,
                'sku' => 'SUP-001',
                'name' => 'Vendas',
                'barcode' => '0987654321',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        DB::table('compartment_inventories')->insert([
            [
                'id' => Str::ulid()->toString(),
                'clinic_id' => $clinicId,
                'compartment_id' => $comp1Id,
                'product_id' => $prod1Id,
                'qty_available' => 10,
                'qty_reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::ulid()->toString(),
                'clinic_id' => $clinicId,
                'compartment_id' => $comp2Id,
                'product_id' => $prod2Id,
                'qty_available' => 5,
                'qty_reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $this->command->info("Seeder executed. Admin email: admin@lockerp.com | password: password123");
    }
}
