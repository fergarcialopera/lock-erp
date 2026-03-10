<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class ClinicTest extends TestCase
{
    public function test_get_clinic_returns_clinic_data(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->getJson('/api/v1/clinic');

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name', 'settings']);
        $response->assertJson(['name' => 'Main Clinic']);
    }

    public function test_update_settings_requires_admin(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->patchJson('/api/v1/clinic/settings', [
            'open_latency_ms' => 300,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Settings updated successfully']);

        $clinic = DB::table('clinics')->where('id', $admin->clinic_id)->first();
        $settings = json_decode($clinic->settings, true);
        $this->assertEquals(300, $settings['open_latency_ms']);
    }

    public function test_update_settings_forbidden_for_non_admin(): void
    {
        $readonlyId = \Illuminate\Support\Str::ulid()->toString();
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();

        DB::table('users')->insert([
            'id' => $readonlyId,
            'clinic_id' => $admin->clinic_id,
            'name' => 'ReadOnly',
            'email' => 'readonly-clinic@lockerp.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role' => 'READONLY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::find($readonlyId), 'api');

        $response = $this->patchJson('/api/v1/clinic/settings', [
            'open_latency_ms' => 300,
        ]);

        $response->assertStatus(403);
    }

    public function test_clinic_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/clinic')->assertStatus(401);
        $this->patchJson('/api/v1/clinic/settings', ['open_latency_ms' => 100])->assertStatus(401);
    }
}
