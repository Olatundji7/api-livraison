<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'service_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('service_fee')->default(100)->after('delivery_fee');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'service_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('service_fee');
            });
        }
    }
};
