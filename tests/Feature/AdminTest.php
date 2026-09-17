<?php

namespace Tests\Feature;

use App\Models\Deliverer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_admin_ne_peut_pas_acceder_aux_routes_admin(): void
    {
        $client = User::factory()->client()->create();

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/v1/admin/orders');

        $response->assertStatus(403);
    }

    public function test_admin_voit_les_livreurs_en_attente_de_validation(): void
    {
        $admin = User::factory()->admin()->create();
        Deliverer::factory()->enAttente()->create();
        Deliverer::factory()->create(); // déjà validé, ne doit pas apparaître

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/deliverers/pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('deliverers'));
    }

    public function test_admin_peut_valider_un_livreur(): void
    {
        $admin = User::factory()->admin()->create();
        $deliverer = Deliverer::factory()->enAttente()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/deliverers/{$deliverer->id}/validate", ['valide' => true]);

        $response->assertStatus(200)->assertJsonPath('statut_validation', 'valide');
    }

    public function test_admin_peut_filtrer_les_commandes_par_statut(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->create(['statut' => 'livree']);
        Order::factory()->create(['statut' => 'en_attente']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/orders?statut=livree');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_peut_assigner_manuellement_une_commande(): void
    {
        $admin = User::factory()->admin()->create();
        $deliverer = Deliverer::factory()->create();
        $order = Order::factory()->create(['statut' => 'en_attente']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/assign", ['deliverer_id' => $deliverer->user_id]);

        $response->assertStatus(200)->assertJsonPath('statut', 'traitee');
        $this->assertEquals($deliverer->user_id, $order->fresh()->deliverer_id);
    }

    public function test_admin_peut_creer_un_autre_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/admins', [
            'nom' => 'Second Admin',
            'telephone' => '+22990001111',
            'mot_de_passe' => 'password123',
        ]);

        $response->assertStatus(201)->assertJsonPath('admin.nom', 'Second Admin');
        $this->assertDatabaseHas('users', ['telephone' => '+22990001111', 'role' => 'admin']);
    }
}
