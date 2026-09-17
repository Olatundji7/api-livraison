<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "nom" du contrat = colonne "name" déjà fournie par Laravel
            $table->string('telephone')->unique()->nullable()->after('name');
            $table->enum('role', ['client', 'livreur', 'admin'])->default('client')->after('telephone');
        });

        // email n'est plus obligatoire (le contrat le rend optionnel à l'inscription)
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telephone', 'role']);
        });
    }
};
