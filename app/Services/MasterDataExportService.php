<?php

namespace App\Services;

use App\Exports\MappedQueryExport;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\MusterExpected;
use App\Models\ServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class MasterDataExportService
{
    public function validationRules(string $type): array
    {
        return match ($type) {
            'clients' => [
                'search' => ['nullable', 'string', 'max:120'],
                'industry' => ['nullable', 'string', 'max:120'],
            ],
            'locations' => [
                'search' => ['nullable', 'string', 'max:120'],
                'state_id' => ['nullable', 'integer', 'exists:states,id'],
            ],
            'contracts' => [
                'search' => ['nullable', 'string', 'max:120'],
                'client_id' => ['nullable', 'integer', 'exists:clients,id'],
                'status' => ['nullable', Rule::in(['Draft', 'Active', 'Expired', 'Closed'])],
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
            'clients' => $this->clientsDefinition($validated),
            'locations' => $this->locationsDefinition($validated),
            'contracts' => $this->contractsDefinition($validated),
            'service-orders' => $this->serviceOrdersDefinition($validated),
            'muster-roll' => $this->musterRollDefinition($validated, $musterComplianceService),
            default => abort(404),
        };
    }

    private function clientsDefinition(array $validated): array
    {
        $search = trim((string) ($validated['search'] ?? ''));
        $industry = trim((string) ($validated['industry'] ?? ''));

        $query = Client::query()
            ->select(['id', 'name', 'code', 'contact_person', 'email', 'phone', 'industry', 'is_active'])
            ->withCount(['locations', 'contracts'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($industry !== '', fn (Builder $query) => $query->where('industry', $industry))
            ->orderBy('name');

        return [
            'permission' => 'clients.view',
            'module' => 'clients',
            'description' => 'Exported clients master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'clients-export',
            'export' => new MappedQueryExport(
                $query,
                ['Client', 'Code', 'Contact Person', 'Email', 'Phone', 'Industry', 'Locations', 'Contracts', 'Status'],
                fn (Client $client) => [
                    $client->name,
                    $client->code,
                    $client->contact_person,
                    $client->email,
                    $client->phone,
                    $client->industry,
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

        $query = Location::query()
            ->select(['id', 'client_id', 'state_id', 'operation_area_id', 'name', 'city', 'postal_code', 'address', 'is_active'])
            ->with(['client:id,name,code', 'state:id,name,code', 'operationArea:id,name'])
            ->withCount(['contracts', 'serviceOrders'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('postal_code', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('state', fn (Builder $stateQuery) => $stateQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($stateId > 0, fn (Builder $query) => $query->where('state_id', $stateId))
            ->orderBy('name');

        return [
            'permission' => 'locations.view',
            'module' => 'locations',
            'description' => 'Exported locations master data.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'locations-export',
            'export' => new MappedQueryExport(
                $query,
                ['Location', 'Client', 'State', 'Operation Area', 'City', 'Postal Code', 'Contracts', 'Service Orders', 'Status'],
                fn (Location $location) => [
                    $location->name,
                    $location->client?->name,
                    $location->state?->name,
                    $location->operationArea?->name,
                    $location->city,
                    $location->postal_code,
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
            ->select(['id', 'client_id', 'location_id', 'contract_no', 'start_date', 'end_date', 'contract_value', 'status', 'scope'])
            ->with(['client:id,name,code', 'location:id,name,city'])
            ->withCount('locations')
            ->withCount('serviceOrders')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('contract_no', 'like', "%{$search}%")
                        ->orWhere('scope', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"));
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
                ['Contract No', 'Client', 'Primary Location', 'Covered Sites', 'Start Date', 'End Date', 'Status', 'Service Orders', 'Value'],
                fn (Contract $contract) => [
                    $contract->contract_no,
                    $contract->client?->name,
                    $contract->location?->name,
                    $contract->locations_count,
                    optional($contract->start_date)->format('Y-m-d'),
                    optional($contract->end_date)->format('Y-m-d'),
                    $contract->status,
                    $contract->service_orders_count,
                    $contract->contract_value,
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
                'order_no',
                'requested_date',
                'scheduled_date',
                'period_start_date',
                'period_end_date',
                'muster_cycle_type',
                'muster_due_days',
                'status',
                'priority',
                'amount',
                'remarks',
            ])
            ->with(['contract.client:id,name', 'location:id,name,city', 'team:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('order_no', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('contract', fn (Builder $contractQuery) => $contractQuery->where('contract_no', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($contractId > 0, fn (Builder $query) => $query->where('contract_id', $contractId))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('requested_date');

        return [
            'permission' => 'service_orders.view',
            'module' => 'service_orders',
            'description' => 'Exported service orders.',
            'record_count' => (clone $query)->count(),
            'file_name_base' => 'service-orders-export',
            'export' => new MappedQueryExport(
                $query,
                ['Order No', 'Contract', 'Client', 'Location', 'Team', 'Requested Date', 'Scheduled Date', 'Status', 'Priority', 'Amount'],
                fn (ServiceOrder $serviceOrder) => [
                    $serviceOrder->order_no,
                    $serviceOrder->contract?->contract_no,
                    $serviceOrder->contract?->client?->name,
                    $serviceOrder->location?->name,
                    $serviceOrder->team?->name,
                    optional($serviceOrder->requested_date)->format('Y-m-d'),
                    optional($serviceOrder->scheduled_date)->format('Y-m-d'),
                    $serviceOrder->status,
                    $serviceOrder->priority,
                    $serviceOrder->amount,
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