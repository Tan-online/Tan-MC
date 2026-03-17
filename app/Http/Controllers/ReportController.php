<?php

namespace App\Http\Controllers;

use App\Exports\ComplianceReportExport;
use App\Jobs\GenerateReportExport;
use App\Models\GeneratedExport;
use App\Services\ComplianceReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request, ComplianceReportingService $complianceReportingService)
    {
        $filters = $this->filters($request);
        $reportOptions = $complianceReportingService->reportOptions($request->user());
        $reportType = $request->input('report', (string) $reportOptions->keys()->first());

        abort_unless($reportOptions->has($reportType), 404);

        $report = $complianceReportingService->report($reportType, $filters, $request->user());

        return view('reports.index', [
            'report' => $report,
            'reportType' => $reportType,
            'reportOptions' => $reportOptions,
            'columns' => $complianceReportingService->reportColumns($reportType),
            'filters' => $filters,
            'filterOptions' => $complianceReportingService->filterOptions($request->user(), $filters),
        ]);
    }

    public function export(Request $request, string $report, string $format, ComplianceReportingService $complianceReportingService)
    {
        abort_unless(in_array($format, ['excel', 'pdf', 'csv'], true), 404);
        abort_unless($complianceReportingService->reportOptions($request->user())->has($report), 404);

        $filters = $this->filters($request);
        $mode = (string) $request->input('mode', 'auto');
        abort_unless(in_array($mode, ['auto', 'sync', 'queue'], true), 422);

        $export = $complianceReportingService->reportExportRows($report, $filters, $request->user());
        $fileBase = str($report)->replace('-', '_') . '_' . $filters['year'] . '_' . str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT);
        $recordCount = count($export['rows']);
        // Queue if explicitly requested, or if record count > 200 to prevent timeouts
        $shouldQueue = $mode === 'queue' || ($mode === 'auto' && $recordCount > 200);

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
                ->with('status', 'Report export queued successfully. Check Background Tasks for progress.')
                ->with('export_queued', true);
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
            'report' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'status' => ['nullable', 'string', 'max:20'],
        ])->validate();

        return [
            'month' => max(1, min(12, (int) ($request->input('month') ?: now()->month))),
            'year' => (int) ($request->input('year') ?: now()->year),
            'search' => trim((string) $request->input('search')),
            'client_id' => $request->integer('client_id'),
            'contract_id' => $request->integer('contract_id'),
            'status' => trim((string) $request->input('status')),
        ];
    }
}
