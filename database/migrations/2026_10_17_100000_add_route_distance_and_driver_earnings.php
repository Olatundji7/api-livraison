<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'route_distance_km')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('route_distance_km', 10, 2)->default(0)->after('distance_travelled_km');
            });
        }
        if (! Schema::hasColumn('orders', 'driver_earnings')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('driver_earnings')->default(0)->after('route_distance_km');
            });
        }
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('orders', 'driver_earnings') ? 'driver_earnings' : null,
            Schema::hasColumn('orders', 'route_distance_km') ? 'route_distance_km' : null,
        ]);
        if ($columns) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(array_values($columns)));
        }
    }
};
