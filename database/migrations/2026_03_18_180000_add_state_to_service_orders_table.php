<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('state_id')
                ->nullable()
                ->after('contract_id')
                ->constrained()
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('state_id', 'service_orders_state_idx');
        });

        DB::table('service_orders')
            ->select(['service_orders.id', 'locations.state_id'])
            ->join('locations', 'locations.id', '=', 'service_orders.location_id')
            ->whereNull('service_orders.state_id')
            ->orderBy('service_orders.id')
            ->lazy()
            ->each(function (object $row): void {
                DB::table('service_orders')
                    ->where('id', $row->id)
                    ->update(['state_id' => $row->state_id]);
            });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('service_orders_state_idx');
            $table->dropConstrainedForeignId('state_id');
        });
    }
};