<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'gross_delivery_fee' => ['type' => 'unsignedInteger', 'default' => 0, 'after' => 'delivery_fee'],
            'discount_amount' => ['type' => 'unsignedInteger', 'default' => 0, 'after' => 'gross_delivery_fee'],
            'service_fee' => ['type' => 'unsignedInteger', 'default' => 100, 'after' => 'discount_amount'],
            'fedapay_fee' => ['type' => 'unsignedInteger', 'default' => 0, 'after' => 'service_fee'],
            'net_service_revenue' => ['type' => 'unsignedInteger', 'default' => 0, 'after' => 'fedapay_fee'],
            'distance_travelled_km' => ['type' => 'decimal', 'default' => 0, 'precision' => 10, 'scale' => 2, 'after' => 'net_service_revenue'],
        ];

        foreach ($columns as $name => $definition) {
            if (Schema::hasColumn('orders', $name)) {
                continue;
            }
            Schema::table('orders', function (Blueprint $table) use ($name, $definition) {
                if ($definition['type'] === 'decimal') {
                    $table->decimal($name, $definition['precision'], $definition['scale'])->default($definition['default'])->after($definition['after']);
                } else {
                    $table->unsignedInteger($name)->default($definition['default'])->after($definition['after']);
                }
            });
        }
    }

    public function down(): void
    {
        $drop = array_filter([
            Schema::hasColumn('orders', 'distance_travelled_km') ? 'distance_travelled_km' : null,
            Schema::hasColumn('orders', 'net_service_revenue') ? 'net_service_revenue' : null,
            Schema::hasColumn('orders', 'fedapay_fee') ? 'fedapay_fee' : null,
            Schema::hasColumn('orders', 'service_fee') ? 'service_fee' : null,
            Schema::hasColumn('orders', 'discount_amount') ? 'discount_amount' : null,
            Schema::hasColumn('orders', 'gross_delivery_fee') ? 'gross_delivery_fee' : null,
        ]);
        if ($drop) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(array_values($drop)));
        }
    }
};
