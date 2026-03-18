<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_location', function (Blueprint $table) {
            $table->foreignId('operation_executive_id')
                ->nullable()
                ->after('end_date')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedTinyInteger('muster_due_days')
                ->default(0)
                ->after('operation_executive_id');

            $table->index('operation_executive_id', 'so_location_operation_executive_idx');
        });

        DB::table('service_order_location')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $serviceOrder = DB::table('service_orders')
                        ->where('id', $row->service_order_id)
                        ->first(['operation_executive_id', 'muster_due_days']);

                    if (! $serviceOrder) {
                        continue;
                    }

                    DB::table('service_order_location')
                        ->where('id', $row->id)
                        ->update([
                            'operation_executive_id' => $serviceOrder->operation_executive_id,
                            'muster_due_days' => (int) ($serviceOrder->muster_due_days ?? 0),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('service_order_location', function (Blueprint $table) {
            $table->dropIndex('so_location_operation_executive_idx');
            $table->dropConstrainedForeignId('operation_executive_id');
            $table->dropColumn('muster_due_days');
        });
    }
};