<?php

namespace App\Http\Controllers;

use App\Exports\ComplianceReportExport;
use App\Services\ComplianceReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        abort_unless(in_array($format, ['excel', 'pdf'], true), 404);
        abort_unless(array_key_exists($report, $complianceReportingService->reportOptions()), 404);

        $filters = $this->filters($request);
        $export = $complianceReportingService->reportExportRows($report, $filters);
        $fileBase = str($report)->replace('-', '_') . '_' . $filters['year'] . '_' . str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT);

        if ($format === 'excel') {
            return Excel::download(
                new ComplianceReportExport($export['headings'], $export['rows']),
                "{$fileBase}.xlsx"
            );
        }

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
