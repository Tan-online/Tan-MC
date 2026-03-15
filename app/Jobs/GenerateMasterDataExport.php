<?php

namespace App\Jobs;

use App\Models\GeneratedExport;
use App\Services\ActivityLogService;
use App\Services\MasterDataExportService;
use App\Services\MusterComplianceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Throwable;

class GenerateMasterDataExport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $generatedExportId,
    ) {
    }

    public function handle(
        MasterDataExportService $masterDataExportService,
        MusterComplianceService $musterComplianceService,
        ActivityLogService $activityLogService,
    ): void {
        $exportRecord = GeneratedExport::query()->findOrFail($this->generatedExportId);

        $exportRecord->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $definition = $masterDataExportService->definition($exportRecord->type, $exportRecord->filters ?? [], $musterComplianceService);
            $extension = $exportRecord->format === 'csv' ? 'csv' : 'xlsx';
            $path = 'exports/' . now()->format('Y/m') . '/' . $definition['file_name_base'] . '-' . $exportRecord->id . '.' . $extension;

            Excel::store(
                $definition['export'],
                $path,
                $exportRecord->disk,
                $exportRecord->format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
            );

            $exportRecord->update([
                'status' => 'completed',
                'path' => $path,
                'file_name' => $definition['file_name_base'] . '.' . $extension,
                'record_count' => $definition['record_count'],
                'completed_at' => now(),
            ]);

            $activityLogService->log(
                $definition['module'],
                'export',
                ucfirst(str_replace('-', ' ', $exportRecord->type)) . ' export generated in background.',
                $exportRecord->id,
                $exportRecord->user_id
            );
        } catch (Throwable $exception) {
            $exportRecord->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            throw $exception;
        }
    }
}