<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+229' . fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'client',
            'status' => 'actif',
        ];
    }

    public function client(): static { return $this->state(['role' => 'client']); }
    public function driver(): static { return $this->state(['role' => 'driver']); }
    public function admin(): static { return $this->state(['role' => 'admin']); }
    public function suspendu(): static { return $this->state(['status' => 'suspendu']); }
}
