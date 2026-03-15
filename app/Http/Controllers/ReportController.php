<?php

namespace App\Http\Controllers;

use App\Exports\ComplianceReportExport;
use App\Jobs\GenerateReportExport;
use App\Models\GeneratedExport;
use App\Services\ComplianceReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request, ComplianceReportingService $complianceReportingService)
    {
        $filters = $this->filters($request);
        $reportType = $request->input('report', 'client-compliance');
        $reportOptions = $complianceReportingService->reportOptions();

        abort_unless(array_key_exists($reportType, $reportOptions), 404);

        $report = $complianceReportingService->report($reportType, $filters);

        return view('reports.index', [
            'report' => $report,
            'reportType' => $reportType,
            'reportOptions' => $reportOptions,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request, string $report, string $format, ComplianceReportingService $complianceReportingService)
    {
        abort_unless(in_array($format, ['excel', 'pdf', 'csv'], true), 404);
        abort_unless(array_key_exists($report, $complianceReportingService->reportOptions()), 404);

        $filters = $this->filters($request);
        $mode = (string) $request->input('mode', 'auto');
        abort_unless(in_array($mode, ['auto', 'sync', 'queue'], true), 422);

        $export = $complianceReportingService->reportExportRows($report, $filters);
        $fileBase = str($report)->replace('-', '_') . '_' . $filters['year'] . '_' . str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT);
        $recordCount = count($export['rows']);
        $shouldQueue = $mode === 'queue' || ($mode === 'auto' && $recordCount > 1000);

        if ($shouldQueue) {
            $generatedExport = GeneratedExport::query()->create([
                'user_id' => $request->user()?->id,
                'category' => 'report',
                'type' => $report,
                'format' => $format,
                'status' => 'pending',
                'disk' => 'local',
                'filters' => $filters,
                'record_count' => $recordCount,
            ]);

            GenerateReportExport::dispatch($generatedExport->id);
            $this->logActivity('reports', 'export_queued', "Queued {$report} report for background generation.", $generatedExport->id, $request->user());

            return redirect()
                ->back()
                ->with('status', 'Report export queued successfully. Track progress from Background Tasks.');
        }

        if (in_array($format, ['excel', 'csv'], true)) {
            $this->logActivity('reports', 'export', 'Exported ' . $report . ' report in ' . strtoupper($format) . ' format.', null, $request->user());

            return Excel::download(
                new ComplianceReportExport($export['headings'], $export['rows']),
                $fileBase . '.' . ($format === 'excel' ? 'xlsx' : 'csv'),
                $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
            );
        }

        $this->logActivity('reports', 'export', "Exported {$report} report in PDF format.", null, $request->user());

        return Pdf::loadView('reports.pdf', [
            'title' => $export['title'],
            'headings' => $export['headings'],
            'rows' => $export['rows'],
            'filters' => $filters,
        ])->download("{$fileBase}.pdf");
    }

    private function filters(Request $request): array
    {
        validator($request->all(), [
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
            'report' => ['nullable', Rule::in(['client-compliance', 'state-compliance', 'executive-performance'])],
        ])->validate();

        return [
            'month' => max(1, min(12, (int) ($request->input('month') ?: now()->month))),
            'year' => (int) ($request->input('year') ?: now()->year),
        ];
    }
}
