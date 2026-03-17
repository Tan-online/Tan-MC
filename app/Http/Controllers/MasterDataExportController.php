<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateMasterDataExport;
use App\Models\GeneratedExport;
use App\Services\MasterDataExportService;
use App\Services\MusterComplianceService;
use Illuminate\Http\RedirectResponse;
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
    ): BinaryFileResponse|RedirectResponse|\Illuminate\Http\JsonResponse {
        $validated = validator($request->all(), array_merge(
            $masterDataExportService->validationRules($type),
            [
                'format' => ['nullable', Rule::in(['xlsx', 'csv'])],
                'mode' => ['nullable', Rule::in(['auto', 'sync', 'queue'])],
            ],
        ))->validate();

        $definition = $masterDataExportService->definition($type, $validated, $musterComplianceService);
        $this->authorizePermission($definition['permission']);

        $format = (string) ($validated['format'] ?? 'xlsx');
        $mode = (string) ($validated['mode'] ?? 'auto');
        // Queue if explicitly requested, or if record count > 500 to prevent timeouts
        $shouldQueue = $mode === 'queue' || ($mode === 'auto' && $definition['record_count'] > 500);

        if ($shouldQueue) {
            $generatedExport = GeneratedExport::query()->create([
                'user_id' => $request->user()?->id,
                'category' => 'master-data',
                'type' => $type,
                'format' => $format,
                'status' => 'pending',
                'disk' => 'local',
                'filters' => collect($validated)->except(['mode', 'format'])->all(),
                'record_count' => $definition['record_count'],
            ]);

            GenerateMasterDataExport::dispatch($generatedExport->id);

            $this->logActivity(
                $definition['module'],
                'export_queued',
                ucfirst(str_replace('-', ' ', $type)) . ' export queued for background processing.',
                $generatedExport->id,
                $request->user()
            );

            // Return JSON if AJAX request, otherwise redirect
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'queued',
                    'message' => 'Export queued successfully. Check Background Tasks for progress.',
                    'export_id' => $generatedExport->id,
                ]);
            }

            return redirect()
                ->back()
                ->with('status', 'Export queued successfully. Check Background Tasks for progress.')
                ->with('export_queued', true);
        }

        $this->logActivity($definition['module'], 'export', $definition['description'], null, $request->user());

        return Excel::download(
            $definition['export'],
            $definition['file_name_base'] . '.' . $format,
            $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX,
        );
    }
}