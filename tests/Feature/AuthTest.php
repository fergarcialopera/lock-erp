<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_returns_token_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@lockerp.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
        $response->assertJson(['token_type' => 'bearer']);
    }

    public function test_login_returns_401_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@lockerp.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid credentials']);
    }

    public function test_login_returns_404_when_user_not_found(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@lockerp.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'User not found']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
    }

    public function test_logout_returns_success_when_authenticated(): void
    {
        $admin = DB::table('users')->where('email', 'admin@lockerp.com')->first();
        $this->actingAs(User::find($admin->id), 'api');

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Successfully logged out']);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}
