<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class DispenseTest extends TestCase
{
    public function test_auth_returns_token()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@lockerp.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
    }

    public function test_inventory_remove_creates_dispense_and_moves_inventory_to_reserved()
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $inventory = DB::table('compartment_inventories')->where('qty_available', '>=', 1)->first();

        $response = $this->postJson('/api/v1/inventory/remove', [
            'compartment_id' => $inventory->compartment_id,
            'product_id' => $inventory->product_id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'compartment_inventory', 'dispense']);
        $dispenseId = $response->json('dispense.id');

        $updatedInv = DB::table('compartment_inventories')->where('id', $inventory->id)->first();
        $this->assertEquals($inventory->qty_available - 2, $updatedInv->qty_available);
        $this->assertEquals($inventory->qty_reserved + 2, $updatedInv->qty_reserved);

        $dispense = DB::table('dispenses')->where('id', $dispenseId)->first();
        $this->assertNotNull($dispense);
        $this->assertEquals('PENDING', $dispense->status);
        $this->assertEquals(2, $dispense->quantity);

        $audit = DB::table('audit_logs')
            ->where('entity_id', $dispenseId)
            ->where('action', 'dispense_requested')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_confirm_read_is_idempotent()
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $inventory = DB::table('compartment_inventories')->where('qty_available', '>=', 1)->first();
        $comp = DB::table('compartments')->where('id', $inventory->compartment_id)->first();
        $dispenseId = Str::ulid()->toString();

        DB::table('compartment_inventories')->where('id', $inventory->id)->update([
            'qty_reserved' => $inventory->qty_reserved + 1,
        ]);

        DB::table('dispenses')->insert([
            'id' => $dispenseId,
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

        $response = $this->postJson("/api/v1/dispenses/{$dispenseId}/confirm-read");
        $response->assertStatus(200);

        $response2 = $this->postJson("/api/v1/dispenses/{$dispenseId}/confirm-read");
        $response2->assertStatus(200);
        $this->assertEquals('Already confirmed read', $response2->json('message'));

        $updatedInv = DB::table('compartment_inventories')->where('id', $inventory->id)->first();
        $this->assertEquals($inventory->qty_reserved, $updatedInv->qty_reserved);
    }

    public function test_dispenses_index_returns_list(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->getJson('/api/v1/dispenses');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }

    public function test_dispenses_show_returns_detail(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $inventory = DB::table('compartment_inventories')->where('qty_available', '>=', 1)->first();
        $comp = DB::table('compartments')->where('id', $inventory->compartment_id)->first();

        $dispenseId = Str::ulid()->toString();
        DB::table('compartment_inventories')->where('id', $inventory->id)->update([
            'qty_reserved' => $inventory->qty_reserved + 1,
        ]);
        DB::table('dispenses')->insert([
            'id' => $dispenseId,
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

        $response = $this->getJson("/api/v1/dispenses/{$dispenseId}");

        $response->assertStatus(200);
        $response->assertJson(['id' => $dispenseId]);
    }

    public function test_dispenses_show_returns_404_for_unknown(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $fakeId = Str::ulid()->toString();

        $response = $this->getJson("/api/v1/dispenses/{$fakeId}");

        $response->assertStatus(404);
    }

    public function test_readonly_user_forbidden_on_adjust_inventory()
    {
        $readUserId = Str::ulid()->toString();
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();

        DB::table('users')->insert([
            'id' => $readUserId,
            'clinic_id' => $admin->clinic_id,
            'name' => 'ReadOnly User',
            'email' => 'readonly@lockerp.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role' => 'READONLY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::find($readUserId), 'api');

        $res = $this->postJson('/api/v1/inventory/adjust', [
            'compartment_id' => Str::ulid()->toString(),
            'product_id' => Str::ulid()->toString(),
            'qty_available' => 10,
        ]);

        $res->assertStatus(403);
    }
}
