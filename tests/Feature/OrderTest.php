<?php

namespace Tests\Feature;

use App\Models\Deliverer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_peut_creer_une_commande_avec_prix_calcule(): void
    {
        $client = User::factory()->client()->create();

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/v1/orders', [
            'type' => 'livraison',
            'pickup_lat' => 9.3372,
            'pickup_lng' => 2.6303,
            'pickup_adresse' => 'Marché Arzèkè, Parakou',
            'dest_lat' => 9.3456,
            'dest_lng' => 2.6210,
            'dest_adresse' => 'Quartier Zongo, Parakou',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('order.statut', 'en_attente')
            ->assertJsonStructure(['order' => ['id', 'statut', 'distance_km', 'prix_estime', 'cree_le']]);

        $order = Order::first();
        $this->assertGreaterThan(0, $order->distance_km);
        // prix = 500 (base) + 300 * distance
        $this->assertEquals(500 + round(300 * $order->distance_km), $order->prix_estime);
    }

    public function test_un_livreur_ne_peut_pas_creer_de_commande(): void
    {
        $livreur = User::factory()->livreur()->create();

        $response = $this->actingAs($livreur, 'sanctum')->postJson('/api/v1/orders', [
            'type' => 'livraison',
            'pickup_lat' => 9.3372, 'pickup_lng' => 2.6303, 'pickup_adresse' => 'A',
            'dest_lat' => 9.3456, 'dest_lng' => 2.6210, 'dest_adresse' => 'B',
        ]);

        $response->assertStatus(403);
    }

    public function test_un_client_peut_voir_le_detail_de_sa_propre_commande(): void
    {
        $client = User::factory()->client()->create();
        $order = Order::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(200)->assertJsonPath('order.id', $order->id);
    }

    public function test_un_client_ne_peut_pas_voir_la_commande_d_un_autre_client(): void
    {
        $client = User::factory()->client()->create();
        $autreClient = User::factory()->client()->create();
        $order = Order::factory()->create(['client_id' => $autreClient->id]);

        $response = $this->actingAs($client, 'sanctum')->getJson("/api/v1/orders/{$order->id}");

        $response->assertStatus(403);
    }

    public function test_liste_des_livreurs_a_proximite(): void
    {
        $client = User::factory()->client()->create();
        Deliverer::factory()->create(['position_lat' => 9.3390, 'position_lng' => 2.6280]); // proche
        Deliverer::factory()->create(['position_lat' => 12.6, 'position_lng' => 3.0]); // loin (Kandi)

        $response = $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/deliverers/nearby?lat=9.3372&lng=2.6303&rayon_km=5');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('deliverers'));
    }
}
