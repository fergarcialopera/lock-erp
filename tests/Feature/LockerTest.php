<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class LockerTest extends TestCase
{
    private function getAdmin(): object
    {
        return DB::table('users')->where('email', 'admin@lockerp.com')->first();
    }

    public function test_index_returns_lockers(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->getJson('/api/v1/lockers');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    public function test_show_returns_locker_with_compartments(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $locker = DB::table('lockers')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->getJson("/api/v1/lockers/{$locker->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'code', 'name', 'compartments']);
        $response->assertJson(['code' => 'LCK-01']);
    }

    public function test_store_creates_locker(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->postJson('/api/v1/lockers', [
            'code' => 'LCK-02',
            'name' => 'Locker Secundario',
            'location' => 'Sala 2',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'code', 'name', 'location']);
        $response->assertJson(['code' => 'LCK-02', 'name' => 'Locker Secundario']);

        $this->assertDatabaseHas('lockers', ['code' => 'LCK-02']);
    }

    public function test_update_modifies_locker(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $locker = DB::table('lockers')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->patchJson("/api/v1/lockers/{$locker->id}", [
            'name' => 'Locker Renombrado',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['name' => 'Locker Renombrado']);
    }

    public function test_destroy_deactivates_locker(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $locker = DB::table('lockers')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->deleteJson("/api/v1/lockers/{$locker->id}");

        $response->assertStatus(200);
        $updated = DB::table('lockers')->where('id', $locker->id)->first();
        $this->assertFalse((bool) $updated->is_active);
    }

    public function test_lockers_require_authentication(): void
    {
        $this->getJson('/api/v1/lockers')->assertStatus(401);
    }
}
