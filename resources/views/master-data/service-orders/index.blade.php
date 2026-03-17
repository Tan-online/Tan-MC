@extends('layouts.app')

@section('title', 'Sales Orders | Tan-MC')

@section('content')
    <x-page-header
        title="Sales Orders"
        subtitle="Client-driven order workflow with contract validation, operation ownership, and multi-location mapping."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Sales Orders'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                @include('master-data.import-controls', ['type' => 'service-orders', 'label' => 'Sales Orders', 'modalId' => 'serviceOrdersImportModal'])
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createServiceOrderModal" @disabled($contracts->isEmpty() || $locations->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Sales Order
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @include('master-data.import-report', ['type' => 'service-orders'])

    @if ($contracts->isEmpty() || $locations->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add contracts and locations before creating sales orders.</div>
    @endif

    <x-table title="Sales Order Queue" description="Track assignment, location coverage, and monthly muster period windows." :loading="true" :columns="7" :rows="5">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('service-orders.index') }}" class="d-flex flex-wrap gap-2" data-loading-form>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search sales orders">
                </div>
                <select name="client_id" class="form-select">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($clientId === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
                <select name="contract_id" class="form-select">
                    <option value="">All contracts</option>
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected($contractId === $contract->id)>{{ $contract->contract_no }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary">Search</button>
                <a href="{{ route('exports.master-data', ['type' => 'service-orders'] + request()->query()) }}" class="btn btn-outline-primary" data-loading-trigger data-loading-mode="download">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Sales Order</th>
                        <th>Contract</th>
                        <th>Locations</th>
                        <th>Team</th>
                        <th>Operation Executive</th>
                        <th>Period</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($serviceOrders as $serviceOrder)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $serviceOrder->order_no }}</div>
                                <div class="small text-muted">{{ optional($serviceOrder->requested_date)->format('d M Y') }}</div>
                            </td>
                            <td>
                                <div>{{ $serviceOrder->contract?->contract_no }}</div>
                                <div class="small text-muted">{{ $serviceOrder->contract?->client?->name }}</div>
                            </td>
                            <td>
                                @php($names = $serviceOrder->locations->pluck('name')->filter())
                                @if ($names->isEmpty())
                                    <span class="text-muted">N/A</span>
                                @else
                                    <span class="small">{{ $names->implode(', ') }}</span>
                                @endif
                            </td>
                            <td>{{ $serviceOrder->team?->name ?: 'Unassigned' }}</td>
                            <td>{{ $serviceOrder->operationExecutive?->name ?: 'Unassigned' }}</td>
                            <td>
                                <div>{{ optional($serviceOrder->period_start_date)->format('d M Y') }}</div>
                                <div class="small text-muted">to {{ optional($serviceOrder->period_end_date)->format('d M Y') }}</div>
                                <div class="small text-muted">Start day: {{ $serviceOrder->muster_start_day }}</div>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editServiceOrderModal-{{ $serviceOrder->id }}">Edit</button>
                                    <form method="POST" action="{{ route('service-orders.destroy', $serviceOrder) }}" onsubmit="return confirm('Delete this sales order?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No sales orders found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $serviceOrders->firstItem() ?? 0 }} to {{ $serviceOrders->lastItem() ?? 0 }} of {{ $serviceOrders->total() }} sales orders</p>
            {{ $serviceOrders->links() }}
        </x-slot:footer>
    </x-table>

    <div class="modal fade" id="createServiceOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('service-orders.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Sales Order</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @include('master-data.service-orders.partials.form', [
                            'modalKey' => 'createServiceOrderModal',
                            'clients' => $clients,
                            'contracts' => $contracts,
                            'locations' => $locations,
                            'teams' => $teams,
                            'operationsExecutives' => $operationsExecutives,
                            'statusOptions' => $statusOptions,
                            'serviceOrder' => null,
                        ])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Sales Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($serviceOrders as $serviceOrder)
        <div class="modal fade" id="editServiceOrderModal-{{ $serviceOrder->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('service-orders.update', $serviceOrder) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Sales Order</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @include('master-data.service-orders.partials.form', [
                                'modalKey' => 'editServiceOrderModal-' . $serviceOrder->id,
                                'clients' => $clients,
                                'contracts' => $contracts,
                                'locations' => $locations,
                                'teams' => $teams,
                                'operationsExecutives' => $operationsExecutives,
                                'statusOptions' => $statusOptions,
                                'serviceOrder' => $serviceOrder,
                            ])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Sales Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-contract-client-filter]').forEach((wrapper) => {
                const clientSelect = wrapper.querySelector('[data-client-select]');
                const contractSelect = wrapper.querySelector('[data-contract-select]');

                if (!clientSelect || !contractSelect) {
                    return;
                }

                const filterContracts = () => {
                    const clientId = clientSelect.value;

                    Array.from(contractSelect.options).forEach((option) => {
                        if (!option.value) {
                            option.hidden = false;
                            return;
                        }

                        const matchesClient = option.dataset.clientId === clientId;
                        option.hidden = !!clientId && !matchesClient;

                        if (option.hidden && option.selected) {
                            option.selected = false;
                        }
                    });
                };

                clientSelect.addEventListener('change', filterContracts);
                filterContracts();
            });
        });
    </script>
@endsection
