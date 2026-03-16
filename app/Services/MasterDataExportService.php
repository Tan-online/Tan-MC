<?php

namespace App\Services;

use App\Exports\MappedQueryExport;
use App\Models\Department;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\MusterExpected;
use App\Models\OperationArea;
use App\Models\ServiceOrder;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class MasterDataExportService
{
    public function validationRules(string $type): array
    {
        return match ($type) {
            'users' => [
                'search' => ['nullable', 'string', 'max:120'],
            ],
            'departments' => [
                'search' => ['nullable', 'string', 'max:120'],
            ],
            'states' => [
                'search' => ['nullable', 'string', 'max:120'],
            ],
            'operation-areas' => [
                'search' => ['nullable', 'string', 'max:120'],
            ],
            'teams' => [
                'search' => ['nullable', 'string', 'max:120'],
            ],
            'clients' => [
                'search' => ['nullable', 'string', 'max:120'],
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
            ],
            'locations' => [
                'search' => ['nullable', 'string', 'max:120'],
                'state_id' => ['nullable', 'integer', 'exists:states,id'],
                'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            ],
            'contracts' => [
                'search' => ['nullable', 'string', 'max:120'],
                'client_id' => ['nullable', 'integer', 'exists:clients,id'],
                'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            ],
            'service-orders' => [
                'search' => ['nullable', 'string', 'max:120'],
                'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
                'status' => ['nullable', Rule::in(['Open', 'Assigned', 'In Progress', 'Completed', 'Cancelled'])],
            ],
            'muster-roll' => [
                'client_id' => ['nullable', 'integer', 'exists:clients,id'],
                'contract_id' => ['required', 'integer', 'exists:contracts,id'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'year' => ['nullable', 'integer', 'between:2020,2100'],
                'status' => ['nullable', Rule::in(['Pending', 'Received', 'Late', 'Approved', 'Returned', 'Closed'])],
            ],
            default => abort(404),
        };
    }

    public function definition(string $type, array $validated, MusterComplianceService $musterComplianceService): array
    {
        return match ($type) {
            'users' => $this->usersDefinition($validated),
            'departments' => $this->departmentsDefinition($validated),
            'states' => $this->statesDefinition($validated),
            'operation-areas' => $this->operationAreasDefinition($validated),
            'teams' => $this->teamsDefinition($validated),
            'clients' => $this->clientsDefinition($validated),
            'locations' => $this->locationsDefinition($validated),
            'contracts' => $this->contractsDefinition($validated),
            'service-orders' => $this->serviceOrdersDefinition($validated),
            'muster-roll' => $this->musterRollDefinition($validated, $musterComplianceService),
            default => abort(404),
        };
    }

    private function usersDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));

        $query = User::query()
            ->select(['id', 'employee_code', 'name', 'department_id', 'manager_id', 'hod_id', 'status'])
            ->with(['department:id,name', 'manager:id,name', 'hod:id,name', 'role:id,name', 'roles:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name');

        return [
            'permission' => 'users.view',
            'module' => 'users',
            'description' => 'Exported users master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'users-export',
            'export' => new MappedQueryExport(
                $query,
                ['Employee Code', 'Name', 'Department', 'Role', 'Manager', 'HOD', 'Status'],
                fn (User $user) => [
                    $user->employee_code,
                    $user->name,
                    $user->department?->name,
                    $user->roleNames(),
                    $user->manager?->name,
                    $user->hod?->name,
                    $user->status,
                ],
            ),
        ];
    }

    private function departmentsDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Department::query()
            ->select(['id', 'name', 'code', 'description', 'is_active'])
            ->withCount('teams')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        return [
            'permission' => 'departments.view',
            'module' => 'departments',
            'description' => 'Exported departments master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'departments-export',
            'export' => new MappedQueryExport(
                $query,
                ['Name', 'Code', 'Description', 'Teams', 'Status'],
                fn (Department $department) => [
                    $department->name,
                    $department->code,
                    $department->description,
                    $department->teams_count,
                    $department->is_active ? 'Active' : 'Inactive',
                ],
            ),
        ];
    }

    private function statesDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));

        $query = State::query()
            ->select(['id', 'name', 'code', 'region', 'is_active'])
            ->withCount('operationAreas')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        return [
            'permission' => 'states.view',
            'module' => 'states',
            'description' => 'Exported states master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'states-export',
            'export' => new MappedQueryExport(
                $query,
                ['State', 'Code', 'Region', 'Operation Areas', 'Status'],
                fn (State $state) => [
                    $state->name,
                    $state->code,
                    $state->region,
                    $state->operation_areas_count,
                    $state->is_active ? 'Active' : 'Inactive',
                ],
            ),
        ];
    }

    private function operationAreasDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));

        $query = OperationArea::query()
            ->select(['id', 'name', 'code', 'state_id', 'description', 'is_active'])
            ->with(['state:id,name'])
            ->withCount('teams')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('state', fn (Builder $stateQuery) => $stateQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name');

        return [
            'permission' => 'operation_areas.view',
            'module' => 'operation_areas',
            'description' => 'Exported operation areas master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'operation-areas-export',
            'export' => new MappedQueryExport(
                $query,
                ['Name', 'Code', 'State', 'Description', 'Teams', 'Status'],
                fn (OperationArea $operationArea) => [
                    $operationArea->name,
                    $operationArea->code,
                    $operationArea->state?->name,
                    $operationArea->description,
                    $operationArea->teams_count,
                    $operationArea->is_active ? 'Active' : 'Inactive',
                ],
            ),
        ];
    }

    private function teamsDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));

        $query = Team::query()
            ->select(['id', 'name', 'code', 'department_id', 'operation_area_id', 'operation_executive_id', 'manager_id', 'hod_id', 'is_active'])
            ->with([
                'department:id,name',
                'operationArea:id,name',
                'operationExecutive:id,name',
                'manager:id,name',
                'hod:id,name',
            ])
            ->withCount('serviceOrders')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('operationArea', fn (Builder $areaQuery) => $areaQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name');

        return [
            'permission' => 'teams.view',
            'module' => 'teams',
            'description' => 'Exported teams master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'teams-export',
            'export' => new MappedQueryExport(
                $query,
                ['Team', 'Code', 'Department', 'Operation Area', 'Operation Executive', 'Manager', 'HOD', 'Members', 'Status'],
                fn (Team $team) => [
                    $team->name,
                    $team->code,
                    $team->department?->name,
                    $team->operationArea?->name,
                    $team->operationExecutive?->name,
                    $team->manager?->name,
                    $team->hod?->name,
                    $team->service_orders_count,
                    $team->is_active ? 'Active' : 'Inactive',
                ],
            ),
        ];
    }

    private function clientsDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));

        $query = Client::query()
            ->select(['id', 'name', 'code', 'is_active'])
            ->withCount(['locations', 'contracts'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name');

        return [
            'permission' => 'clients.view',
            'module' => 'clients',
            'description' => 'Exported clients master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'clients-export',
            'export' => new MappedQueryExport(
                $query,
                ['Client Code', 'Client Name', 'Locations', 'Contracts', 'Status'],
                fn (Client $client) => [
                    $client->code,
                    $client->name,
                    $client->locations_count,
                    $client->contracts_count,
                    $client->is_active ? 'Active' : 'Inactive',
                ],
            ),
        ];
    }

    private function locationsDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));
        $stateId = (int) ($validated['state_id'] ?? 0);
        $clientId = (int) ($validated['client_id'] ?? 0);

        $query = Location::query()
            ->select(['id', 'client_id', 'state_id', 'code', 'name', 'address', 'is_active'])
            ->with(['client:id,name,code', 'state:id,name,code'])
            ->withCount(['contracts', 'serviceOrders'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('state', fn (Builder $stateQuery) => $stateQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($stateId > 0, fn (Builder $query) => $query->where('state_id', $stateId))
            ->when($clientId > 0, fn (Builder $query) => $query->where('client_id', $clientId))
            ->orderBy('name');

        return [
            'permission' => 'locations.view',
            'module' => 'locations',
            'description' => 'Exported locations master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'locations-export',
            'export' => new MappedQueryExport(
                $query,
                ['Location Code', 'Location', 'Client', 'State', 'Contracts', 'Service Orders', 'Status'],
                fn (Location $location) => [
                    $location->code,
                    $location->name,
                    $location->client?->name,
                    $location->state?->name,
                    $location->contracts_count,
                    $location->service_orders_count,
                    $location->is_active ? 'Active' : 'Inactive',
                ],
            ),
        ];
    }

    private function contractsDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));
        $clientId = (int) ($validated['client_id'] ?? 0);
        $status = (string) ($validated['status'] ?? '');

        $query = Contract::query()
            ->select(['id', 'client_id', 'location_id', 'contract_no', 'contract_name', 'start_date', 'end_date', 'status', 'scope'])
            ->with(['client:id,name,code', 'location:id,name'])
            ->withCount('serviceOrders')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('contract_no', 'like', "%{$search}%")
                        ->orWhere('contract_name', 'like', "%{$search}%")
                        ->orWhere('scope', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($clientId > 0, fn (Builder $query) => $query->where('client_id', $clientId))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('start_date');

        return [
            'permission' => 'contracts.view',
            'module' => 'contracts',
            'description' => 'Exported contracts master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'contracts-export',
            'export' => new MappedQueryExport(
                $query,
                ['Contract No', 'Contract Name', 'Client', 'Start Date', 'Deactivate Date', 'Status', 'Service Orders'],
                fn (Contract $contract) => [
                    $contract->contract_no,
                    $contract->contract_name,
                    $contract->client?->name,
                    optional($contract->start_date)->format('Y-m-d'),
                    optional($contract->end_date)->format('Y-m-d'),
                    $contract->status,
                    $contract->service_orders_count,
                ],
            ),
        ];
    }

    private function serviceOrdersDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));
        $contractId = (int) ($validated['contract_id'] ?? 0);
        $status = (string) ($validated['status'] ?? '');

        $query = ServiceOrder::query()
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
                'status',
                'remarks',
            ])
            ->with(['contract.client:id,name', 'location:id,name,city', 'locations:id,name', 'team:id,name', 'operationExecutive:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('order_no', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('contract', fn (Builder $contractQuery) => $contractQuery->where('contract_no', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('locations', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('operationExecutive', fn (Builder $executiveQuery) => $executiveQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($contractId > 0, fn (Builder $query) => $query->where('contract_id', $contractId))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('requested_date');

        return [
            'permission' => 'service_orders.view',
            'module' => 'service_orders',
            'description' => 'Exported sales orders.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'service-orders-export',
            'export' => new MappedQueryExport(
                $query,
                ['Sales Order No', 'Contract', 'Client', 'Locations', 'Team', 'Operation Executive', 'Requested Date', 'Muster Start Day', 'Period Start', 'Period End', 'Status'],
                fn (ServiceOrder $serviceOrder) => [
                    $serviceOrder->order_no,
                    $serviceOrder->contract?->contract_no,
                    $serviceOrder->contract?->client?->name,
                    $serviceOrder->locations->pluck('name')->filter()->implode(', '),
                    $serviceOrder->team?->name,
                    $serviceOrder->operationExecutive?->name,
                    optional($serviceOrder->requested_date)->format('Y-m-d'),
                    $serviceOrder->muster_start_day,
                    optional($serviceOrder->period_start_date)->format('Y-m-d'),
                    optional($serviceOrder->period_end_date)->format('Y-m-d'),
                    $serviceOrder->status,
                ],
            ),
        ];
    }

    private function musterRollDefinition(array $validated, MusterComplianceService $musterComplianceService): array
    {
        $contractId = (int) $validated['contract_id'];
        $month = max(1, min(12, (int) ($validated['month'] ?? now()->month)));
        $year = (int) ($validated['year'] ?? now()->year);
        $status = (string) ($validated['status'] ?? '');

        $contract = Contract::query()
            ->with(['client:id,name'])
            ->findOrFail($contractId);

        $cycle = $musterComplianceService->ensureCycleForContractMonth($contract, $month, $year);

        abort_if(! $cycle, 422, 'No active service order period found for the selected contract and month.');

        $query = MusterExpected::query()
            ->select([
                'id',
                'muster_cycle_id',
                'contract_id',
                'location_id',
                'executive_mapping_id',
                'status',
                'received_via',
                'received_at',
                'approved_at',
                'final_closed_at',
            ])
            ->with([
                'contract.client:id,name',
                'location.state:id,name',
                'location:id,name,city,state_id',
                'executiveMapping:id,executive_name',
            ])
            ->where('muster_cycle_id', $cycle->id)
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->orderBy('location_id');

        return [
            'permission' => 'workflow.view',
            'module' => 'muster',
            'description' => 'Exported muster roll.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'muster-roll-export',
            'export' => new MappedQueryExport(
                $query,
                ['Cycle', 'Contract', 'Client', 'Location', 'City', 'State', 'Executive', 'Status', 'Received Via', 'Received At', 'Approved At', 'Closed At'],
                fn (MusterExpected $expected) => [
                    $cycle->cycle_label,
                    $expected->contract?->contract_no,
                    $expected->contract?->client?->name,
                    $expected->location?->name,
                    $expected->location?->city,
                    $expected->location?->state?->name,
                    $expected->executiveMapping?->executive_name,
                    $expected->status,
                    $expected->received_via,
                    optional($expected->received_at)->format('Y-m-d H:i:s'),
                    optional($expected->approved_at)->format('Y-m-d H:i:s'),
                    optional($expected->final_closed_at)->format('Y-m-d H:i:s'),
                ],
            ),
        ];
    }
}