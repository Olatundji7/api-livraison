<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DelivererFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->livreur(),
            'statut_validation' => 'valide',
            'disponible' => true,
            'position_lat' => 9.3390,
            'position_lng' => 2.6280,
        ];
    }

    public function enAttente(): static
    {
        return $this->state(['statut_validation' => 'en_attente']);
    }

    public function indisponible(): static
    {
        return $this->state(['disponible' => false]);
    }
}
