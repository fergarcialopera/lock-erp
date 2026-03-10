<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class CompartmentTest extends TestCase
{
    private function getAdmin(): object
    {
        return DB::table('users')->where('email', 'admin@lockerp.com')->first();
    }

    public function test_index_returns_compartments(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->getJson('/api/v1/compartments');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data)); // Seeder creates 2 compartments
    }

    public function test_index_can_filter_by_locker_id(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $locker = DB::table('lockers')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->getJson("/api/v1/compartments?locker_id={$locker->id}");

        $response->assertStatus(200);
        $data = $response->json();
        foreach ($data as $comp) {
            $this->assertEquals($locker->id, $comp['locker_id']);
        }
    }

    public function test_show_returns_compartment_by_id(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $compartment = DB::table('compartments')
            ->join('lockers', 'lockers.id', '=', 'compartments.locker_id')
            ->where('lockers.clinic_id', $admin->clinic_id)
            ->select('compartments.*')
            ->first();

        $response = $this->getJson("/api/v1/compartments/{$compartment->id}");

        $response->assertStatus(200);
        $response->assertJson(['id' => $compartment->id]);
    }

    public function test_store_creates_compartment(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $locker = DB::table('lockers')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->postJson('/api/v1/compartments', [
            'locker_id' => $locker->id,
            'code' => 'P-03',
            'status' => 'AVAILABLE',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'locker_id', 'code', 'status']);
        $response->assertJson(['code' => 'P-03']);

        $this->assertDatabaseHas('compartments', ['code' => 'P-03', 'locker_id' => $locker->id]);
    }

    public function test_update_modifies_compartment(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $compartment = DB::table('compartments')
            ->join('lockers', 'lockers.id', '=', 'compartments.locker_id')
            ->where('lockers.clinic_id', $admin->clinic_id)
            ->select('compartments.*')
            ->first();

        $response = $this->patchJson("/api/v1/compartments/{$compartment->id}", [
            'status' => 'MAINTENANCE',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'MAINTENANCE']);
    }

    public function test_destroy_deactivates_compartment(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $compartment = DB::table('compartments')
            ->join('lockers', 'lockers.id', '=', 'compartments.locker_id')
            ->where('lockers.clinic_id', $admin->clinic_id)
            ->select('compartments.*')
            ->first();

        $response = $this->deleteJson("/api/v1/compartments/{$compartment->id}");

        $response->assertStatus(200);
        $updated = DB::table('compartments')->where('id', $compartment->id)->first();
        $this->assertFalse((bool) $updated->is_active);
    }

    public function test_compartments_require_authentication(): void
    {
        $this->getJson('/api/v1/compartments')->assertStatus(401);
    }
}
