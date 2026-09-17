<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => User::factory()->client(),
            'deliverer_id' => null,
            'type' => 'livraison',
            'pickup_lat' => 9.3372,
            'pickup_lng' => 2.6303,
            'pickup_adresse' => 'Marché Arzèkè, Parakou',
            'dest_lat' => 9.3456,
            'dest_lng' => 2.6210,
            'dest_adresse' => 'Quartier Zongo, Parakou',
            'note' => null,
            'statut' => 'en_attente',
            'distance_km' => 3.2,
            'prix_estime' => 1460,
            'prix_final' => null,
            'cree_le' => now(),
        ];
    }

    public function assignee(): static
    {
        return $this->state(fn () => [
            'deliverer_id' => User::factory()->livreur(),
            'statut' => 'traitee',
        ]);
    }
}
