<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('deliverer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('livraison'); // livraison | course_personnelle
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('pickup_adresse');
            $table->decimal('dest_lat', 10, 7);
            $table->decimal('dest_lng', 10, 7);
            $table->string('dest_adresse');
            $table->string('note')->nullable();
            $table->enum('statut', ['en_attente', 'traitee', 'en_cours', 'livree', 'annulee'])
                  ->default('en_attente');
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedInteger('prix_estime')->nullable();
            $table->unsignedInteger('prix_final')->nullable();
            $table->timestamp('cree_le')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
