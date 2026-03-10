<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Models\User;
use Tests\TestCase;

class ProductTest extends TestCase
{
    private function getAdmin(): object
    {
        return DB::table('users')->where('email', 'admin@lockerp.com')->first();
    }

    public function test_index_returns_products(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data)); // Seeder creates 2 products
    }

    public function test_show_returns_product_by_id(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $product = DB::table('products')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJson(['id' => $product->id, 'name' => $product->name]);
    }

    public function test_store_creates_product(): void
    {
        $this->actingAs(User::find($this->getAdmin()->id), 'api');

        $response = $this->postJson('/api/v1/products', [
            'sku' => 'NEW-001',
            'name' => 'Nuevo Producto',
            'barcode' => '111222333',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'sku', 'name', 'barcode']);
        $response->assertJson(['sku' => 'NEW-001', 'name' => 'Nuevo Producto']);

        $this->assertDatabaseHas('products', ['sku' => 'NEW-001']);
    }

    public function test_update_modifies_product(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $product = DB::table('products')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->patchJson("/api/v1/products/{$product->id}", [
            'name' => 'Nombre Actualizado',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['name' => 'Nombre Actualizado']);
    }

    public function test_destroy_deactivates_product(): void
    {
        $admin = $this->getAdmin();
        $this->actingAs(User::find($admin->id), 'api');

        $product = DB::table('products')->where('clinic_id', $admin->clinic_id)->first();

        $response = $this->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200);
        $updated = DB::table('products')->where('id', $product->id)->first();
        $this->assertFalse((bool) $updated->is_active);
    }

    public function test_products_require_authentication(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
    }
}
