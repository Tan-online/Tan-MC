<?php

namespace App\Http\Controllers;

use App\Services\MasterDataExportService;
use App\Services\MusterComplianceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MasterDataExportController extends Controller
{
    public function export(
        Request $request,
        string $type,
        MusterComplianceService $musterComplianceService,
        MasterDataExportService $masterDataExportService,
    ): BinaryFileResponse {
        $validated = validator($request->all(), array_merge(
            $masterDataExportService->validationRules($type),
            [
                'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
            ],
        ))->validate();

        $format = (string) ($validated['format'] ?? 'xlsx');

        $definition = $masterDataExportService->definition($type, $validated, $musterComplianceService);
        $this->authorizePermission($definition['permission']);

        $this->logActivity($definition['module'], 'export', $definition['description'], null, $request->user());

        return Excel::download(
            $definition['export'],
            $definition['file_name_base'] . '.' . $format,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }
}