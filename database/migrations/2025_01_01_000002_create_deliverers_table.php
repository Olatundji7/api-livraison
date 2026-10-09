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
            $table->string('photo')->nullable();
            $table->string('telephone')->nullable();
            $table->string('vehicule_type')->nullable();
            $table->string('immatriculation')->nullable();
            $table->enum('status', ['disponible', 'reserve', 'occupe', 'hors_ligne', 'suspendu'])
                  ->default('hors_ligne');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();

            // Réservation atomique (bloc 2)
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reserved_until')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverers');
    }
};
