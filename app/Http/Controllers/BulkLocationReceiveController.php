<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\MusterExpected;
use App\Services\MusterComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BulkLocationReceiveController extends Controller
{
    public function index(Request $request, MusterComplianceService $musterComplianceService)
    {
        $clientId = $request->integer('client_id');
        $contractId = $request->integer('contract_id');
        $month = max(1, min(12, $request->integer('month') ?: (int) now()->format('n')));
        $year = $request->integer('year') ?: (int) now()->format('Y');
        $status = trim((string) $request->string('status'));

        if ($request->routeIs('workflow.approvals.index') && $status === '') {
            $status = 'Received';
        }

        $clients = Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $contracts = Contract::query()
            ->with('client:id,name')
            ->withCount('locations')
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('contract_no')
            ->get(['id', 'client_id', 'contract_no', 'status']);

        $cycle = null;
        $expectedEntries = null;
        $cycleSummary = [
            'total' => 0,
            'pending' => 0,
            'received' => 0,
            'late' => 0,
            'approved' => 0,
            'returned' => 0,
            'closed' => 0,
        ];

        if ($contractId > 0) {
            $contract = Contract::query()
                ->with(['client:id,name', 'locations:id,name,city'])
                ->findOrFail($contractId);

            $cycle = $musterComplianceService->ensureCycleForContractMonth($contract, $month, $year);

            if ($cycle) {
                $expectedEntries = $musterComplianceService->expectedEntriesForCycle($cycle, [
                    'status' => $status,
                    'per_page' => 25,
                ]);

                $cycleSummary = MusterExpected::query()
                    ->selectRaw('status, COUNT(*) as total')
                    ->where('muster_cycle_id', $cycle->id)
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->pipe(function ($totals) {
                        return [
                            'total' => (int) $totals->sum(),
                            'pending' => (int) ($totals['Pending'] ?? 0),
                            'received' => (int) ($totals['Received'] ?? 0),
                            'late' => (int) ($totals['Late'] ?? 0),
                            'approved' => (int) ($totals['Approved'] ?? 0),
                            'returned' => (int) ($totals['Returned'] ?? 0),
                            'closed' => (int) ($totals['Closed'] ?? 0),
                        ];
                    });
            }
        }

        return view('operations.bulk-receive.index', compact(
            'clients',
            'contracts',
            'clientId',
            'contractId',
            'month',
            'year',
            'status',
            'cycle',
            'expectedEntries',
            'cycleSummary',
        ));
    }

    public function store(Request $request, MusterComplianceService $musterComplianceService)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => ['required', 'exists:clients,id'],
            'contract_id' => ['required', 'exists:contracts,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'status' => ['nullable', Rule::in(['Pending', 'Received', 'Late', 'Approved', 'Returned'])],
            'action' => ['required', Rule::in(['received_hard_copy', 'received_email', 'pending'])],
            'selected_expected_ids' => ['nullable', 'array'],
            'selected_expected_ids.*' => ['integer', 'exists:muster_expected,id'],
            'select_all_locations' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('bulk-receive.index', $request->only(['client_id', 'contract_id', 'month', 'year', 'status']))
                ->withErrors($validator)
                ->withInput();
        }

        $contract = Contract::query()->findOrFail($request->integer('contract_id'));
        $cycle = $musterComplianceService->ensureCycleForContractMonth($contract, $request->integer('month'), $request->integer('year'));

        if (! $cycle) {
            return redirect()
                ->route('bulk-receive.index', $request->only(['client_id', 'contract_id', 'month', 'year']))
                ->with('error', 'No active service order period found for the selected contract and month.');
        }

        $entries = MusterExpected::query()
            ->where('muster_cycle_id', $cycle->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when(! $request->boolean('select_all_locations'), function ($query) use ($request) {
                $selectedIds = array_map('intval', $request->input('selected_expected_ids', []));
                $query->whereIn('id', $selectedIds);
            })
            ->get();

        if ($entries->isEmpty()) {
            return redirect()
                ->route('bulk-receive.index', $request->only(['client_id', 'contract_id', 'month', 'year']))
                ->with('error', 'Select at least one location before applying the bulk action.');
        }

        $musterComplianceService->applyBulkReceive(
            $cycle,
            $entries,
            (string) $request->input('action'),
            $request->input('remarks'),
            $request->user()
        );

        return redirect()
            ->route('bulk-receive.index', $request->only(['client_id', 'contract_id', 'month', 'year']))
            ->with('status', 'Bulk receive action applied successfully.');
    }

    public function review(Request $request, MusterExpected $musterExpected, MusterComplianceService $musterComplianceService)
    {
        $validator = Validator::make($request->all(), [
            'review_status' => ['required', Rule::in(['Approved', 'Returned'])],
            'review_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ((string) $request->input('review_status') === 'Approved') {
            $this->authorizePermission('muster.approve');
        }

        $musterComplianceService->applyReviewDecision(
            $musterExpected,
            (string) $request->input('review_status'),
            $request->input('review_remarks'),
            $request->user()
        );

        return redirect()->back()->with('status', 'Compliance review updated successfully.');
    }

    public function finalClose(Request $request, MusterExpected $musterExpected, MusterComplianceService $musterComplianceService)
    {
        $validator = Validator::make($request->all(), [
            'final_close_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $musterComplianceService->finalClose(
            $musterExpected,
            $request->input('final_close_remarks'),
            $request->user()
        );

        return redirect()->back()->with('status', 'Workflow item closed successfully.');
    }
}
