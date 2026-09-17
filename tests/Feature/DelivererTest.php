<?php

namespace Tests\Feature;

use App\Events\LocationUpdated;
use App\Events\OrderStatusChanged;
use App\Models\Deliverer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DelivererTest extends TestCase
{
    use RefreshDatabase;

    private function livreurAvecProfil(array $attrs = []): User
    {
        $user = User::factory()->livreur()->create();
        Deliverer::factory()->create(array_merge(['user_id' => $user->id], $attrs));
        return $user->fresh();
    }

    public function test_un_livreur_peut_basculer_sa_disponibilite(): void
    {
        $livreur = $this->livreurAvecProfil(['disponible' => false]);

        $response = $this->actingAs($livreur, 'sanctum')
            ->patchJson('/api/v1/deliverer/availability', ['disponible' => true]);

        $response->assertStatus(200)->assertJson(['disponible' => true]);
        $this->assertTrue($livreur->deliverer->fresh()->disponible);
    }

    public function test_un_livreur_voit_les_commandes_disponibles_a_proximite(): void
    {
        $livreur = $this->livreurAvecProfil(['position_lat' => 9.3390, 'position_lng' => 2.6280]);
        Order::factory()->create(['statut' => 'en_attente', 'pickup_lat' => 9.3372, 'pickup_lng' => 2.6303]);
        Order::factory()->create(['statut' => 'livree']); // ne doit pas apparaître

        $response = $this->actingAs($livreur, 'sanctum')->getJson('/api/v1/deliverer/orders/available');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('orders'));
    }

    public function test_un_livreur_peut_accepter_une_commande_en_attente(): void
    {
        Event::fake();
        $livreur = $this->livreurAvecProfil();
        $order = Order::factory()->create(['statut' => 'en_attente']);

        $response = $this->actingAs($livreur, 'sanctum')
            ->postJson("/api/v1/deliverer/orders/{$order->id}/accept");

        $response->assertStatus(200)->assertJsonPath('statut', 'traitee');
        $this->assertEquals($livreur->id, $order->fresh()->deliverer_id);
        Event::assertDispatched(OrderStatusChanged::class);
    }

    public function test_impossible_d_accepter_une_commande_deja_prise(): void
    {
        $livreur = $this->livreurAvecProfil();
        $order = Order::factory()->create(['statut' => 'traitee']);

        $response = $this->actingAs($livreur, 'sanctum')
            ->postJson("/api/v1/deliverer/orders/{$order->id}/accept");

        $response->assertStatus(409);
    }

    public function test_un_livreur_peut_changer_le_statut_de_sa_commande(): void
    {
        $livreur = $this->livreurAvecProfil();
        $order = Order::factory()->create(['statut' => 'traitee', 'deliverer_id' => $livreur->id]);

        $response = $this->actingAs($livreur, 'sanctum')
            ->patchJson("/api/v1/deliverer/orders/{$order->id}/status", ['statut' => 'en_cours']);

        $response->assertStatus(200)->assertJsonPath('statut', 'en_cours');
    }

    public function test_un_livreur_ne_peut_pas_modifier_une_commande_qui_ne_lui_est_pas_assignee(): void
    {
        $livreur = $this->livreurAvecProfil();
        $autreLivreur = $this->livreurAvecProfil();
        $order = Order::factory()->create(['statut' => 'traitee', 'deliverer_id' => $autreLivreur->id]);

        $response = $this->actingAs($livreur, 'sanctum')
            ->patchJson("/api/v1/deliverer/orders/{$order->id}/status", ['statut' => 'en_cours']);

        $response->assertStatus(403);
    }

    public function test_l_envoi_de_position_diffuse_un_evenement_si_course_active(): void
    {
        Event::fake();
        $livreur = $this->livreurAvecProfil();
        Order::factory()->create(['statut' => 'en_cours', 'deliverer_id' => $livreur->id]);

        $response = $this->actingAs($livreur, 'sanctum')
            ->postJson('/api/v1/deliverer/location', ['lat' => 9.34, 'lng' => 2.63]);

        $response->assertStatus(200);
        Event::assertDispatched(LocationUpdated::class);
    }

    public function test_la_facture_est_calculee_selon_le_bareme(): void
    {
        $livreur = $this->livreurAvecProfil();
        $order = Order::factory()->create([
            'deliverer_id' => $livreur->id,
            'statut' => 'livree',
            'distance_km' => 3.4,
            'prix_final' => null,
        ]);

        $response = $this->actingAs($livreur, 'sanctum')
            ->getJson("/api/v1/deliverer/orders/{$order->id}/invoice");

        $response->assertStatus(200)->assertJson([
            'tarif_base' => 500,
            'tarif_par_km' => 300,
            'prix_final' => 500 + round(300 * 3.4), // 1520
        ]);
    }
}
