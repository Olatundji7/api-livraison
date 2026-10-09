<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DelivererFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'telephone' => '+229' . fake()->numerify('########'),
            'vehicule_type' => 'moto',
            'immatriculation' => strtoupper(fake()->bothify('??-####-??')),
            'status' => 'disponible',
            'latitude' => 9.3390,
            'longitude' => 2.6280,
            'last_location_at' => now(),
        ];
    }

    public function horsLigne(): static { return $this->state(['status' => 'hors_ligne']); }
    public function occupe(): static { return $this->state(['status' => 'occupe']); }
}
