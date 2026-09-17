<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        Service::insert([
            [
                'nom' => 'Livraison de colis',
                'description' => 'Envoi et réception de colis n\'importe où à Parakou.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nom' => 'Course personnelle',
                'description' => 'Un livreur fait une course pour vous (achats, dépôt de documents...).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
