<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_returns_summary_data(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'active_products_count',
            'available_lockers_count',
            'pending_dispenses_count',
            'has_low_stock',
            'latest_dispenses',
        ]);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }
}
