<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->index(['client_id', 'state_id'], 'locations_client_state_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['client_id', 'location_id'], 'contracts_client_location_idx');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->index(['contract_id', 'location_id'], 'service_orders_contract_location_idx');
        });

        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->index(['client_id', 'location_id', 'contract_id'], 'executive_map_scope_idx');
        });

        Schema::table('muster_cycles', function (Blueprint $table) {
            $table->index(['contract_id', 'service_order_id'], 'muster_cycles_contract_service_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('muster_cycles', function (Blueprint $table) {
            $table->dropIndex('muster_cycles_contract_service_order_idx');
        });

        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->dropIndex('executive_map_scope_idx');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('service_orders_contract_location_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_client_location_idx');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('locations_client_state_idx');
        });
    }
};
