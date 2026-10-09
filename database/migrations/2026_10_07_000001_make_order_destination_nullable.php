<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('destination_address')->nullable()->change();
            $table->decimal('destination_latitude', 10, 7)->nullable()->change();
            $table->decimal('destination_longitude', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('destination_address')->nullable(false)->change();
            $table->decimal('destination_latitude', 10, 7)->nullable(false)->change();
            $table->decimal('destination_longitude', 10, 7)->nullable(false)->change();
        });
    }
};
