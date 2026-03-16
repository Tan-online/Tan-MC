<?php

namespace App\Jobs;

use App\Exports\ComplianceReportExport;
use App\Models\GeneratedExport;
use App\Services\ActivityLogService;
use App\Services\ComplianceReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $generatedExportId,
    ) {
    }

    public function handle(ComplianceReportingService $complianceReportingService, ActivityLogService $activityLogService): void
    {
        $exportRecord = GeneratedExport::query()->with('user')->findOrFail($this->generatedExportId);

        $exportRecord->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            abort_unless($exportRecord->user !== null, 404);

            $filters = $exportRecord->filters ?? [];
            $export = $complianceReportingService->reportExportRows($exportRecord->type, $filters, $exportRecord->user);
            $baseName = str($exportRecord->type)->replace('-', '_') . '_' . ($filters['year'] ?? now()->year) . '_' . str_pad((string) ($filters['month'] ?? now()->month), 2, '0', STR_PAD_LEFT);

            if ($exportRecord->format === 'pdf') {
                $path = 'exports/' . now()->format('Y/m') . '/' . $baseName . '-' . $exportRecord->id . '.pdf';
                Storage::disk($exportRecord->disk)->put(
                    $path,
                    Pdf::loadView('reports.pdf', [
                        'title' => $export['title'],
                        'headings' => $export['headings'],
                        'rows' => $export['rows'],
                        'filters' => $filters,
                    ])->output()
                );
            } else {
                $extension = $exportRecord->format === 'csv' ? 'csv' : 'xlsx';
                $path = 'exports/' . now()->format('Y/m') . '/' . $baseName . '-' . $exportRecord->id . '.' . $extension;
                Excel::store(
                    new ComplianceReportExport($export['headings'], $export['rows']),
                    $path,
                    $exportRecord->disk,
                    $exportRecord->format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX,
                );
            }

            $extension = $exportRecord->format === 'excel' ? 'xlsx' : $exportRecord->format;

            $exportRecord->update([
                'status' => 'completed',
                'path' => $path,
                'file_name' => $baseName . '.' . $extension,
                'record_count' => count($export['rows']),
                'completed_at' => now(),
            ]);

            $activityLogService->log(
                'reports',
                'export',
                "Generated {$exportRecord->type} report in background.",
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