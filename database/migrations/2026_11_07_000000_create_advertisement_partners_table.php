<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisement_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('business_category', 150)->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_whatsapp', 40)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::table('advertisements', function (Blueprint $table) {
            $table->foreignId('partner_id')
                ->nullable()
                ->after('id')
                ->constrained('advertisement_partners')
                ->nullOnDelete();
            $table->boolean('is_sponsored')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn('is_sponsored');
        });
        Schema::dropIfExists('advertisement_partners');
    }
};
