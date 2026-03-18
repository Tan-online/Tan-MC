<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('import_batches')
            ->select(['id', 'failure_report'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $batch): void {
                $report = $batch->failure_report;

                if (is_string($report)) {
                    $report = json_decode($report, true);
                }

                if (! is_array($report)) {
                    return;
                }

                DB::table('import_batches')
                    ->where('id', $batch->id)
                    ->update(['failed_rows' => count($report)]);
            });
    }

    public function down(): void
    {
        DB::table('import_batches')
            ->select(['id', 'failure_report'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $batch): void {
                $report = $batch->failure_report;

                if (is_string($report)) {
                    $report = json_decode($report, true);
                }

                if (! is_array($report)) {
                    return;
                }

                $failedRows = collect($report)
                    ->pluck('row')
                    ->filter()
                    ->unique()
                    ->count();

                DB::table('import_batches')
                    ->where('id', $batch->id)
                    ->update(['failed_rows' => $failedRows]);
            });
    }
};