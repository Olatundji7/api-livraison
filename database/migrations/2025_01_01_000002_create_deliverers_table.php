<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('statut_validation', ['en_attente', 'valide', 'refuse'])->default('en_attente');
            $table->boolean('disponible')->default(false);
            $table->decimal('position_lat', 10, 7)->nullable();
            $table->decimal('position_lng', 10, 7)->nullable();
            $table->string('piece_identite_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverers');
    }
};
