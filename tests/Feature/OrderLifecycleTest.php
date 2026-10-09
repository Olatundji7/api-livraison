<?php

namespace Tests\Feature;

use App\Models\Deliverer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function creerCommande(User $client): Order
    {
        $response = $this->actingAs($client, 'sanctum')->postJson('/api/orders', [
            'type' => 'livraison',
            'pickup_address' => 'Marché Arzèkè',
            'pickup_latitude' => 9.3372,
            'pickup_longitude' => 2.6303,
            'destination_address' => 'Quartier Zongo',
            'destination_latitude' => 9.3456,
            'destination_longitude' => 2.6210,
        ]);

        $response->assertStatus(201)->assertJsonPath('order.status', 'en_attente');
        return Order::findOrFail($response->json('order.id'));
    }

    public function test_client_cree_une_commande_sans_choisir_de_livreur(): void
    {
        $client = User::factory()->client()->create();
        $order = $this->creerCommande($client);

        $this->assertNull($order->driver_id);
        $this->assertEquals('en_attente', $order->status);
    }

    public function test_course_personnelle_n_exige_pas_d_adresse_de_reception(): void
    {
        $client = User::factory()->client()->create();

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/orders', [
            'type' => 'course_personnelle',
            'pickup_address' => 'Quartier Zongo',
            'pickup_latitude' => 9.3372,
            'pickup_longitude' => 2.6303,
        ]);

        $response->assertStatus(201)->assertJsonPath('order.status', 'en_attente');
        $this->assertNull($response->json('order.destination_address'));
    }

    public function test_cycle_complet_prise_en_charge_jusqu_a_livraison(): void
    {
        $client = User::factory()->client()->create();
        $driverUser = User::factory()->driver()->create();
        Deliverer::factory()->create(['user_id' => $driverUser->id, 'status' => 'disponible']);

        $order = $this->creerCommande($client);

        $available = $this->actingAs($driverUser, 'sanctum')->getJson('/api/driver/orders/available');
        $available->assertStatus(200);
        $this->assertTrue(collect($available->json('orders'))->contains('id', $order->id));

        $this->actingAs($driverUser, 'sanctum')
            ->postJson("/api/driver/orders/{$order->id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('order.status', 'livreur_accepte');

        $this->actingAs($driverUser, 'sanctum')->postJson("/api/driver/orders/{$order->id}/start")
            ->assertJsonPath('order.status', 'en_cours');

        $this->actingAs($driverUser, 'sanctum')->postJson("/api/driver/orders/{$order->id}/pickup")
            ->assertJsonPath('order.status', 'colis_recupere');

        $this->actingAs($driverUser, 'sanctum')->postJson("/api/driver/orders/{$order->id}/deliver")
            ->assertJsonPath('order.status', 'livree');

        $this->assertEquals('disponible', $driverUser->deliverer->fresh()->status);
    }

    public function test_un_autre_livreur_ne_peut_pas_modifier_une_commande_deja_prise(): void
    {
        $client = User::factory()->client()->create();
        $driverA = User::factory()->driver()->create();
        $driverB = User::factory()->driver()->create();
        Deliverer::factory()->create(['user_id' => $driverA->id, 'status' => 'disponible']);
        Deliverer::factory()->create(['user_id' => $driverB->id, 'status' => 'disponible']);
        $order = $this->creerCommande($client);

        $this->actingAs($driverA, 'sanctum')->postJson("/api/driver/orders/{$order->id}/accept")->assertStatus(200);
        $this->actingAs($driverB, 'sanctum')->postJson("/api/driver/orders/{$order->id}/accept")->assertStatus(403);
    }

    public function test_client_retrouve_sa_commande_active(): void
    {
        $client = User::factory()->client()->create();
        $order = $this->creerCommande($client);

        $active = $this->actingAs($client, 'sanctum')->getJson('/api/orders/active');
        $active->assertStatus(200)->assertJsonPath('order.id', $order->id);
    }
}
