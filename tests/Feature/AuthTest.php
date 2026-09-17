<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_peut_s_inscrire(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'nom' => 'Fatouma Alassane',
            'telephone' => '+22997001122',
            'email' => 'fatouma@example.com',
            'mot_de_passe' => 'password123',
            'role' => 'client',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.nom', 'Fatouma Alassane')
            ->assertJsonPath('user.role', 'client')
            ->assertJsonStructure(['user' => ['id', 'nom', 'role'], 'token']);

        $this->assertDatabaseHas('users', ['telephone' => '+22997001122', 'role' => 'client']);
    }

    public function test_un_livreur_qui_s_inscrit_cree_automatiquement_un_profil_deliverer(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'nom' => 'Karim Boukari',
            'telephone' => '+22997889900',
            'mot_de_passe' => 'password123',
            'role' => 'livreur',
        ]);

        $response->assertStatus(201);

        $user = User::where('telephone', '+22997889900')->first();
        $this->assertNotNull($user->deliverer);
        $this->assertEquals('en_attente', $user->deliverer->statut_validation);
        $this->assertFalse($user->deliverer->disponible);
    }

    public function test_l_inscription_refuse_un_telephone_deja_utilise(): void
    {
        User::factory()->create(['telephone' => '+22997001122']);

        $response = $this->postJson('/api/v1/auth/register', [
            'nom' => 'Doublon',
            'telephone' => '+22997001122',
            'mot_de_passe' => 'password123',
            'role' => 'client',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('telephone');
    }

    public function test_connexion_reussie_avec_les_bons_identifiants(): void
    {
        User::factory()->create([
            'telephone' => '+22997001122',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'telephone' => '+22997001122',
            'mot_de_passe' => 'password123',
        ]);

        $response->assertStatus(200)->assertJsonStructure(['user', 'token']);
    }

    public function test_connexion_refusee_avec_un_mauvais_mot_de_passe(): void
    {
        User::factory()->create([
            'telephone' => '+22997001122',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'telephone' => '+22997001122',
            'mot_de_passe' => 'mauvais_mdp',
        ]);

        $response->assertStatus(401);
    }

    public function test_un_utilisateur_connecte_peut_se_deconnecter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
    }
}
