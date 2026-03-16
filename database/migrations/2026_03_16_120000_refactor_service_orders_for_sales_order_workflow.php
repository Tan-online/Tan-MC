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
            $table->foreignId('operation_executive_id')
                ->nullable()
                ->after('team_id')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedTinyInteger('muster_start_day')
                ->default(1)
                ->after('period_end_date');

            $table->index('operation_executive_id', 'service_orders_operation_executive_idx');
            $table->index('muster_start_day', 'service_orders_muster_start_day_idx');
        });

        Schema::create('service_order_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('location_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['service_order_id', 'location_id']);
            $table->index(['location_id', 'start_date', 'end_date'], 'so_location_date_idx');
        });

        DB::table('service_orders')
            ->select(['id', 'location_id', 'period_start_date', 'period_end_date'])
            ->whereNotNull('location_id')
            ->orderBy('id')
            ->chunk(200, function ($orders): void {
                $now = now();
                $rows = [];

                foreach ($orders as $order) {
                    $rows[] = [
                        'service_order_id' => $order->id,
                        'location_id' => $order->location_id,
                        'start_date' => $order->period_start_date,
                        'end_date' => $order->period_end_date,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('service_order_location')->upsert(
                        $rows,
                        ['service_order_id', 'location_id'],
                        ['start_date', 'end_date', 'updated_at']
                    );
                }
            });

        DB::table('service_orders')
            ->whereNull('muster_start_day')
            ->update(['muster_start_day' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_location');

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('service_orders_operation_executive_idx');
            $table->dropIndex('service_orders_muster_start_day_idx');
            $table->dropConstrainedForeignId('operation_executive_id');
            $table->dropColumn('muster_start_day');
        });
    }
};
