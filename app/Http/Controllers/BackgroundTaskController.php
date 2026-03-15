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
}