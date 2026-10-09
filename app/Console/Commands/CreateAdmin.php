<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';
    protected $description = 'Créer un compte administrateur MA Livraison';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nom complet'));
        $phone = trim((string) $this->ask('Téléphone'));
        $email = trim((string) $this->ask('Email (facultatif)'));
        $password = (string) $this->secret('Mot de passe');

        if ($name === '' || $phone === '' || strlen($password) < 6) {
            $this->error('Nom, téléphone et mot de passe (6 caractères minimum) sont obligatoires.');
            return self::FAILURE;
        }
        if (User::where('phone', $phone)->exists()) {
            $this->error('Ce numéro existe déjà.');
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'password' => Hash::make($password),
            'role' => 'admin',
            'status' => 'actif',
        ]);

        $this->info('Administrateur créé avec succès.');
        return self::SUCCESS;
    }
}
