<?php

namespace Database\Seeders;

use App\Models\Deliverer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ServiceSeeder::class);

        // Un admin de démo
        User::create([
            'name' => 'Admin MA Livraison',
            'telephone' => '+22900000000',
            'email' => 'admin@ma-livraison.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Un client de démo
        $client = User::create([
            'name' => 'Fatouma Alassane',
            'telephone' => '+22997001122',
            'email' => 'fatouma@example.com',
            'password' => Hash::make('password'),
            'role' => 'client',
        ]);

        // Un livreur de démo, déjà validé et disponible, positionné à Parakou
        $delivererUser = User::create([
            'name' => 'Karim Boukari',
            'telephone' => '+22997889900',
            'email' => 'karim@example.com',
            'password' => Hash::make('password'),
            'role' => 'livreur',
        ]);

        Deliverer::create([
            'user_id' => $delivererUser->id,
            'statut_validation' => 'valide',
            'disponible' => true,
            'position_lat' => 9.3390,
            'position_lng' => 2.6280,
        ]);
    }
}
