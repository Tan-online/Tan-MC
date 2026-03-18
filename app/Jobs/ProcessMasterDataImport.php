<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\ActivityLogService;
use App\Services\DashboardStatsService;
use App\Services\MasterDataImportRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessMasterDataImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $importBatchId,
    ) {
    }

    public function handle(
        MasterDataImportRegistry $registry,
        ActivityLogService $activityLogService,
        DashboardStatsService $dashboardStatsService,
    ): void {
        $batch = ImportBatch::query()->findOrFail($this->importBatchId);
        $config = $registry->config($batch->type);

        $batch->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        /** @var \App\Imports\AbstractMasterDataImport $import */
        $import = app($config['import']);

        try {
            Excel::import($import, Storage::disk($batch->disk)->path($batch->stored_path));

            $failedRows = count($import->failures());

            $batch->update([
                'status' => 'completed',
                'inserted_rows' => $import->insertedCount(),
                'failed_rows' => $failedRows,
                'failure_report' => $import->failures(),
                'completed_at' => now(),
            ]);

            $dashboardStatsService->forget();
            $activityLogService->log(
                $batch->type,
                'import',
                "Imported {$import->insertedCount()} {$config['label']} rows via background job.",
                $batch->id,
                $batch->user_id
            );
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            $activityLogService->log(
                $batch->type,
                'import_failed',
                "Failed {$config['label']} import job: {$exception->getMessage()}.",
                $batch->id,
                $batch->user_id
            );

            throw $exception;
        }
    }
}