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
            $table->date('period_start_date')->nullable()->after('scheduled_date');
            $table->date('period_end_date')->nullable()->after('period_start_date');
            $table->string('muster_cycle_type', 20)->default('1-last')->after('period_end_date');
            $table->unsignedTinyInteger('muster_due_days')->default(0)->after('muster_cycle_type');
            $table->boolean('auto_generate_muster')->default(true)->after('muster_due_days');

            $table->index(['contract_id', 'auto_generate_muster']);
            $table->index(['period_start_date', 'period_end_date']);
        });

        DB::table('service_orders')
            ->orderBy('id')
            ->get(['id', 'requested_date', 'scheduled_date'])
            ->each(function (object $serviceOrder): void {
                DB::table('service_orders')
                    ->where('id', $serviceOrder->id)
                    ->update([
                        'period_start_date' => $serviceOrder->requested_date,
                        'period_end_date' => $serviceOrder->scheduled_date ?: $serviceOrder->requested_date,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex(['contract_id', 'auto_generate_muster']);
            $table->dropIndex(['period_start_date', 'period_end_date']);
            $table->dropColumn([
                'period_start_date',
                'period_end_date',
                'muster_cycle_type',
                'muster_due_days',
                'auto_generate_muster',
            ]);
        });
    }
};
