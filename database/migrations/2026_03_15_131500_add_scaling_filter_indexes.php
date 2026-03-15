<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index('industry', 'clients_industry_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index('status', 'contracts_status_idx');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->index('status', 'service_orders_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('service_orders_status_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_status_idx');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_industry_idx');
        });
    }
};