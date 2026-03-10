<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class AuditTest extends TestCase
{
    public function test_index_returns_audit_logs_for_admin(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }

    public function test_audit_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertStatus(401);
    }

    public function test_readonly_user_forbidden_on_audit(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $readonlyId = \Illuminate\Support\Str::ulid()->toString();

        DB::table('users')->insert([
            'id' => $readonlyId,
            'clinic_id' => $admin->clinic_id,
            'name' => 'ReadOnly',
            'email' => 'readonly-audit@lockerp.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role' => 'READONLY',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::find($readonlyId), 'api');

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertStatus(403);
    }
}
