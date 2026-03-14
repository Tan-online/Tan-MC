<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index('is_active', 'clients_is_active_idx');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->index(['client_id', 'is_active'], 'locations_client_active_idx');
            $table->index(['state_id', 'is_active'], 'locations_state_active_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['client_id', 'status', 'start_date'], 'contracts_client_status_start_idx');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->index(['status', 'requested_date'], 'service_orders_status_requested_idx');
            $table->index(['team_id', 'status'], 'service_orders_team_status_idx');
        });

        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->index(['executive_user_id', 'is_active'], 'exec_mappings_user_active_idx');
            $table->index(['operation_area_id', 'is_active'], 'exec_mappings_area_active_idx');
        });

        Schema::table('muster_expected', function (Blueprint $table) {
            $table->index(['muster_cycle_id', 'status'], 'muster_expected_cycle_status_idx');
            $table->index(['executive_mapping_id', 'status'], 'muster_expected_exec_status_idx');
            $table->index('updated_at', 'muster_expected_updated_idx');
        });

        Schema::table('muster_received', function (Blueprint $table) {
            $table->index(['action_by_user_id', 'status'], 'muster_received_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_is_active_idx');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('locations_client_active_idx');
            $table->dropIndex('locations_state_active_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_client_status_start_idx');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('service_orders_status_requested_idx');
            $table->dropIndex('service_orders_team_status_idx');
        });

        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->dropIndex('exec_mappings_user_active_idx');
            $table->dropIndex('exec_mappings_area_active_idx');
        });

        Schema::table('muster_expected', function (Blueprint $table) {
            $table->dropIndex('muster_expected_cycle_status_idx');
            $table->dropIndex('muster_expected_exec_status_idx');
            $table->dropIndex('muster_expected_updated_idx');
        });

        Schema::table('muster_received', function (Blueprint $table) {
            $table->dropIndex('muster_received_user_status_idx');
        });
    }
};
