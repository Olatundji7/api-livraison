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
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['livraison', 'course_personnelle'])->default('livraison');

            $table->enum('status', [
                'en_attente', 'livreur_reserve', 'livreur_accepte', 'en_cours',
                'arrivee_retrait', 'colis_recupere', 'en_livraison', 'livree',
                'annulee', 'refusee', 'echec',
            ])->default('en_attente');

            $table->string('pickup_address')->nullable();
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->string('destination_address');
            $table->decimal('destination_latitude', 10, 7);
            $table->decimal('destination_longitude', 10, 7);
            $table->string('note', 1000)->nullable();

            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->unsignedInteger('gross_delivery_fee')->default(0);
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('service_fee')->default(100);
            $table->unsignedInteger('fedapay_fee')->default(0);
            $table->unsignedInteger('net_service_revenue')->default(0);
            $table->decimal('distance_travelled_km', 10, 2)->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->enum('payment_status', ['non_paye', 'paye', 'echoue'])->default('non_paye');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
