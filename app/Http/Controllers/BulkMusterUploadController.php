<?php

namespace App\Http\Controllers;

use App\Services\OperationsWorkspaceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BulkMusterUploadController extends Controller
{
    public function index(Request $request, OperationsWorkspaceService $operationsWorkspaceService)
    {
        $user = $request->user();

        $selectedWageMonth = $operationsWorkspaceService->resolveSelectedMonth($request->query('wage_month'));
        $wageMonthOptions = $operationsWorkspaceService->wageMonthOptions();

        $search = trim((string) $request->query('search', ''));

        $filters = [];

        $query = $operationsWorkspaceService->compactLocationRowsQuery($user, $selectedWageMonth, $filters);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('cl.name', 'like', "%{$search}%")
                  ->orWhere('so.order_no', 'like', "%{$search}%")
                  ->orWhere('so.so_number', 'like', "%{$search}%");
            });
        }

        // Order: already uploaded (submitted/approved) on top, then by location name
        $query->reorder()->orderByRaw("CASE WHEN COALESCE(slms.status, 'pending') IN ('submitted','approved') THEN 0 ELSE 1 END")
            ->orderBy('l.name');

        $rows = $query->paginate(20)->withQueryString();

        return view('operations.bulk-upload.index', compact('rows', 'wageMonthOptions', 'selectedWageMonth', 'search'));
    }

    public function store(Request $request, OperationsWorkspaceService $operationsWorkspaceService)
    {
        $validator = Validator::make($request->all(), [
            'wage_month' => 'required|string',
            'selected_pairs' => 'required|array|min:1',
            'selected_pairs.*' => ['required', 'regex:/^\d+:\d+$/'],
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,zip|max:10240',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = $request->user();
        $wageMonth = Carbon::createFromFormat('Y-m', $request->input('wage_month'));
        $pairs = $request->input('selected_pairs', []);

        // Re-validate selected pairs belong to user's visible scope for this wage month
        $baseQuery = $operationsWorkspaceService->compactLocationRowsQuery($user, $wageMonth, []);
        $baseQuery->reorder();

        $baseQuery->where(function ($q) use ($pairs) {
            foreach ($pairs as $pair) {
                [$soId, $locationId] = explode(':', $pair);
                $q->orWhere(function ($sub) use ($soId, $locationId) {
                    $sub->where('sol.service_order_id', (int) $soId)
                        ->where('sol.location_id', (int) $locationId);
                });
            }
        });

        $allowedRows = $baseQuery->get();

        if ($allowedRows->isEmpty()) {
            return back()->with('error', 'No valid locations selected or you do not have permission for selected items.');
        }

        $file = $request->file('file');

        // Store a single file for the batch in private storage with a safe name
        $originalName = $file->getClientOriginalName();
        $timestamp = time();
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $filename = $user->id . '_' . $wageMonth->format('Y-m') . '_bulk_' . $timestamp . '_' . $safeName;

        $path = $file->storeAs("location-uploads/{$wageMonth->format('Y-m')}", $filename, 'private');

        foreach ($allowedRows as $row) {
            $operationsWorkspaceService->submitSoLocationStatus(
                (int) $row->service_order_id,
                (int) $row->location_id,
                $wageMonth,
                'submitted',
                $path,
                null,
                $user->id
            );
        }

        return redirect()->route('bulk-muster.index', ['wage_month' => $wageMonth->format('Y-m')])->with('success', 'File uploaded and records updated for selected locations.');
    }
}
