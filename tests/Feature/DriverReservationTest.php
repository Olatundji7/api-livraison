<?php

namespace Tests\Feature;

use App\Models\Deliverer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_liste_des_livreurs_disponibles(): void
    {
        $client = User::factory()->client()->create();
        Deliverer::factory()->create();
        Deliverer::factory()->horsLigne()->create();

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/drivers/available');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('drivers'));
    }

    public function test_recherche_de_livreurs_a_proximite(): void
    {
        $client = User::factory()->client()->create();
        Deliverer::factory()->create(['latitude' => 9.3390, 'longitude' => 2.6280]); // proche
        Deliverer::factory()->create(['latitude' => 12.6, 'longitude' => 3.0]); // loin

        $response = $this->actingAs($client, 'sanctum')
            ->getJson('/api/drivers/nearby?latitude=9.3372&longitude=2.6303&radius=5');

        $this->assertCount(1, $response->json('drivers'));
    }

    public function test_un_client_peut_reserver_un_livreur_disponible(): void
    {
        $client = User::factory()->client()->create();
        $driver = Deliverer::factory()->create();

        $response = $this->actingAs($client, 'sanctum')->postJson("/api/drivers/{$driver->id}/reserve");

        $response->assertStatus(200)->assertJsonPath('driver.status', 'reserve');
        $this->assertEquals($client->id, $driver->fresh()->reserved_by);
    }

    public function test_impossible_de_reserver_un_livreur_deja_reserve(): void
    {
        $clientA = User::factory()->client()->create();
        $clientB = User::factory()->client()->create();
        $driver = Deliverer::factory()->create();

        $this->actingAs($clientA, 'sanctum')->postJson("/api/drivers/{$driver->id}/reserve");
        $response = $this->actingAs($clientB, 'sanctum')->postJson("/api/drivers/{$driver->id}/reserve");

        $response->assertStatus(409);
    }

    public function test_liberer_une_reservation_rend_le_livreur_disponible(): void
    {
        $client = User::factory()->client()->create();
        $driver = Deliverer::factory()->create(['status' => 'reserve', 'reserved_by' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->deleteJson("/api/drivers/{$driver->id}/reserve");

        $response->assertStatus(200);
        $this->assertEquals('disponible', $driver->fresh()->status);
    }

    public function test_un_autre_client_ne_peut_pas_liberer_la_reservation_de_quelqu_un_d_autre(): void
    {
        $proprietaire = User::factory()->client()->create();
        $intrus = User::factory()->client()->create();
        $driver = Deliverer::factory()->create(['status' => 'reserve', 'reserved_by' => $proprietaire->id]);

        $response = $this->actingAs($intrus, 'sanctum')->deleteJson("/api/drivers/{$driver->id}/reserve");

        $response->assertStatus(403);
    }

    public function test_un_livreur_peut_changer_son_propre_statut(): void
    {
        $driverUser = User::factory()->driver()->create();
        Deliverer::factory()->horsLigne()->create(['user_id' => $driverUser->id]);

        $response = $this->actingAs($driverUser, 'sanctum')
            ->patchJson('/api/driver/status', ['status' => 'disponible']);

        $response->assertStatus(200)->assertJsonPath('driver.status', 'disponible');
    }

    public function test_un_livreur_peut_envoyer_sa_position(): void
    {
        $driverUser = User::factory()->driver()->create();
        Deliverer::factory()->create(['user_id' => $driverUser->id]);

        $response = $this->actingAs($driverUser, 'sanctum')
            ->postJson('/api/driver/location', ['latitude' => 9.35, 'longitude' => 2.64]);

        $response->assertStatus(200);
    }
}
