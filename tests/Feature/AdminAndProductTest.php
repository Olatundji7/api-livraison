<?php

namespace Tests\Feature;

use App\Models\Deliverer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_driver(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/drivers', [
            'name' => 'Nouveau Livreur',
            'phone' => '+22990000001',
            'password' => 'password123',
            'vehicule_type' => 'moto',
        ]);

        $response->assertCreated()->assertJsonPath('driver.user.name', 'Nouveau Livreur');
        $this->assertDatabaseHas('users', ['phone' => '+22990000001', 'role' => 'driver']);
    }

    public function test_client_ne_voit_que_les_produits_actifs(): void
    {
        Product::create(['name' => 'Produit actif', 'price' => 1000, 'stock' => 2, 'is_active' => true]);
        Product::create(['name' => 'Produit caché', 'price' => 2000, 'stock' => 0, 'is_active' => false]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $this->assertCount(1, $response->json('products'));
    }

    public function test_reservation_expiree_ne_bloque_plus_un_livreur(): void
    {
        $client = User::factory()->client()->create();
        $driver = Deliverer::factory()->create([
            'status' => 'reserve',
            'reserved_by' => $client->id,
            'reserved_until' => now()->subMinute(),
        ]);

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/drivers/available');

        $response->assertOk();
        $this->assertSame('disponible', $driver->fresh()->status);
    }
}
