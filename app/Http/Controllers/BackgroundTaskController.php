<?php

namespace App\Http\Controllers;

use App\Exports\ComplianceReportExport;
use App\Jobs\ProcessMasterDataImport;
use App\Models\GeneratedExport;
use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackgroundTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAll = $user?->hasPermission('activity_logs.view') ?? false;

        $imports = ImportBatch::query()
            ->with('user:id,name,employee_code')
            ->when(! $canViewAll, fn ($query) => $query->where('user_id', $user?->id))
            ->latest()
            ->paginate(15, ['*'], 'imports_page')
            ->withQueryString();

        return view('system.background-tasks.index', compact('imports', 'canViewAll'));
    }

    public function cancelExport(Request $request, GeneratedExport $generatedExport)
    {
        $user = $request->user();
        $canViewAll = $user?->hasPermission('activity_logs.view') ?? false;

        if (! $canViewAll && $generatedExport->user_id !== $user?->id) {
            abort(403);
        }

        if (in_array($generatedExport->status, ['completed', 'failed', 'cancelled'], true)) {
            return redirect()->route('background-tasks.index')->with('status', 'Task is already in a terminal state.');
        }

        $generatedExport->update([
            'status' => 'cancelled',
            'error_message' => 'Cancelled by ' . ($user?->name ?? 'user') . ' on ' . now()->format('d M Y H:i'),
            'completed_at' => now(),
        ]);

        return redirect()->route('background-tasks.index')->with('status', 'Export task cancelled successfully.');
    }

    public function cancelImport(Request $request, ImportBatch $importBatch)
    {
        $user = $request->user();
        $canViewAll = $user?->hasPermission('activity_logs.view') ?? false;

        if (! $canViewAll && $importBatch->user_id !== $user?->id) {
            abort(403);
        }

        if (in_array($importBatch->status, ['completed', 'failed', 'cancelled'], true)) {
            return redirect()->route('background-tasks.index')->with('status', 'Task is already in a terminal state.');
        }

        $importBatch->update([
            'status' => 'cancelled',
            'error_message' => 'Cancelled by ' . ($user?->name ?? 'user') . ' on ' . now()->format('d M Y H:i'),
            'completed_at' => now(),
        ]);

        return redirect()->route('background-tasks.index')->with('status', 'Import task cancelled successfully.');
    }

    public function retryImport(Request $request, ImportBatch $importBatch)
    {
        $user = $request->user();
        $canViewAll = $user?->hasPermission('activity_logs.view') ?? false;

        if (! $canViewAll && $importBatch->user_id !== $user?->id) {
            abort(403);
        }

        if ($importBatch->status !== 'failed') {
            return redirect()->route('background-tasks.index')->with('status', 'Only failed imports can be retried.');
        }

        // Reset the import batch to pending state and requeue the job
        $importBatch->update([
            'status' => 'pending',
            'error_message' => null,
            'inserted_rows' => 0,
            'failed_rows' => 0,
            'failure_report' => null,
            'completed_at' => null,
        ]);

        ProcessMasterDataImport::dispatch($importBatch->id);

        return redirect()->route('background-tasks.index')->with('status', 'Import task queued for retry. Check progress here.');
    }

    public function downloadImportFailureReport(Request $request, ImportBatch $importBatch): BinaryFileResponse
    {
        $user = $request->user();
        $canViewAll = $user?->hasPermission('activity_logs.view') ?? false;

        if (! $canViewAll && $importBatch->user_id !== $user?->id) {
            abort(403);
        }

        [$headings, $rows] = $this->buildImportFailureReport($importBatch);

        abort_if($rows === [], 404);

        $fileName = str($importBatch->type)->replace('-', '_')->append('_import_errors_' . $importBatch->id . '.xlsx')->toString();

        return Excel::download(new ComplianceReportExport($headings, $rows), $fileName);
    }

    private function buildImportFailureReport(ImportBatch $importBatch): array
    {
        $sourceRows = $this->readImportSourceRows($importBatch);
        $sourceColumns = $sourceRows !== []
            ? array_keys($sourceRows[0]['values'])
            : $this->failureColumnsFromReport($importBatch->failure_report ?? []);

        $headings = array_merge(['Source Row', 'Error Field', 'Error Message'], $sourceColumns);

        if (! empty($importBatch->failure_report)) {
            $rows = [];

            foreach ($importBatch->failure_report as $failure) {
                $values = is_array($failure['values'] ?? null) ? $failure['values'] : [];
                $rows[] = array_merge([
                    $failure['row'] ?? null,
                    $failure['attribute'] ?? 'row',
                    $this->summarizeImportErrorMessage(implode(' ', $failure['errors'] ?? []), $failure['attribute'] ?? null),
                ], array_map(fn (string $column) => $values[$column] ?? null, $sourceColumns));
            }

            return [$headings, $rows];
        }

        if (($importBatch->error_message ?? null) !== null && $sourceRows !== []) {
            $rows = [];

            foreach ($sourceRows as $sourceRow) {
                $rows[] = array_merge([
                    $sourceRow['row'],
                    'row',
                    $this->summarizeImportErrorMessage($importBatch->error_message),
                ], array_map(fn (string $column) => $sourceRow['values'][$column] ?? null, $sourceColumns));
            }

            return [$headings, $rows];
        }

        if (($importBatch->error_message ?? null) !== null) {
            return [
                ['Source Row', 'Error Field', 'Error Message'],
                [[null, 'row', $this->summarizeImportErrorMessage($importBatch->error_message)]],
            ];
        }

        return [$headings, []];
    }

    private function readImportSourceRows(ImportBatch $importBatch): array
    {
        if (! $importBatch->stored_path || ! Storage::disk($importBatch->disk)->exists($importBatch->stored_path)) {
            return [];
        }

        $sheetRows = IOFactory::load(Storage::disk($importBatch->disk)->path($importBatch->stored_path))
            ->getActiveSheet()
            ->toArray(null, true, true, false);

        if ($sheetRows === []) {
            return [];
        }

        $headingRow = array_shift($sheetRows);
        $columns = [];

        foreach ($headingRow as $heading) {
            $columns[] = trim((string) $heading);
        }

        $rows = [];

        foreach ($sheetRows as $index => $sheetRow) {
            $values = [];

            foreach ($columns as $columnIndex => $column) {
                if ($column === '') {
                    continue;
                }

                $values[$column] = $sheetRow[$columnIndex] ?? null;
            }

            $hasValue = collect($values)->contains(fn ($value) => $value !== null && $value !== '');

            if (! $hasValue) {
                continue;
            }

            $rows[] = [
                'row' => $index + 2,
                'values' => $values,
            ];
        }

        return $rows;
    }

    private function failureColumnsFromReport(array $failureReport): array
    {
        $columns = [];

        foreach ($failureReport as $failure) {
            foreach (array_keys($failure['values'] ?? []) as $column) {
                $columns[$column] = $column;
            }
        }

        return array_values($columns);
    }

    private function summarizeImportErrorMessage(?string $message, ?string $attribute = null): string
    {
        $message = trim((string) $message);

        if ($message === '') {
            return 'Import failed';
        }

        $normalized = strtolower($message);

        if (str_contains($normalized, 'locations.locations_code_unique') || str_contains($normalized, 'duplicate entry') && str_contains($normalized, 'location')) {
            return 'Duplicate location code';
        }

        if (str_contains($normalized, 'clients_code_unique') || str_contains($normalized, 'duplicate entry') && str_contains($normalized, 'client')) {
            return 'Duplicate client code';
        }

        if (str_contains($normalized, 'contracts_contract_no_unique')) {
            return 'Duplicate contract number';
        }

        if (str_contains($normalized, 'duplicate contract number')) {
            return 'Duplicate contract number';
        }

        if (str_contains($normalized, 'service_orders_order_no_unique')) {
            return 'Duplicate sales order number';
        }

        if (str_contains($normalized, 'client code') && str_contains($normalized, 'not found')) {
            return 'Client not found';
        }

        if (str_contains($normalized, 'state code') && str_contains($normalized, 'not found')) {
            return 'State not found';
        }

        if (str_contains($normalized, 'contract number') && str_contains($normalized, 'not found')) {
            return 'Contract not found';
        }

        if (str_contains($normalized, 'team code') && str_contains($normalized, 'not found')) {
            return 'Team not found';
        }

        if (str_contains($normalized, 'operation executive employee code') && str_contains($normalized, 'not found')) {
            return 'Operation executive not found';
        }

        if (str_contains($normalized, 'location code') && str_contains($normalized, 'not found')) {
            return 'Location not found';
        }

        if (str_contains($normalized, 'no operation area')) {
            return 'Operation area missing for state';
        }

        if (str_contains($normalized, 'must be a string')) {
            return $attribute ? ucfirst(str_replace('_', ' ', $attribute)) . ' must be text' : 'Invalid text value';
        }

        if (str_contains($normalized, 'required')) {
            return $attribute ? ucfirst(str_replace('_', ' ', $attribute)) . ' is required' : 'Required field missing';
        }

        if (str_contains($normalized, 'integrity constraint violation') || str_contains($normalized, 'sqlstate')) {
            return 'Duplicate or invalid database value';
        }

        return str($message)->before(' (Connection:')->limit(80)->toString();
    }
}