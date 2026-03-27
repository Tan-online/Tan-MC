<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_order_location', function (Blueprint $table) {
            $table->date('wage_month')->nullable()->after('muster_due_days');
            $table->foreignId('dispatched_by_user_id')
                ->nullable()
                ->after('wage_month')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('dispatched_at')->nullable()->after('dispatched_by_user_id');

            $table->index('wage_month', 'so_location_wage_month_idx');
            $table->index('location_id', 'so_location_location_idx');
            $table->index(['wage_month', 'dispatched_at'], 'so_location_wage_dispatch_idx');
        });

        DB::table('service_order_location')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $serviceOrder = DB::table('service_orders')
                        ->where('id', $row->service_order_id)
                        ->first(['period_start_date', 'requested_date']);

                    if (! $serviceOrder) {
                        continue;
                    }

                    $legacyDispatchEntry = DB::table('dispatch_entries')
                        ->where('service_order_id', $row->service_order_id)
                        ->first(['dispatched_by_user_id', 'dispatched_at']);

                    $wageMonthSource = $serviceOrder->period_start_date ?: $serviceOrder->requested_date;
                    $wageMonth = $wageMonthSource
                        ? Carbon::parse($wageMonthSource)->startOfMonth()->toDateString()
                        : null;

                    DB::table('service_order_location')
                        ->where('id', $row->id)
                        ->update([
                            'wage_month' => $wageMonth,
                            'dispatched_by_user_id' => $legacyDispatchEntry?->dispatched_by_user_id,
                            'dispatched_at' => $legacyDispatchEntry?->dispatched_at,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('service_order_location', function (Blueprint $table) {
            $table->dropIndex('so_location_wage_dispatch_idx');
            $table->dropIndex('so_location_location_idx');
            $table->dropIndex('so_location_wage_month_idx');
            $table->dropConstrainedForeignId('dispatched_by_user_id');
            $table->dropColumn(['wage_month', 'dispatched_at']);
        });
    }
};
