<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    private function getAdmin(): object
    {
        return DB::table('users')->where('email', 'admin@lockerp.com')->first();
    }

    public function test_index_returns_inventory(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->getJson('/api/v1/inventory');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }

    public function test_adjust_updates_inventory(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $inventory = DB::table('compartment_inventories')->first();

        $response = $this->postJson('/api/v1/inventory/adjust', [
            'compartment_id' => $inventory->compartment_id,
            'product_id' => $inventory->product_id,
            'qty_available' => 99,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Inventory adjusted']);

        $updated = DB::table('compartment_inventories')
            ->where('compartment_id', $inventory->compartment_id)
            ->where('product_id', $inventory->product_id)
            ->first();
        $this->assertEquals(99, $updated->qty_available);
    }

    public function test_add_increases_quantity(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $inventory = DB::table('compartment_inventories')->first();
        $initialQty = $inventory->qty_available;

        $response = $this->postJson('/api/v1/inventory/add', [
            'compartment_id' => $inventory->compartment_id,
            'product_id' => $inventory->product_id,
            'quantity' => 5,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'compartment_inventory']);

        $updated = DB::table('compartment_inventories')
            ->where('compartment_id', $inventory->compartment_id)
            ->where('product_id', $inventory->product_id)
            ->first();
        $this->assertEquals($initialQty + 5, $updated->qty_available);
    }

    public function test_remove_creates_dispense_and_decreases_available(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $inventory = DB::table('compartment_inventories')->where('qty_available', '>=', 2)->first();
        $initialAvailable = $inventory->qty_available;
        $initialReserved = $inventory->qty_reserved;

        $response = $this->postJson('/api/v1/inventory/remove', [
            'compartment_id' => $inventory->compartment_id,
            'product_id' => $inventory->product_id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'compartment_inventory', 'dispense']);

        $updated = DB::table('compartment_inventories')->where('id', $inventory->id)->first();
        $this->assertEquals($initialAvailable - 2, $updated->qty_available);
        $this->assertEquals($initialReserved + 2, $updated->qty_reserved);
    }

    public function test_destroy_deletes_inventory_entry(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $inventory = DB::table('compartment_inventories')->first();

        $response = $this->deleteJson("/api/v1/inventory/{$inventory->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('compartment_inventories', ['id' => $inventory->id]);
    }

    public function test_readonly_forbidden_on_adjust(): void
    {
        $admin = $this->getAdmin();
        $readonlyId = \Illuminate\Support\Str::ulid()->toString();

        DB::table('users')->insert([
            'id' => $readonlyId,
            'clinic_id' => $admin->clinic_id,
            'name' => 'ReadOnly',
            'email' => 'readonly-inv@lockerp.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role' => 'READONLY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::find($readonlyId), 'api');

        $inventory = DB::table('compartment_inventories')->first();

        $response = $this->postJson('/api/v1/inventory/adjust', [
            'compartment_id' => $inventory->compartment_id,
            'product_id' => $inventory->product_id,
            'qty_available' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_inventory_requires_authentication(): void
    {
        $this->getJson('/api/v1/inventory')->assertStatus(401);
    }
}
