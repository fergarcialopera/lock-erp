<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class UserTest extends TestCase
{
    private function getAdmin(): object
    {
        return DB::table('users')->where('email', 'admin@lockerp.com')->first();
    }

    public function test_index_returns_users(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    public function test_show_returns_user_by_id(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->getJson("/api/v1/users/{$admin->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['password']);
        $response->assertJsonFragment(['email' => 'admin@lockerp.com']);
    }

    public function test_store_creates_user(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@lockerp.com',
            'password' => 'password123',
            'role' => 'RESPONSABLE',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'name', 'email', 'role']);
        $response->assertJson(['email' => 'nuevo@lockerp.com', 'role' => 'RESPONSABLE']);

        $this->assertDatabaseHas('users', ['email' => 'nuevo@lockerp.com']);
    }

    public function test_update_modifies_user(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->patchJson("/api/v1/users/{$admin->id}", [
            'name' => 'Admin Actualizado',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['name' => 'Admin Actualizado']);
    }

    public function test_destroy_deactivates_user(): void
    {
        $admin = $this->getAdmin();
        $newUserId = \Illuminate\Support\Str::ulid()->toString();

        DB::table('users')->insert([
            'id' => $newUserId,
            'clinic_id' => $admin->clinic_id,
            'name' => 'User to delete',
            'email' => 'todelete@lockerp.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role' => 'READONLY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->deleteJson("/api/v1/users/{$newUserId}");

        $response->assertStatus(200);
        $updated = DB::table('users')->where('id', $newUserId)->first();
        $this->assertFalse((bool) $updated->is_active);
    }

    public function test_users_require_authentication(): void
    {
        $this->getJson('/api/v1/users')->assertStatus(401);
    }
}
