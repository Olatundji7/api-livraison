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
            'telephone' => '+229' . fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'client',
        ];
    }

    public function client(): static
    {
        return $this->state(['role' => 'client']);
    }

    public function livreur(): static
    {
        return $this->state(['role' => 'livreur']);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }
}
