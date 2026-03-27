<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_location', function (Blueprint $table) {
            $table->index(
                ['operation_executive_id', 'wage_month', 'start_date', 'end_date'],
                'so_location_exec_wage_active_idx'
            );
        });

        Schema::table('muster_cycles', function (Blueprint $table) {
            $table->index(['year', 'month', 'service_order_id'], 'muster_cycles_period_service_order_idx');
        });

        Schema::table('muster_expected', function (Blueprint $table) {
            $table->index(['location_id', 'muster_cycle_id', 'status'], 'muster_expected_location_cycle_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_location', function (Blueprint $table) {
            $table->dropIndex('so_location_exec_wage_active_idx');
        });

        Schema::table('muster_cycles', function (Blueprint $table) {
            $table->dropIndex('muster_cycles_period_service_order_idx');
        });

        Schema::table('muster_expected', function (Blueprint $table) {
            $table->dropIndex('muster_expected_location_cycle_status_idx');
        });
    }
};
