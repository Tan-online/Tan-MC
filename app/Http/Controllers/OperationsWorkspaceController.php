<?php

namespace App\Http\Controllers;

use App\Services\OperationsWorkspaceService;
use App\Exports\WorkspaceLocationsExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OperationsWorkspaceController extends Controller
{
    public function teams(Request $request, OperationsWorkspaceService $operationsWorkspaceService)
    {
        abort_unless($this->accessControl()->isOperationsScoped($request->user()), 403);

        $activeWageMonth = $operationsWorkspaceService->activeWageMonth();
        $selectedTeamId = max(0, (int) $request->query('team_id', 0));
        $teams = $operationsWorkspaceService->visibleTeamsQuery($request->user())
            ->paginate(10)
            ->withQueryString();
        $selectedTeam = $selectedTeamId > 0
            ? $operationsWorkspaceService->visibleTeamsQuery($request->user())->whereKey($selectedTeamId)->first()
            : $operationsWorkspaceService->primaryTeam($request->user());
        $selectedTeamMetrics = $selectedTeam
            ? $operationsWorkspaceService->teamWorkspaceMetrics($selectedTeam, $activeWageMonth)
            : null;

        return view('operations.workspace.teams', [
            'teams' => $teams,
            'selectedTeam' => $selectedTeam,
            'selectedTeamMetrics' => $selectedTeamMetrics,
            'activeWageMonthLabel' => $activeWageMonth->format('F Y'),
        ]);
    }

    public function locations(Request $request, OperationsWorkspaceService $operationsWorkspaceService)
    {
        abort_unless($this->accessControl()->isOperationsScoped($request->user()), 403);

        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'executive_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,submit,return,received'],
            'wage_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $activeWageMonth = $operationsWorkspaceService->activeWageMonth();
        $selectedWageMonth = $validated['wage_month'] ?? null;
        
        // If wage month is provided, parse it; otherwise use the active wage month
        if ($selectedWageMonth) {
            $selectedWageMonth = \Carbon\Carbon::createFromFormat('Y-m', $selectedWageMonth)->startOfMonth();
        } else {
            $selectedWageMonth = $activeWageMonth;
        }

        $filters = [
            'client_id' => (int) ($validated['client_id'] ?? 0),
            'location_id' => (int) ($validated['location_id'] ?? 0),
            'executive_id' => (int) ($validated['executive_id'] ?? 0),
            'status' => $validated['status'] ?? '',
        ];

        $locationRows = $operationsWorkspaceService->compactLocationRowsQuery($request->user(), $selectedWageMonth, $filters)
            ->paginate(25)
            ->withQueryString();
        
        $clientOptions = $operationsWorkspaceService->clientOptionsQuery($request->user(), $selectedWageMonth)->get();
        $locationOptions = $operationsWorkspaceService->locationOptionsQuery($request->user(), $selectedWageMonth)->get();
        $executiveOptions = $operationsWorkspaceService->executiveOptionsQuery($request->user(), $selectedWageMonth)->get();
        
        $wageMonthOptions = $operationsWorkspaceService->wageMonthOptions($activeWageMonth);

        // Get action availabilities for the current user
        $actionAvailabilities = $operationsWorkspaceService->getActionAvailabilities($request->user());
        
        // Prepare timezone format for display
        $userTimezone = config('app.timezone', 'Asia/Kolkata');

        return view('operations.workspace.locations', [
            'locationRows' => $locationRows,
            'clientOptions' => $clientOptions,
            'locationOptions' => $locationOptions,
            'executiveOptions' => $executiveOptions,
            'selectedClientId' => $filters['client_id'],
            'selectedLocationId' => $filters['location_id'],
            'selectedExecutiveId' => $filters['executive_id'],
            'selectedStatus' => $filters['status'],
            'selectedWageMonth' => $selectedWageMonth->format('Y-m'),
            'wageMonthOptions' => $wageMonthOptions,
            'activeWageMonth' => $activeWageMonth,
            'actionAvailabilities' => $actionAvailabilities,
            'userTimezone' => $userTimezone,
        ]);
    }

    public function exportLocations(Request $request, OperationsWorkspaceService $operationsWorkspaceService)
    {
        abort_unless($this->accessControl()->isOperationsScoped($request->user()), 403);

        $validated = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'executive_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,submit,return,received'],
            'wage_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $activeWageMonth = $operationsWorkspaceService->activeWageMonth();
        $selectedWageMonth = $validated['wage_month'] ?? null;
        
        if ($selectedWageMonth) {
            $selectedWageMonth = \Carbon\Carbon::createFromFormat('Y-m', $selectedWageMonth)->startOfMonth();
        } else {
            $selectedWageMonth = $activeWageMonth;
        }

        $filters = [
            'client_id' => (int) ($validated['client_id'] ?? 0),
            'location_id' => (int) ($validated['location_id'] ?? 0),
            'executive_id' => (int) ($validated['executive_id'] ?? 0),
            'status' => $validated['status'] ?? '',
        ];

        $locationRows = $operationsWorkspaceService->compactLocationRowsQuery($request->user(), $selectedWageMonth, $filters)
            ->get();

        $fileName = 'workspace-locations-' . $selectedWageMonth->format('Y-m') . '-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new WorkspaceLocationsExport($locationRows, $selectedWageMonth), $fileName);
    }

    public function submitLocation(Request $request, $locationId, OperationsWorkspaceService $operationsWorkspaceService)
    {
        abort_unless($this->accessControl()->isOperationsScoped($request->user()), 403);

        $validated = $request->validate([
            'wage_month' => ['required', 'date_format:Y-m'],
            'type' => ['required', 'in:hard_copy,email,courier,soft_copy_upload'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,zip'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        // File is required unless type is hard_copy
        if ($validated['type'] !== 'hard_copy' && !$request->hasFile('file')) {
            return back()->withErrors(['file' => 'File upload is required for this submission type.']);
        }

        $serviceOrderLocation = \App\Models\ServiceOrderLocation::findOrFail($locationId);
        $wageMonth = \Carbon\Carbon::createFromFormat('Y-m', $validated['wage_month']);

        // Store file if provided
        $filePath = null;
        if ($request->hasFile('file')) {
            try {
                $filePath = $operationsWorkspaceService->validateAndStoreFile(
                    $request->file('file'),
                    $serviceOrderLocation->id,
                    $request->user()->id,
                    $wageMonth
                );
            } catch (\Exception $e) {
                return back()->withErrors(['file' => $e->getMessage()]);
            }
        }

        // Submit the SO-Location monthly status with submission_type
        // Uses updateOrCreate to handle both new submissions and resubmissions (rejected/returned)
        \App\Models\SoLocationMonthlyStatus::updateOrCreate(
            [
                'service_order_id' => $serviceOrderLocation->service_order_id,
                'location_id' => $serviceOrderLocation->location_id,
                'wage_month' => $wageMonth->format('Y-m'),
            ],
            [
                'status' => 'submitted',
                'submission_type' => $validated['type'],
                'file_path' => $filePath,
                'remarks' => $validated['remarks'] ?? null,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
            ]
        );

        // Record in status history
        $operationsWorkspaceService->recordStatusHistory(
            $serviceOrderLocation->service_order_id,
            $serviceOrderLocation->location_id,
            $wageMonth,
            'submitted',
            $validated['remarks'] ?? 'Submitted via ' . ucfirst(str_replace('_', ' ', $validated['type'])),
            $request->user()->id
        );

        // Update the service_order_location record for backwards compatibility
        $serviceOrderLocation->update([
            'status' => 'submit',
            'type' => $validated['type'],
            'remarks' => $validated['remarks'],
            'action_date' => now('Asia/Kolkata'),
            'action_by_id' => $request->user()->id,
        ]);

        // Log status change in legacy history
        $historyRemark = $validated['remarks'] ?? 'Submitted via ' . ucfirst(str_replace('_', ' ', $validated['type']));
        if ($filePath) {
            $historyRemark .= ' | File: ' . $request->file('file')->getClientOriginalName();
        }

        \App\Models\ServiceOrderLocationStatusHistory::create([
            'service_order_location_id' => $serviceOrderLocation->id,
            'status' => 'submit',
            'remarks' => $historyRemark,
            'changed_by_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Location has been submitted successfully for ' . $wageMonth->format('M Y') . '.');
    }

    public function rejectLocation(Request $request, $locationId, OperationsWorkspaceService $operationsWorkspaceService)
    {
        // Only Admin, Super Admin, Manager, or HOD can reject (hard reject)
        abort_unless(
            $this->accessControl()->hasRole($request->user(), ['admin', 'super_admin', 'manager', 'hod']),
            403
        );

        $validated = $request->validate([
            'wage_month' => ['required', 'date_format:Y-m'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $serviceOrderLocation = \App\Models\ServiceOrderLocation::findOrFail($locationId);
        $wageMonth = \Carbon\Carbon::createFromFormat('Y-m', $validated['wage_month']);

        // Reviewer hard rejects the SO-Location status (rejected state)
        $operationsWorkspaceService->rejectSoLocationStatus(
            $serviceOrderLocation->service_order_id,
            $serviceOrderLocation->location_id,
            $wageMonth,
            $request->user()->id,
            $validated['remarks']
        );

        // Update legacy record for backwards compatibility
        $serviceOrderLocation->update([
            'status' => 'return',
            'remarks' => $validated['remarks'],
            'action_date' => now('Asia/Kolkata'),
            'action_by_id' => $request->user()->id,
        ]);

        // Log in legacy history
        \App\Models\ServiceOrderLocationStatusHistory::create([
            'service_order_location_id' => $serviceOrderLocation->id,
            'status' => 'return',
            'remarks' => 'Rejected: ' . $validated['remarks'],
            'changed_by_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Location submission has been rejected. User must resubmit for ' . $wageMonth->format('M Y') . '.');
    }

    /**
     * Reviewer approves a submitted location status
     * Transitions: submitted -> approved
     */
    public function approveLocation(Request $request, $locationId, OperationsWorkspaceService $operationsWorkspaceService)
    {
        // Only Admin, Super Admin, Manager, or HOD can approve
        abort_unless(
            $this->accessControl()->hasRole($request->user(), ['admin', 'super_admin', 'manager', 'hod']),
            403
        );

        $validated = $request->validate([
            'wage_month' => ['required', 'date_format:Y-m'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $serviceOrderLocation = \App\Models\ServiceOrderLocation::findOrFail($locationId);
        $wageMonth = \Carbon\Carbon::createFromFormat('Y-m', $validated['wage_month']);

        // Reviewer approves the SO-Location status
        $operationsWorkspaceService->approveSoLocationStatus(
            $serviceOrderLocation->service_order_id,
            $serviceOrderLocation->location_id,
            $wageMonth,
            $request->user()->id,
            $validated['remarks'] ?? null
        );

        // Update legacy record for backwards compatibility
        $serviceOrderLocation->update([
            'status' => 'received',
            'remarks' => $validated['remarks'] ?? 'Approved',
            'action_date' => now('Asia/Kolkata'),
            'action_by_id' => $request->user()->id,
        ]);

        // Log in legacy history
        \App\Models\ServiceOrderLocationStatusHistory::create([
            'service_order_location_id' => $serviceOrderLocation->id,
            'status' => 'received',
            'remarks' => 'Approved' . ($validated['remarks'] ? ': ' . $validated['remarks'] : ''),
            'changed_by_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Location submission has been approved for ' . $wageMonth->format('M Y') . '.');
    }

    /**
     * Reviewer returns a submitted location status for correction
     * Transitions: submitted -> returned
     */
    public function returnLocation(Request $request, $locationId, OperationsWorkspaceService $operationsWorkspaceService)
    {
        // Only Admin, Super Admin, Manager, or HOD can return for correction
        abort_unless(
            $this->accessControl()->hasRole($request->user(), ['admin', 'super_admin', 'manager', 'hod']),
            403
        );

        $validated = $request->validate([
            'wage_month' => ['required', 'date_format:Y-m'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $serviceOrderLocation = \App\Models\ServiceOrderLocation::findOrFail($locationId);
        $wageMonth = \Carbon\Carbon::createFromFormat('Y-m', $validated['wage_month']);

        // Reviewer returns the SO-Location status for correction (soft reject)
        $operationsWorkspaceService->returnSoLocationStatus(
            $serviceOrderLocation->service_order_id,
            $serviceOrderLocation->location_id,
            $wageMonth,
            $request->user()->id,
            $validated['remarks']
        );

        // Update legacy record for backwards compatibility
        $serviceOrderLocation->update([
            'status' => 'return',
            'remarks' => $validated['remarks'],
            'action_date' => now('Asia/Kolkata'),
            'action_by_id' => $request->user()->id,
        ]);

        // Log in legacy history
        \App\Models\ServiceOrderLocationStatusHistory::create([
            'service_order_location_id' => $serviceOrderLocation->id,
            'status' => 'return',
            'remarks' => 'Returned for correction: ' . $validated['remarks'],
            'changed_by_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Location submission has been returned for correction. User can resubmit for ' . $wageMonth->format('M Y') . '.');
    }


    public function uploadLocationFile(Request $request, $locationId)
    {
        abort_unless($this->accessControl()->isOperationsScoped($request->user()), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,zip'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $serviceOrderLocation = \App\Models\ServiceOrderLocation::findOrFail($locationId);

        // Only allow file upload if status is 'submit' and type is not 'hard_copy'
        abort_if($serviceOrderLocation->status !== 'submit', 403, 'Only submitted locations can receive file uploads.');
        abort_if($serviceOrderLocation->type === 'hard_copy', 403, 'Hard copy submissions cannot have files uploaded.');

        // Store the file
        $filePath = $request->file('file')->store('workspace-location-uploads/' . $serviceOrderLocation->id, 'private');

        // Mark as received
        $serviceOrderLocation->update([
            'status' => 'received',
            'remarks' => $validated['remarks'],
            'action_date' => now(),
            'action_by_id' => $request->user()->id,
        ]);

        // Log status change in history
        \App\Models\ServiceOrderLocationStatusHistory::create([
            'service_order_location_id' => $serviceOrderLocation->id,
            'status' => 'received',
            'remarks' => 'File uploaded: ' . $request->file('file')->getClientOriginalName() . '. ' . ($validated['remarks'] ?? ''),
            'changed_by_id' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'File has been uploaded and location marked as received.');
    }

    public function downloadFile($locationId)
    {
        abort_unless($this->accessControl()->isOperationsScoped(auth()->user()), 403);

        $serviceOrderLocation = \App\Models\ServiceOrderLocation::findOrFail($locationId);

        // Get the SO-Location monthly status record
        $soLocationMonthlyStatus = \App\Models\SoLocationMonthlyStatus::where([
            ['service_order_id', '=', $serviceOrderLocation->service_order_id],
            ['location_id', '=', $serviceOrderLocation->location_id],
        ])->latest('wage_month')->first();

        abort_if(!$soLocationMonthlyStatus || !$soLocationMonthlyStatus->file_path, 404, 'File not found.');

        // Check if file exists in storage
        $storageDisk = \Illuminate\Support\Facades\Storage::disk('private');
        abort_if(!$storageDisk->exists($soLocationMonthlyStatus->file_path), 404, 'File not found in storage.');

        // Download the file
        return $storageDisk->download($soLocationMonthlyStatus->file_path);
    }
}
