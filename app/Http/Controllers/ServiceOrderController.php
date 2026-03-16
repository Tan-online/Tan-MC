<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\DispatchEntry;
use App\Models\Location;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $statusOptions = ['Open', 'Assigned', 'In Progress', 'Completed', 'Cancelled'];
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'status' => ['nullable', Rule::in($statusOptions)],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $clientId = (int) ($validated['client_id'] ?? 0);
        $contractId = (int) ($validated['contract_id'] ?? 0);
        $status = (string) ($validated['status'] ?? '');

        $serviceOrdersQuery = ServiceOrder::query()
            ->select([
                'id',
                'contract_id',
                'location_id',
                'team_id',
                'operation_executive_id',
                'order_no',
                'requested_date',
                'period_start_date',
                'period_end_date',
                'muster_start_day',
                'muster_due_days',
                'auto_generate_muster',
                'status',
                'remarks',
            ])
            ->with(['contract.client:id,name', 'location:id,name,city', 'locations:id,name,city', 'team:id,name', 'operationExecutive:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('order_no', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('contract', fn ($contractQuery) => $contractQuery->where('contract_no', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('locations', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('team', fn ($teamQuery) => $teamQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('operationExecutive', fn ($executiveQuery) => $executiveQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($clientId > 0, fn ($query) => $query->whereHas('contract', fn ($contractQuery) => $contractQuery->where('client_id', $clientId)))
            ->when($contractId > 0, fn ($query) => $query->where('contract_id', $contractId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('requested_date');

        $this->accessControl()->scopeServiceOrders($serviceOrdersQuery, $user);

        $serviceOrders = $serviceOrdersQuery
            ->paginate(25)
            ->withQueryString();

        $contractsQuery = Contract::query()->orderBy('contract_no');
        $this->accessControl()->scopeContracts($contractsQuery, $user);
        $contracts = $contractsQuery->get(['id', 'client_id', 'location_id', 'contract_no']);

        $clients = Client::query()
            ->whereIn('id', $contracts->pluck('client_id')->filter()->unique()->values())
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $locationsQuery = Location::query()->where('is_active', true)->orderBy('name');
        $this->accessControl()->scopeLocations($locationsQuery, $user);
        $locations = $locationsQuery->get(['id', 'name', 'city']);
        $teams = Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $operationsExecutives = User::query()
            ->where('status', 'Active')
            ->where(function ($query) {
                $query
                    ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', 'operations'))
                    ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'operations'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);

        return view('master-data.service-orders.index', compact(
            'serviceOrders',
            'clients',
            'contracts',
            'locations',
            'teams',
            'operationsExecutives',
            'statusOptions',
            'search',
            'clientId',
            'contractId',
            'status'
        ));
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

        $clientContractError = $this->validateClientAndContract(
            $request->user(),
            $request->integer('client_id'),
            $request->integer('contract_id')
        );

        if ($clientContractError !== null) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors(['contract_id' => $clientContractError])
                ->withInput()
                ->with('open_modal', 'createServiceOrderModal');
        }

        $locationError = $this->validateContractLocations(
            $request->user(),
            $request->integer('contract_id'),
            $request->input('location_ids', [])
        );

        if ($locationError !== null) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors(['location_ids' => $locationError])
                ->withInput()
                ->with('open_modal', 'createServiceOrderModal');
        }

        $serviceOrder = ServiceOrder::create($this->payload($request));
        $this->syncLocations($serviceOrder, $request);
        DispatchEntry::query()->firstOrCreate(
            ['service_order_id' => $serviceOrder->id],
            ['status' => 'pending']
        );
        $this->logActivity('service_orders', 'create', "Created service order {$serviceOrder->order_no}.", $serviceOrder, $request->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Sales order created successfully.');
    }

    public function update(Request $request, ServiceOrder $serviceOrder)
    {
        $this->accessControl()->scopeServiceOrders(ServiceOrder::query()->whereKey($serviceOrder->id), $request->user())->firstOrFail();

        $validator = Validator::make($request->all(), $this->rules($serviceOrder));

        if ($validator->fails()) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editServiceOrderModal-' . $serviceOrder->id);
        }

        $clientContractError = $this->validateClientAndContract(
            $request->user(),
            $request->integer('client_id'),
            $request->integer('contract_id')
        );

        if ($clientContractError !== null) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors(['contract_id' => $clientContractError])
                ->withInput()
                ->with('open_modal', 'editServiceOrderModal-' . $serviceOrder->id);
        }

        $locationError = $this->validateContractLocations(
            $request->user(),
            $request->integer('contract_id'),
            $request->input('location_ids', [])
        );

        if ($locationError !== null) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors(['location_ids' => $locationError])
                ->withInput()
                ->with('open_modal', 'editServiceOrderModal-' . $serviceOrder->id);
        }

        $serviceOrder->update($this->payload($request));
        $this->syncLocations($serviceOrder, $request);

        $dispatchStatus = in_array($serviceOrder->status, ['Completed', 'Cancelled'], true) ? 'closed' : 'pending';
        DispatchEntry::query()->updateOrCreate(
            ['service_order_id' => $serviceOrder->id],
            ['status' => $dispatchStatus]
        );
        $this->logActivity('service_orders', 'update', "Updated service order {$serviceOrder->order_no}.", $serviceOrder, $request->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Sales order updated successfully.');
    }

    public function destroy(ServiceOrder $serviceOrder)
    {
        $this->accessControl()->scopeServiceOrders(ServiceOrder::query()->whereKey($serviceOrder->id), request()->user())->firstOrFail();

        $orderNo = $serviceOrder->order_no;
        $serviceOrder->delete();
        $this->logActivity('service_orders', 'delete', "Deleted service order {$orderNo}.", $serviceOrder->id, request()->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Sales order deleted successfully.');
    }

    private function rules(?ServiceOrder $serviceOrder = null): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'contract_id' => ['required', 'exists:contracts,id'],
            'location_ids' => ['required', 'array', 'min:1'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'location_start_dates' => ['nullable', 'array'],
            'location_end_dates' => ['nullable', 'array'],
            'location_start_dates.*' => ['nullable', 'date'],
            'location_end_dates.*' => ['nullable', 'date'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'operation_executive_id' => ['nullable', 'exists:users,id'],
            'order_no' => ['required', 'string', 'max:50', Rule::unique('service_orders', 'order_no')->ignore($serviceOrder?->id)],
            'requested_date' => ['required', 'date'],
            'muster_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'muster_due_days' => ['nullable', 'integer', 'min:0', 'max:15'],
            'auto_generate_muster' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['Open', 'Assigned', 'In Progress', 'Completed', 'Cancelled'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function payload(Request $request): array
    {
        [$periodStartDate, $periodEndDate] = $this->resolvePeriodDates(
            (string) $request->input('requested_date'),
            $request->integer('muster_start_day')
        );

        $locationIds = collect($request->input('location_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        return [
            'contract_id' => $request->integer('contract_id'),
            'location_id' => $locationIds->first(),
            'team_id' => $request->filled('team_id') ? $request->integer('team_id') : null,
            'operation_executive_id' => $request->filled('operation_executive_id') ? $request->integer('operation_executive_id') : null,
            'order_no' => strtoupper((string) $request->input('order_no')),
            'requested_date' => $request->input('requested_date'),
            'scheduled_date' => null,
            'period_start_date' => $periodStartDate,
            'period_end_date' => $periodEndDate,
            'muster_start_day' => $request->integer('muster_start_day'),
            'muster_cycle_type' => $request->integer('muster_start_day') === 21 ? '21-20' : '1-last',
            'muster_due_days' => $request->integer('muster_due_days'),
            'auto_generate_muster' => $request->boolean('auto_generate_muster', true),
            'status' => $request->input('status'),
            'priority' => 'Medium',
            'amount' => null,
            'remarks' => $request->input('remarks'),
        ];
    }

    private function validateClientAndContract(User $user, int $clientId, int $contractId): ?string
    {
        $contractQuery = Contract::query()
            ->whereKey($contractId)
            ->where('client_id', $clientId);

        $this->accessControl()->scopeContracts($contractQuery, $user);

        $isValid = $contractQuery->exists();

        return $isValid ? null : 'The selected contract does not belong to the selected client.';
    }

    private function validateContractLocations(User $user, int $contractId, array $locationIds): ?string
    {
        $locationIds = collect($locationIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) {
            return 'Select at least one location for this sales order.';
        }

        $contractQuery = Contract::query()
            ->whereKey($contractId)
            ->with('locations:id');

        $this->accessControl()->scopeContracts($contractQuery, $user);

        $validLocationIds = $contractQuery->first()?->locations->pluck('id')->all() ?? [];

        foreach ($locationIds as $locationId) {
            if (! in_array($locationId, $validLocationIds, true)) {
                return 'One or more selected locations are not mapped to the selected contract.';
            }
        }

        return null;
    }

    private function resolvePeriodDates(string $requestedDate, int $musterStartDay): array
    {
        $date = Carbon::parse($requestedDate)->startOfDay();
        $startDay = max(1, min(31, $musterStartDay));

        $anchorMonth = $date->day >= $startDay ? $date->copy() : $date->copy()->subMonth();
        $startDate = $anchorMonth->copy()->day(min($startDay, $anchorMonth->daysInMonth))->startOfDay();
        $endDate = $startDate->copy()->addMonth()->subDay()->endOfDay();

        return [
            $startDate->toDateString(),
            $endDate->toDateString(),
        ];
    }

    private function syncLocations(ServiceOrder $serviceOrder, Request $request): void
    {
        $locationIds = collect($request->input('location_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) {
            return;
        }

        $starts = (array) $request->input('location_start_dates', []);
        $ends = (array) $request->input('location_end_dates', []);

        $syncPayload = [];

        foreach ($locationIds as $locationId) {
            $startDate = $starts[$locationId] ?? $serviceOrder->period_start_date?->format('Y-m-d');
            $endDate = $ends[$locationId] ?? $serviceOrder->period_end_date?->format('Y-m-d');

            $syncPayload[$locationId] = [
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
            ];
        }

        $serviceOrder->locations()->sync($syncPayload);
    }
}
