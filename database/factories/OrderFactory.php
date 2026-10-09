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
            'driver_id' => null,
            'type' => 'livraison',
            'status' => 'en_attente',
            'pickup_address' => 'Marché Arzèkè, Parakou',
            'pickup_latitude' => 9.3372,
            'pickup_longitude' => 2.6303,
            'destination_address' => 'Quartier Zongo, Parakou',
            'destination_latitude' => 9.3456,
            'destination_longitude' => 2.6210,
            'subtotal' => 0,
            'delivery_fee' => 1460,
            'total' => 1460,
            'payment_status' => 'non_paye',
        ];
    }
}
