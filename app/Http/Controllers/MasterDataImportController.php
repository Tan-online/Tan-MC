<?php

namespace App\Http\Controllers;

use App\Exports\MasterDataTemplateExport;
use App\Jobs\ProcessMasterDataImport;
use App\Models\ImportBatch;
use App\Services\MasterDataImportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MasterDataImportController extends Controller
{
    public function store(Request $request, string $type, MasterDataImportRegistry $registry): RedirectResponse
    {
        $config = $registry->config($type);
        $this->authorizePermission($config['permission']);

        $validator = validator($request->all(), [
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'type' => ['required', Rule::in(array_keys($registry->configs()))],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route($config['route'])
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', $config['modal_id']);
        }

        $detectedType = $this->detectImportType($request, $registry);

        if ($detectedType === null) {
            return redirect()
                ->route($config['route'])
                ->withErrors(['import_file' => 'Uploaded file headings do not match any supported import template.'])
                ->withInput()
                ->with('open_modal', $config['modal_id']);
        }

        if ($detectedType !== $type) {
            $type = $detectedType;
            $config = $registry->config($type);
            $this->authorizePermission($config['permission']);
        }

        $storedPath = $request->file('import_file')->store('imports/' . now()->format('Y/m'), 'local');

        $batch = ImportBatch::query()->create([
            'user_id' => $request->user()?->id,
            'type' => $type,
            'status' => 'pending',
            'disk' => 'local',
            'stored_path' => $storedPath,
            'original_file_name' => $request->file('import_file')->getClientOriginalName(),
        ]);

        ProcessMasterDataImport::dispatch($batch->id);
        $this->logActivity($type, 'import_queued', "Queued {$config['label']} import for background processing.", $batch->id, $request->user());

        return redirect()
            ->route($config['route'])
            ->with('status', sprintf('%s import queued successfully. Check Background Tasks for progress.', $config['label']))
            ->with('import_queued', true);
    }

    private function detectImportType(Request $request, MasterDataImportRegistry $registry): ?string
    {
        $sheetRows = IOFactory::load($request->file('import_file')->getRealPath())
            ->getActiveSheet()
            ->toArray(null, true, true, false);

        $headingRow = $sheetRows[0] ?? [];

        return $registry->detectTypeFromHeadings($headingRow);
    }

    public function template(string $type, MasterDataImportRegistry $registry)
    {
        $config = $registry->config($type);
        $this->authorizePermission($config['permission']);
        $this->logActivity($type, 'template', "Downloaded {$config['label']} import template.", null, request()->user());

        return Excel::download(
            new MasterDataTemplateExport($config['template_rows']),
            $config['file_name']
        );
    }
}
