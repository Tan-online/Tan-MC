<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\DispatchEntry;
use App\Models\Location;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceOrderController extends Controller
{
    public function index(Request $request)
    {
        $statusOptions = ['Open', 'Assigned', 'In Progress', 'Completed', 'Cancelled'];
        $priorityOptions = ['Low', 'Medium', 'High', 'Critical'];
        $cycleTypeOptions = ['1-last', '21-20'];
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'status' => ['nullable', Rule::in($statusOptions)],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $contractId = (int) ($validated['contract_id'] ?? 0);
        $status = (string) ($validated['status'] ?? '');

        $serviceOrders = ServiceOrder::query()
            ->select([
                'id',
                'contract_id',
                'location_id',
                'team_id',
                'order_no',
                'requested_date',
                'scheduled_date',
                'period_start_date',
                'period_end_date',
                'muster_cycle_type',
                'muster_due_days',
                'auto_generate_muster',
                'status',
                'priority',
                'amount',
                'remarks',
            ])
            ->with(['contract.client:id,name', 'location:id,name,city', 'team:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('order_no', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('contract', fn ($contractQuery) => $contractQuery->where('contract_no', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('team', fn ($teamQuery) => $teamQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($contractId > 0, fn ($query) => $query->where('contract_id', $contractId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('requested_date')
            ->paginate(25)
            ->withQueryString();

        $contracts = Contract::query()->orderBy('contract_no')->get(['id', 'client_id', 'location_id', 'contract_no']);
        $locations = Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'city']);
        $teams = Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('master-data.service-orders.index', compact('serviceOrders', 'contracts', 'locations', 'teams', 'statusOptions', 'priorityOptions', 'cycleTypeOptions', 'search', 'contractId', 'status'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createServiceOrderModal');
        }

        $serviceOrder = ServiceOrder::create($this->payload($request));
        DispatchEntry::query()->firstOrCreate(
            ['service_order_id' => $serviceOrder->id],
            ['status' => 'pending']
        );
        $this->logActivity('service_orders', 'create', "Created service order {$serviceOrder->order_no}.", $serviceOrder, $request->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Service order created successfully.');
    }

    public function update(Request $request, ServiceOrder $serviceOrder)
    {
        $validator = Validator::make($request->all(), $this->rules($serviceOrder));

        if ($validator->fails()) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editServiceOrderModal-' . $serviceOrder->id);
        }

        $serviceOrder->update($this->payload($request));

        $dispatchStatus = in_array($serviceOrder->status, ['Completed', 'Cancelled'], true) ? 'closed' : 'pending';
        DispatchEntry::query()->updateOrCreate(
            ['service_order_id' => $serviceOrder->id],
            ['status' => $dispatchStatus]
        );
        $this->logActivity('service_orders', 'update', "Updated service order {$serviceOrder->order_no}.", $serviceOrder, $request->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Service order updated successfully.');
    }

    public function destroy(ServiceOrder $serviceOrder)
    {
        $orderNo = $serviceOrder->order_no;
        $serviceOrder->delete();
        $this->logActivity('service_orders', 'delete', "Deleted service order {$orderNo}.", $serviceOrder->id, request()->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Service order deleted successfully.');
    }

    private function rules(?ServiceOrder $serviceOrder = null): array
    {
        return [
            'contract_id' => ['required', 'exists:contracts,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'order_no' => ['required', 'string', 'max:50', Rule::unique('service_orders', 'order_no')->ignore($serviceOrder?->id)],
            'requested_date' => ['required', 'date'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:requested_date'],
            'period_start_date' => ['required', 'date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'muster_cycle_type' => ['required', Rule::in(['1-last', '21-20'])],
            'muster_due_days' => ['nullable', 'integer', 'min:0', 'max:15'],
            'auto_generate_muster' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['Open', 'Assigned', 'In Progress', 'Completed', 'Cancelled'])],
            'priority' => ['required', Rule::in(['Low', 'Medium', 'High', 'Critical'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'contract_id' => $request->integer('contract_id'),
            'location_id' => $request->integer('location_id'),
            'team_id' => $request->filled('team_id') ? $request->integer('team_id') : null,
            'order_no' => strtoupper((string) $request->input('order_no')),
            'requested_date' => $request->input('requested_date'),
            'scheduled_date' => $request->input('scheduled_date'),
            'period_start_date' => $request->input('period_start_date'),
            'period_end_date' => $request->input('period_end_date'),
            'muster_cycle_type' => $request->input('muster_cycle_type'),
            'muster_due_days' => $request->integer('muster_due_days'),
            'auto_generate_muster' => $request->boolean('auto_generate_muster', true),
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'amount' => $request->filled('amount') ? $request->input('amount') : null,
            'remarks' => $request->input('remarks'),
        ];
    }
}
