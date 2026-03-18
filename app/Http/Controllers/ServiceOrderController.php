<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\DispatchEntry;
use App\Models\Location;
use App\Models\ServiceOrder;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $statusOptions = ServiceOrder::displayStatusOptions();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'status' => ['nullable', Rule::in(ServiceOrder::allowedStatusValues())],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $clientId = (int) ($validated['client_id'] ?? 0);
        $contractId = (int) ($validated['contract_id'] ?? 0);
        $locationId = (int) ($validated['location_id'] ?? 0);
        $status = (string) ($validated['status'] ?? '');

        $serviceOrdersQuery = ServiceOrder::query()
            ->select([
                'id',
                'contract_id',
                'state_id',
                'location_id',
                'team_id',
                'operation_executive_id',
                'order_no',
                'so_name',
                'requested_date',
                'period_start_date',
                'period_end_date',
                'muster_start_day',
                'muster_due_days',
                'auto_generate_muster',
                'status',
                'remarks',
            ])
            ->with([
                'contract.client:id,name,code',
                'state:id,name,code',
                'location:id,name,code,city',
                'locations:id,name,code,city,state_id,client_id,is_active',
                'activeLocations:id,name,code,city,state_id,client_id,is_active',
            ])
            ->withCount([
                'locations as active_locations_count' => function ($query) {
                    $query->where(function ($innerQuery) {
                        $innerQuery
                            ->whereNull('service_order_location.end_date')
                            ->orWhereDate('service_order_location.end_date', '>=', now()->toDateString());
                    });
                },
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('order_no', 'like', "%{$search}%")
                        ->orWhere('so_name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('contract', fn ($contractQuery) => $contractQuery->where('contract_no', 'like', "%{$search}%"))
                        ->orWhereHas('contract.client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('locations', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('state', fn ($stateQuery) => $stateQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($clientId > 0, fn ($query) => $query->whereHas('contract', fn ($contractQuery) => $contractQuery->where('client_id', $clientId)))
            ->when($contractId > 0, fn ($query) => $query->where('contract_id', $contractId))
            ->when($locationId > 0, fn ($query) => $query->whereHas('locations', fn ($locationQuery) => $locationQuery->where('locations.id', $locationId)))
            ->when($status !== '', fn ($query) => $query->whereIn('status', ServiceOrder::filterStatusesFor($status)))
            ->orderByDesc('requested_date');

        $this->accessControl()->scopeServiceOrders($serviceOrdersQuery, $user);

        $serviceOrders = $serviceOrdersQuery
            ->paginate(25)
            ->withQueryString();

        $contractsQuery = Contract::query()
            ->with(['locations:id,state_id'])
            ->orderBy('contract_no');
        $this->accessControl()->scopeContracts($contractsQuery, $user);
        $contracts = $contractsQuery->get(['id', 'client_id', 'location_id', 'contract_no']);

        $clients = Client::query()
            ->whereIn('id', $contracts->pluck('client_id')->filter()->unique()->values())
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $locationsQuery = Location::query()->where('is_active', true)->orderBy('name');
        $this->accessControl()->scopeLocations($locationsQuery, $user);
        $locationFilterOptions = $locationsQuery->limit(500)->get(['id', 'name', 'city']);
        $clientStatePairsQuery = Location::query()
            ->select(['client_id', 'state_id'])
            ->distinct();
        $this->accessControl()->scopeLocations($clientStatePairsQuery, $user);
        $clientStatePairs = $clientStatePairsQuery->get();
        $stateClientMap = $clientStatePairs
            ->groupBy('state_id')
            ->map(fn ($rows) => $rows->pluck('client_id')->filter()->unique()->values()->all())
            ->all();

        $states = State::query()
            ->whereIn('id', $clientStatePairs->pluck('state_id')->filter()->unique()->values())
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
        $operationsExecutives = User::query()
            ->where('status', 'Active')
            ->where(function ($query) {
                $query
                    ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', 'operations'))
                    ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'operations'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);

        $selectedLocationIds = collect((array) old('location_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $selectedLocationsLookup = $selectedLocationIds->isEmpty()
            ? collect()
            : Location::query()
                ->whereIn('id', $selectedLocationIds->all())
                ->get(['id', 'name', 'code', 'city', 'state_id'])
                ->keyBy('id');

        return view('master-data.service-orders.index', compact(
            'serviceOrders',
            'clients',
            'contracts',
            'states',
            'stateClientMap',
            'locationFilterOptions',
            'selectedLocationsLookup',
            'operationsExecutives',
            'statusOptions',
            'search',
            'clientId',
            'contractId',
            'locationId',
            'status'
        ));
    }

    public function locationOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'all' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $clientQuery = Client::query()->whereKey($validated['client_id']);
        $this->accessControl()->scopeClients($clientQuery, $user);
        abort_unless($clientQuery->exists(), 404);

        $search = trim((string) ($validated['search'] ?? ''));
        $stateId = (int) ($validated['state_id'] ?? 0);
        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = Location::query()
            ->select(['locations.id', 'locations.name', 'locations.code', 'locations.city', 'locations.state_id'])
            ->where('locations.client_id', $validated['client_id'])
            ->where('locations.is_active', true)
            ->with('state:id,name,code')
            ->when($stateId > 0, fn ($builder) => $builder->where('locations.state_id', $stateId))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('locations.name', 'like', "%{$search}%")
                        ->orWhere('locations.code', 'like', "%{$search}%")
                        ->orWhere('locations.city', 'like', "%{$search}%");
                });
            })
            ->orderBy('locations.name')
            ->distinct('locations.id');

        $this->accessControl()->scopeLocations($query, $user, 'locations');

        if ($request->boolean('all')) {
            return response()->json([
                'data' => $query->pluck('locations.id')->map(fn ($id) => (int) $id)->values()->all(),
            ]);
        }

        $locations = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($locations->items())->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'city' => $location->city,
                'state_id' => $location->state_id,
                'state_name' => $location->state?->name,
            ])->values()->all(),
            'meta' => [
                'current_page' => $locations->currentPage(),
                'last_page' => $locations->lastPage(),
                'per_page' => $locations->perPage(),
                'total' => $locations->total(),
            ],
        ]);
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

        if ($this->hasLocationPayload($request)) {
            $locationError = $this->validateClientLocations(
                $request->user(),
                $request->integer('client_id'),
                $request->integer('state_id'),
                $request->input('location_ids', [])
            );

            if ($locationError !== null) {
                return redirect()
                    ->route('service-orders.index')
                    ->withErrors(['location_ids' => $locationError])
                    ->withInput()
                    ->with('open_modal', 'createServiceOrderModal');
            }

            $dateValidationError = $this->validateLocationDates($request);

            if ($dateValidationError !== null) {
                return redirect()
                    ->route('service-orders.index')
                    ->withErrors(['location_ids' => $dateValidationError])
                    ->withInput()
                    ->with('open_modal', 'createServiceOrderModal');
            }
        }

        $serviceOrder = ServiceOrder::create($this->payload($request));

        if ($this->hasLocationPayload($request)) {
            $this->syncLocations($serviceOrder, $request);
        }

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
        $serviceOrder = $this->accessControl()
            ->scopeServiceOrders(ServiceOrder::query()->with('contract:id,client_id')->whereKey($serviceOrder->id), $request->user())
            ->firstOrFail();

        $originalClientId = (int) $serviceOrder->contract?->client_id;
        $originalStateId = (int) $serviceOrder->state_id;

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

        if ($this->hasLocationPayload($request)) {
            $locationError = $this->validateClientLocations(
                $request->user(),
                $request->integer('client_id'),
                $request->integer('state_id'),
                $request->input('location_ids', [])
            );

            if ($locationError !== null) {
                return redirect()
                    ->route('service-orders.index')
                    ->withErrors(['location_ids' => $locationError])
                    ->withInput()
                    ->with('open_modal', 'editServiceOrderModal-' . $serviceOrder->id);
            }

            $dateValidationError = $this->validateLocationDates($request);

            if ($dateValidationError !== null) {
                return redirect()
                    ->route('service-orders.index')
                    ->withErrors(['location_ids' => $dateValidationError])
                    ->withInput()
                    ->with('open_modal', 'editServiceOrderModal-' . $serviceOrder->id);
            }
        }

        $serviceOrder->update($this->payload($request, $serviceOrder));

        if ($this->hasLocationPayload($request)) {
            $this->syncLocations($serviceOrder, $request);
        } elseif ($originalClientId !== $request->integer('client_id') || $originalStateId !== $request->integer('state_id')) {
            $this->clearLocationAssignments($serviceOrder, $request->input('requested_date'));
        }

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

    public function updateLocations(Request $request, ServiceOrder $serviceOrder)
    {
        $serviceOrder = $this->accessControl()
            ->scopeServiceOrders(ServiceOrder::query()->with('contract:id,client_id')->whereKey($serviceOrder->id), $request->user())
            ->firstOrFail();

        $validator = Validator::make($request->all(), $this->locationRules());

        if ($validator->fails()) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'serviceOrderLocationModal-' . $serviceOrder->id);
        }

        $clientId = (int) $serviceOrder->contract?->client_id;
        $stateId = (int) $serviceOrder->state_id;

        if ($clientId <= 0 || $stateId <= 0) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors(['location_ids' => 'Client and state must be set before managing locations.'])
                ->withInput()
                ->with('open_modal', 'serviceOrderLocationModal-' . $serviceOrder->id);
        }

        $locationError = $this->validateClientLocations(
            $request->user(),
            $clientId,
            $stateId,
            $request->input('location_ids', []),
            allowEmpty: true
        );

        if ($locationError !== null) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors(['location_ids' => $locationError])
                ->withInput()
                ->with('open_modal', 'serviceOrderLocationModal-' . $serviceOrder->id);
        }

        $dateValidationError = $this->validateLocationDates($request);

        if ($dateValidationError !== null) {
            return redirect()
                ->route('service-orders.index')
                ->withErrors(['location_ids' => $dateValidationError])
                ->withInput()
                ->with('open_modal', 'serviceOrderLocationModal-' . $serviceOrder->id);
        }

        $this->syncLocations($serviceOrder, $request);
        $this->logActivity('service_orders', 'update', "Updated locations for service order {$serviceOrder->order_no}.", $serviceOrder, $request->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Sales order locations updated successfully.');
    }

    public function terminate(ServiceOrder $serviceOrder)
    {
        $this->accessControl()->scopeServiceOrders(ServiceOrder::query()->whereKey($serviceOrder->id), request()->user())->firstOrFail();

        $terminationDate = now()->toDateString();

        $serviceOrder->update([
            'status' => ServiceOrder::normalizeStatus('Terminate'),
        ]);

        $serviceOrder->locations()
            ->newPivotStatement()
            ->where('service_order_id', $serviceOrder->id)
            ->whereNull('end_date')
            ->update([
                'end_date' => $terminationDate,
                'updated_at' => now(),
            ]);

        DispatchEntry::query()->updateOrCreate(
            ['service_order_id' => $serviceOrder->id],
            ['status' => 'closed']
        );

        $this->logActivity('service_orders', 'terminate', "Terminated sales order {$serviceOrder->order_no}.", $serviceOrder, request()->user());

        return redirect()
            ->route('service-orders.index')
            ->with('status', 'Sales order terminated successfully.');
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
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'order_no' => ['required', 'string', 'max:50', Rule::unique('service_orders', 'order_no')->ignore($serviceOrder?->id)],
            'so_name' => ['nullable', 'string', 'max:150'],
            'requested_date' => ['required', 'date'],
            'muster_start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'auto_generate_muster' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(ServiceOrder::allowedStatusValues())],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function locationRules(): array
    {
        return [
            'location_sync_submitted' => ['required', 'boolean'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'location_start_dates' => ['nullable', 'array'],
            'location_end_dates' => ['nullable', 'array'],
            'location_operation_executive_ids' => ['nullable', 'array'],
            'location_operation_executive_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'location_muster_due_days' => ['nullable', 'array'],
            'location_muster_due_days.*' => ['nullable', 'integer', 'min:0', 'max:15'],
            'location_start_dates.*' => ['nullable', 'date'],
            'location_end_dates.*' => ['nullable', 'date'],
            'removed_location_ids' => ['nullable', 'array'],
            'removed_location_ids.*' => ['integer', 'exists:locations,id'],
            'removed_location_end_dates' => ['nullable', 'array'],
            'removed_location_end_dates.*' => ['nullable', 'date'],
        ];
    }

    private function payload(Request $request, ?ServiceOrder $serviceOrder = null): array
    {
        [$periodStartDate, $periodEndDate] = $this->resolvePeriodDates(
            (string) $request->input('requested_date'),
            $request->integer('muster_start_day')
        );

        $locationIds = $this->extractLocationIds($request);
        $locationId = $locationIds->first();

        if ($locationId === null && $serviceOrder !== null) {
            $locationId = $serviceOrder->location_id;
        }

        $status = ServiceOrder::normalizeStatus((string) $request->input('status'));

        return [
            'contract_id' => $request->integer('contract_id'),
            'state_id' => $request->integer('state_id'),
            'location_id' => $locationId,
            'team_id' => null,
            'operation_executive_id' => $serviceOrder?->operation_executive_id,
            'order_no' => strtoupper((string) $request->input('order_no')),
            'so_name' => trim((string) $request->input('so_name')) ?: null,
            'requested_date' => $request->input('requested_date'),
            'scheduled_date' => null,
            'period_start_date' => $periodStartDate,
            'period_end_date' => $periodEndDate,
            'muster_start_day' => $request->integer('muster_start_day'),
            'muster_cycle_type' => $request->integer('muster_start_day') === 21 ? '21-20' : '1-last',
            'muster_due_days' => $serviceOrder?->muster_due_days ?? 0,
            'auto_generate_muster' => $request->boolean('auto_generate_muster', true),
            'status' => $status,
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

    private function validateClientLocations(User $user, int $clientId, int $stateId, array $locationIds, bool $allowEmpty = false): ?string
    {
        $locationIds = collect($locationIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) {
            return $allowEmpty ? null : 'Select at least one location for this sales order.';
        }

        $validLocationsQuery = Location::query()
            ->where('locations.client_id', $clientId)
            ->whereIn('locations.id', $locationIds->all())
            ->where('locations.is_active', true)
            ->when($stateId > 0, fn ($builder) => $builder->where('locations.state_id', $stateId))
            ->distinct('locations.id');

        $this->accessControl()->scopeLocations($validLocationsQuery, $user, 'locations');

        $validCount = $validLocationsQuery->count('locations.id');

        if ($validCount !== $locationIds->count()) {
            return 'Select only locations that belong to the selected client and state.';
        }

        return null;
    }

    private function validateLocationDates(Request $request): ?string
    {
        $requestedDate = $request->input('requested_date');
        $locationIds = collect($request->input('location_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $starts = (array) $request->input('location_start_dates', []);
        $ends = (array) $request->input('location_end_dates', []);
        $executiveIds = (array) $request->input('location_operation_executive_ids', []);
        $removedIds = collect($request->input('removed_location_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
        $removedEnds = (array) $request->input('removed_location_end_dates', []);

        foreach ($locationIds as $locationId) {
            $startDate = $starts[$locationId] ?? $requestedDate;
            $endDate = $ends[$locationId] ?? null;

            if (! $startDate) {
                return 'Each selected location requires a start date.';
            }

            if (empty($executiveIds[$locationId])) {
                return 'Select an operation executive for each selected location.';
            }

            if ($endDate && $startDate && Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
                return 'Location end date cannot be earlier than the location start date.';
            }
        }

        foreach ($removedIds as $locationId) {
            $endDate = $removedEnds[$locationId] ?? null;

            if (! $endDate) {
                return 'Each removed location requires an end date.';
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
        $locationIds = $this->extractLocationIds($request);

        $starts = (array) $request->input('location_start_dates', []);
        $ends = (array) $request->input('location_end_dates', []);
        $executiveIds = (array) $request->input('location_operation_executive_ids', []);
        $musterDueDays = (array) $request->input('location_muster_due_days', []);
        $removedEnds = (array) $request->input('removed_location_end_dates', []);
        $existingLocationIds = $serviceOrder->activeLocations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $defaultEndDate = $request->input('requested_date') ?: now()->toDateString();
        $status = $request->filled('status')
            ? ServiceOrder::normalizeStatus((string) $request->input('status'))
            : $serviceOrder->display_status;
        $isTerminated = $status === 'Terminate';

        $syncPayload = [];

        foreach ($locationIds as $locationId) {
            $startDate = $starts[$locationId] ?? $serviceOrder->requested_date?->format('Y-m-d');
            $endDate = $ends[$locationId] ?? ($isTerminated ? $defaultEndDate : null);

            $syncPayload[$locationId] = [
                'start_date' => $startDate ?: null,
                'end_date' => $endDate ?: null,
                'operation_executive_id' => ! empty($executiveIds[$locationId]) ? (int) $executiveIds[$locationId] : null,
                'muster_due_days' => max(0, (int) ($musterDueDays[$locationId] ?? 0)),
            ];
        }

        if ($syncPayload !== []) {
            $serviceOrder->locations()->syncWithoutDetaching($syncPayload);

            foreach ($syncPayload as $locationId => $attributes) {
                $serviceOrder->locations()->updateExistingPivot($locationId, $attributes);
            }
        }

        $removedIds = collect($existingLocationIds)
            ->diff(array_keys($syncPayload))
            ->values();

        foreach ($removedIds as $locationId) {
            $serviceOrder->locations()->updateExistingPivot($locationId, [
                'end_date' => $removedEnds[$locationId] ?? $defaultEndDate,
            ]);
        }

        $serviceOrder->syncSummaryFromLocationAssignments();
    }

    private function clearLocationAssignments(ServiceOrder $serviceOrder, ?string $endDate = null): void
    {
        $serviceOrder->locations()
            ->newPivotStatement()
            ->where('service_order_id', $serviceOrder->id)
            ->whereNull('end_date')
            ->update([
                'end_date' => $endDate ?: now()->toDateString(),
                'updated_at' => now(),
            ]);

        $serviceOrder->forceFill([
            'location_id' => null,
            'operation_executive_id' => null,
            'muster_due_days' => 0,
        ])->saveQuietly();
    }

    private function extractLocationIds(Request $request)
    {
        return collect($request->input('location_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }

    private function hasLocationPayload(Request $request): bool
    {
        return $request->hasAny([
            'location_ids',
            'location_start_dates',
            'location_end_dates',
            'location_operation_executive_ids',
            'location_muster_due_days',
            'removed_location_ids',
            'removed_location_end_dates',
        ]);
    }
}
