<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_peut_s_inscrire_et_se_connecter(): void
    {
        $register = $this->postJson('/api/auth/register', [
            'name' => 'Fatouma Alassane',
            'phone' => '+22997001122',
            'password' => 'password123',
        ]);
        $register->assertStatus(201)->assertJsonStructure(['user', 'token']);

        $login = $this->postJson('/api/auth/login', [
            'phone' => '+22997001122',
            'password' => 'password123',
        ]);
        $login->assertStatus(200);
    }

    public function test_impossible_de_creer_un_compte_livreur_via_la_route_publique(): void
    {
        // Le endpoint register ne prend pas de "role" en entrée : tout le monde devient client.
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Karim', 'phone' => '+22997889900', 'password' => 'password123', 'role' => 'driver',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['phone' => '+22997889900', 'role' => 'client']);
    }

    public function test_get_me_renvoie_l_utilisateur_connecte(): void
    {
        $user = User::factory()->client()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/auth/me');

        $response->assertStatus(200)->assertJsonPath('user.id', $user->id);
    }

    public function test_un_compte_suspendu_ne_peut_pas_se_connecter(): void
    {
        User::factory()->create([
            'phone' => '+22997001122', 'password' => bcrypt('password123'), 'status' => 'suspendu',
        ]);

        $response = $this->postJson('/api/auth/login', ['phone' => '+22997001122', 'password' => 'password123']);

        $response->assertStatus(403);
    }
    public function test_un_compte_existant_est_signale_clairement(): void
    {
        User::factory()->client()->create([
            'phone' => '+22997000001',
            'email' => 'deja@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Autre Client',
            'phone' => '+22997000001',
            'email' => 'nouveau@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'ACCOUNT_EXISTS');
    }

}
