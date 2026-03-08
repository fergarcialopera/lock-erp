<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Src\Identity\Infrastructure\Models\User;
use Illuminate\Support\Str;

class OpenOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use an SQLite in-memory DB or configured test DB. Assuming testing using standard DB schema.
        DB::beginTransaction(); // run test in transaction to rollback later
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_auth_returns_token()
    {
        // Seeder already created admin@lockerp.com
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@lockerp.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_inventory_remove_creates_order_and_moves_inventory_to_reserved()
    {
        // Get seeded admin
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        // Look for the seeded compartment and product
        $inventory = DB::table('compartment_inventories')->where('qty_available', '>=', 1)->first();

        // Retirar desde inventario: reserva stock y crea orden
        $response = $this->postJson('/api/v1/inventory/remove', [
            'compartment_id' => $inventory->compartment_id,
            'product_id' => $inventory->product_id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'compartment_inventory', 'order']);
        $orderId = $response->json('order.id');

        // Assert Inventory updated (disponible → reservado)
        $updatedInv = DB::table('compartment_inventories')->where('id', $inventory->id)->first();
        $this->assertEquals($inventory->qty_available - 2, $updatedInv->qty_available);
        $this->assertEquals($inventory->qty_reserved + 2, $updatedInv->qty_reserved);

        // Assert order created
        $order = DB::table('open_orders')->where('id', $orderId)->first();
        $this->assertNotNull($order);
        $this->assertEquals('PENDING', $order->status);
        $this->assertEquals(2, $order->quantity);

        // Assert Audit
        $audit = DB::table('audit_logs')
            ->where('entity_id', $orderId)
            ->where('action', 'open_order_requested')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_confirm_read_is_idempotent()
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        // First we need a PENDING order
        $inventory = DB::table('compartment_inventories')->where('qty_available', '>=', 1)->first();

        $comp = DB::table('compartments')->where('id', $inventory->compartment_id)->first();
        $orderId = Str::ulid()->toString();

        DB::table('compartment_inventories')->where('id', $inventory->id)->update([
            'qty_reserved' => $inventory->qty_reserved + 1
        ]);

        DB::table('open_orders')->insert([
            'id' => $orderId,
            'clinic_id' => $admin->clinic_id,
            'requested_by_user_id' => $admin->id,
            'locker_id' => $comp->locker_id,
            'compartment_id' => $inventory->compartment_id,
            'product_id' => $inventory->product_id,
            'quantity' => 1,
            'status' => 'PENDING',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Confirm Read First Time
        $response = $this->postJson("/api/v1/open-orders/{$orderId}/confirm-read");
        $response->assertStatus(200);

        // 2. Confirm Read Second Time (idempotent)
        $response2 = $this->postJson("/api/v1/open-orders/{$orderId}/confirm-read");
        $response2->assertStatus(200);
        $this->assertEquals('Already confirmed read', $response2->json('message'));

        // Assert reservations dropped
        $updatedInv = DB::table('compartment_inventories')->where('id', $inventory->id)->first();
        $this->assertEquals($inventory->qty_reserved, $updatedInv->qty_reserved); // Returns to original - 1 + 1 (basically -1 logic inside endpoint)
    }

    public function test_readonly_user_forbidden_on_adjust_inventory()
    {
        // Insert a readonly user
        $readUserId = Str::ulid()->toString();
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();

        DB::table('users')->insert([
            'id' => $readUserId,
            'clinic_id' => $admin->clinic_id,
            'name' => 'ReadOnly User',
            'email' => 'readonly@lockerp.com',
            'password' => 'secret',
            'role' => 'READONLY',
            'is_active' => true,
        ]);

        $this->actingAs(User::find($readUserId), 'api');

        // Try Adjust Inventory
        $res = $this->postJson('/api/v1/inventory/adjust', [
            'compartment_id' => Str::ulid()->toString(),
            'product_id' => Str::ulid()->toString(),
            'qty_available' => 10,
        ]);

        $res->assertStatus(403);
    }
}
