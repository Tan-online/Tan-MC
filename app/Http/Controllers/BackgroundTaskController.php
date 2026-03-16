<?php

namespace App\Http\Controllers;

use App\Models\GeneratedExport;
use App\Models\ImportBatch;
use Illuminate\Http\Request;

class BackgroundTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAll = $user?->hasPermission('activity_logs.view') ?? false;

        $exports = GeneratedExport::query()
            ->with('user:id,name,employee_code')
            ->when(! $canViewAll, fn ($query) => $query->where('user_id', $user?->id))
            ->latest()
            ->paginate(15, ['*'], 'exports_page')
            ->withQueryString();

        $imports = ImportBatch::query()
            ->with('user:id,name,employee_code')
            ->when(! $canViewAll, fn ($query) => $query->where('user_id', $user?->id))
            ->latest()
            ->paginate(15, ['*'], 'imports_page')
            ->withQueryString();

        return view('system.background-tasks.index', compact('exports', 'imports', 'canViewAll'));
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
}