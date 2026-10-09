<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_transactions', 'fee_amount')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->unsignedInteger('fee_amount')->default(0)->after('amount');
                $table->unsignedInteger('merchant_net_amount')->default(0)->after('fee_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_transactions', 'fee_amount')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->dropColumn(['fee_amount', 'merchant_net_amount']);
            });
        }
    }
};
