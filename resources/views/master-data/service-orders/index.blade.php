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
                <div class="d-flex flex-wrap gap-2">
                    @include('master-data.import-controls', ['type' => 'service-orders', 'label' => 'Sales Orders', 'modalId' => 'serviceOrdersImportModal'])
                    @include('master-data.import-controls', ['type' => 'service-order-locations', 'label' => 'Sales Order Locations', 'modalId' => 'serviceOrderLocationsImportModal'])
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createServiceOrderModal" @disabled($contracts->isEmpty() || $states->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Sales Order
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @include('master-data.import-report', ['type' => 'service-orders'])

    @if ($contracts->isEmpty() || $states->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add contracts, mapped locations, and states before creating sales orders.</div>
    @endif

    <x-table :loading="true" :columns="9" :rows="5">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('service-orders.index') }}" class="service-order-filter-form" data-loading-form>
                <div class="service-order-toolbar-shell">
                    <div class="service-order-toolbar-fields">
                        <div class="input-group service-order-filter-search">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search SO no, SO name, client, contract">
                        </div>
                        <div class="service-order-toolbar-grid">
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
                            <select name="location_id" class="form-select">
                                <option value="">All locations</option>
                                @foreach ($locationFilterOptions as $locationOption)
                                    <option value="{{ $locationOption->id }}" @selected($locationId === $locationOption->id)>{{ $locationOption->name }}</option>
                                @endforeach
                            </select>
                            <select name="status" class="form-select">
                                <option value="">All statuses</option>
                                @foreach ($statusOptions as $statusOption)
                                    <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $statusOption }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="service-order-toolbar-actions">
                        <button class="btn btn-outline-secondary">Search</button>
                        <a href="{{ route('exports.master-data', ['type' => 'service-orders'] + request()->query()) }}" class="btn btn-outline-primary" data-loading-trigger data-loading-mode="download">
                            <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                        </a>
                    </div>
                </div>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Sales Order No</th>
                        <th>SO Name</th>
                        <th>Client</th>
                        <th>Contract</th>
                        <th>State</th>
                        <th>Active Locations</th>
                        <th>SO Start Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($serviceOrders as $serviceOrder)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $serviceOrder->order_no }}</div>
                            </td>
                            <td>{{ $serviceOrder->so_name ?: 'N/A' }}</td>
                            <td>
                                <div>{{ $serviceOrder->contract?->client?->name ?: 'N/A' }}</div>
                                @if($serviceOrder->contract?->client?->code)
                                    <div class="small text-muted">{{ $serviceOrder->contract->client->code }}</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $serviceOrder->contract?->contract_no }}</div>
                            </td>
                            <td>
                                <div>{{ $serviceOrder->state?->name ?: ($serviceOrder->locations->first()?->state?->name ?? 'N/A') }}</div>
                            </td>
                            <td>
                                <span class="fw-semibold">{{ $serviceOrder->active_locations_count }}</span>
                            </td>
                            <td>{{ optional($serviceOrder->requested_date)->format('d M Y') }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $serviceOrder->display_status === 'Terminate' ? 'text-bg-danger-subtle text-danger' : 'text-bg-success-subtle text-success' }} service-order-status-badge">
                                    {{ $serviceOrder->display_status }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-nowrap justify-content-end gap-1 service-order-action-group">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewServiceOrderModal-{{ $serviceOrder->id }}">View</button>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editServiceOrderModal-{{ $serviceOrder->id }}">Update</button>
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#serviceOrderLocationModal-{{ $serviceOrder->id }}">{{ $serviceOrder->active_locations_count > 0 ? 'Update Location' : 'Add Location' }}</button>
                                    @if ($serviceOrder->display_status !== 'Terminate')
                                        <form method="POST" action="{{ route('service-orders.terminate', $serviceOrder) }}" onsubmit="return confirm('Terminate this sales order?');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-warning">Terminate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">No sales orders found for the current search.</td>
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
        <div class="modal-dialog modal-xl modal-dialog-scrollable service-order-modal-dialog">
            <div class="modal-content border-0 shadow-lg service-order-modal-content">
                <form method="POST" action="{{ route('service-orders.store') }}">
                    @csrf
                    <div class="modal-header service-order-modal-header">
                        <h2 class="modal-title h5 mb-0">Add Sales Order</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body service-order-modal-body">
                        @include('master-data.service-orders.partials.form', [
                            'modalKey' => 'createServiceOrderModal',
                            'clients' => $clients,
                            'contracts' => $contracts,
                            'states' => $states,
                            'statusOptions' => $statusOptions,
                            'serviceOrder' => null,
                        ])
                    </div>
                    <div class="modal-footer service-order-modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Sales Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($serviceOrders as $serviceOrder)
        <div class="modal fade" id="viewServiceOrderModal-{{ $serviceOrder->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Sales Order Details</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted">Sales Order</div>
                                <div class="fw-semibold">{{ $serviceOrder->order_no }}</div>
                                <div class="small text-muted">{{ $serviceOrder->so_name ?: 'No SO name' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">SO Start Date</div>
                                <div>{{ optional($serviceOrder->requested_date)->format('d M Y') }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Status</div>
                                <div>{{ $serviceOrder->display_status }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Client</div>
                                <div>{{ $serviceOrder->contract?->client?->name ?: 'N/A' }}</div>
                                @if ($serviceOrder->contract?->client?->code)
                                    <div class="small text-muted">{{ $serviceOrder->contract->client->code }}</div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Contract</div>
                                <div>{{ $serviceOrder->contract?->contract_no ?: 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">State</div>
                                <div>{{ $serviceOrder->state?->name ?: 'N/A' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="small text-muted">Locations</div>
                                <div class="small text-muted">{{ $serviceOrder->active_locations_count }} active location(s)</div>
                                <div>{{ $serviceOrder->locations->pluck('name')->filter()->implode(', ') ?: 'N/A' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="small text-muted">Remarks</div>
                                <div>{{ $serviceOrder->remarks ?: 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editServiceOrderModal-{{ $serviceOrder->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable service-order-modal-dialog">
                <div class="modal-content border-0 shadow-lg service-order-modal-content">
                    <form method="POST" action="{{ route('service-orders.update', $serviceOrder) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header service-order-modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Sales Order</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body service-order-modal-body">
                            @include('master-data.service-orders.partials.form', [
                                'modalKey' => 'editServiceOrderModal-' . $serviceOrder->id,
                                'clients' => $clients,
                                'contracts' => $contracts,
                                'states' => $states,
                                'statusOptions' => $statusOptions,
                                'serviceOrder' => $serviceOrder,
                            ])
                        </div>
                        <div class="modal-footer service-order-modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Sales Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="serviceOrderLocationModal-{{ $serviceOrder->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable service-order-location-modal-dialog">
                <div class="modal-content border-0 shadow-lg service-order-modal-content">
                    <form method="POST" action="{{ route('service-orders.locations.update', $serviceOrder) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header service-order-modal-header">
                            <div>
                                <h2 class="modal-title h5 mb-1">{{ $serviceOrder->active_locations_count > 0 ? 'Update Locations' : 'Add Locations' }}</h2>
                                <p class="text-muted small mb-0">{{ $serviceOrder->order_no }}{{ $serviceOrder->so_name ? ' | ' . $serviceOrder->so_name : '' }}</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body service-order-modal-body">
                            @include('master-data.service-orders.partials.location-manager', [
                                'modalKey' => 'serviceOrderLocationModal-' . $serviceOrder->id,
                                'serviceOrder' => $serviceOrder,
                                'operationsExecutives' => $operationsExecutives,
                            ])
                        </div>
                        <div class="modal-footer service-order-modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Locations</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    @push('styles')
        <style>
            .service-order-modal-dialog {
                max-width: min(1080px, calc(100vw - 1rem));
                height: calc(100vh - 1.25rem);
                margin: 0.75rem auto;
            }

            .service-order-location-modal-dialog {
                max-width: min(1220px, calc(100vw - 1rem));
                height: calc(100vh - 1.25rem);
                margin: 0.75rem auto;
            }

            .service-order-modal-content {
                max-height: calc(100vh - 1.25rem);
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .service-order-modal-header,
            .service-order-modal-footer {
                flex-shrink: 0;
            }

            .service-order-modal-body {
                overflow-y: auto;
                padding: 0.8rem 0.9rem;
            }

            .service-order-filter-form {
                width: 100%;
            }

            .service-order-toolbar-shell {
                display: grid;
                grid-template-columns: minmax(0, 1.8fr) auto;
                gap: 0.75rem;
                width: 100%;
                align-items: start;
            }

            .service-order-toolbar-fields {
                display: flex;
                flex-direction: column;
                gap: 0.65rem;
            }

            .service-order-location-search {
                flex: 1 1 320px;
                min-width: 240px;
            }

            .service-order-location-panel {
                display: flex;
                flex-direction: column;
                min-height: 0;
                overflow: hidden;
            }

            .service-order-location-results {
                max-height: min(54vh, 520px);
                overflow-y: auto;
                overflow-x: auto;
            }

            .service-order-location-table {
                width: 100%;
                margin-bottom: 0;
            }

            .service-order-location-table th,
            .service-order-location-table td {
                padding: 0.5rem 0.6rem;
                vertical-align: middle;
            }

            .service-order-toolbar-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 0.55rem;
            }

            .service-order-toolbar-actions {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 0.5rem;
                align-items: center;
            }

            .service-order-status-badge {
                min-width: 84px;
                padding: 0.34rem 0.62rem;
                font-weight: 600;
            }

            .service-order-action-group form {
                margin: 0;
            }

            .service-order-action-group .btn {
                white-space: nowrap;
            }

            .service-order-form .form-label {
                margin-bottom: 0.28rem;
                font-size: 0.79rem;
                font-weight: 600;
            }

            .service-order-form .form-control,
            .service-order-form .form-select {
                min-height: 38px;
            }

            .service-order-form .row {
                --bs-gutter-y: 0.6rem;
            }

            .service-order-filter-search .form-control,
            .service-order-filter-search .input-group-text,
            .service-order-toolbar-grid .form-select {
                min-height: 40px;
            }

            .service-order-location-note {
                border-style: dashed;
            }

            .service-order-location-context {
                min-width: 220px;
            }

            .table.align-middle > :not(caption) > * > * {
                padding-top: 0.52rem;
                padding-bottom: 0.52rem;
            }

            .table.align-middle tbody td {
                white-space: nowrap;
            }

            .table.align-middle tbody td:nth-child(2),
            .table.align-middle tbody td:nth-child(3),
            .table.align-middle tbody td:nth-child(4),
            .table.align-middle tbody td:nth-child(5) {
                max-width: 180px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            @media (max-width: 991.98px) {
                .service-order-toolbar-shell,
                .service-order-toolbar-grid {
                    grid-template-columns: 1fr;
                }

                .service-order-modal-dialog {
                    max-width: calc(100vw - 1rem);
                    height: calc(100vh - 1rem);
                    margin: 0.5rem auto;
                }

                .service-order-location-modal-dialog {
                    max-width: calc(100vw - 1rem);
                    height: calc(100vh - 1rem);
                    margin: 0.5rem auto;
                }

                .service-order-toolbar-actions {
                    justify-content: stretch;
                }

                .service-order-toolbar-actions .btn {
                    flex: 1 1 100%;
                }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const debounce = (callback, wait) => {
                    let timer = null;

                    return (...args) => {
                        window.clearTimeout(timer);
                        timer = window.setTimeout(() => callback(...args), wait);
                    };
                };

                const normalizeDate = (value) => value || null;

                document.querySelectorAll('[data-basic-service-order-form]').forEach((wrapper) => {
                    const clientSelect = wrapper.querySelector('[data-client-select]');
                    const contractSelect = wrapper.querySelector('[data-contract-select]');
                    const stateSelect = wrapper.querySelector('[data-state-select]');
                    const filterContracts = () => {
                        const clientId = clientSelect.value;

                        Array.from(contractSelect.options).forEach((option) => {
                            if (!option.value) {
                                option.hidden = false;
                                option.disabled = false;
                                return;
                            }

                            const matchesClient = option.dataset.clientId === clientId;
                            option.hidden = !!clientId && !matchesClient;
                            option.disabled = !!clientId && !matchesClient;

                            if (option.hidden && option.selected) {
                                option.selected = false;
                            }
                        });
                    };

                    const filterStates = () => {
                        const clientId = clientSelect.value;

                        Array.from(stateSelect.options).forEach((option) => {
                            if (!option.value) {
                                option.hidden = false;
                                option.disabled = false;
                                return;
                            }

                            const clientIds = (option.dataset.clientIds || '')
                                .split(',')
                                .map((value) => value.trim())
                                .filter(Boolean);
                            const matchesClient = clientId === '' || clientIds.includes(clientId);
                            option.hidden = !matchesClient;
                            option.disabled = !matchesClient;

                            if (option.hidden && option.selected) {
                                option.selected = false;
                            }
                        });
                    };

                    clientSelect.addEventListener('change', () => {
                        filterContracts();
                        filterStates();

                        if (contractSelect.selectedOptions[0]?.hidden) {
                            contractSelect.value = '';
                        }

                        if (stateSelect.selectedOptions[0]?.hidden) {
                            stateSelect.value = '';
                        }
                    });

                    filterContracts();
                    filterStates();
                });

                document.querySelectorAll('[data-service-order-location-form]').forEach((wrapper) => {
                    const searchInput = wrapper.querySelector('[data-location-search]');
                    const results = wrapper.querySelector('[data-location-results]');
                    const meta = wrapper.querySelector('[data-location-meta]');
                    const summary = wrapper.querySelector('[data-location-summary]');
                    const hiddenInputs = wrapper.querySelector('[data-location-hidden-inputs]');
                    const loadMoreButton = wrapper.querySelector('[data-location-load-more]');
                    const selectAllCheckbox = wrapper.querySelector('[data-location-select-all]');
                    const bulkExecutive = wrapper.querySelector('[data-bulk-executive]');
                    const bulkMusterDue = wrapper.querySelector('[data-bulk-muster-due]');
                    const bulkApplyButton = wrapper.querySelector('[data-apply-bulk-assignments]');
                    const endpoint = wrapper.dataset.locationsEndpoint;
                    const clientId = wrapper.dataset.clientId;
                    const stateId = wrapper.dataset.stateId;
                    const defaultStartDate = wrapper.dataset.defaultStartDate || '';
                    const initialSelected = JSON.parse(wrapper.dataset.initialSelected || '[]');
                    const initialRemoved = JSON.parse(wrapper.dataset.initialRemoved || '[]');
                    const executives = JSON.parse(wrapper.dataset.executives || '[]');
                    const selectedLocations = new Map();
                    const removedLocations = new Map();
                    let currentPage = 1;
                    let lastPage = 1;
                    let currentItems = [];
                    let searchController = null;

                    const upsertSelectedLocation = (item) => {
                        selectedLocations.set(Number(item.id), {
                            id: Number(item.id),
                            name: item.name,
                            code: item.code || '',
                            city: item.city || '',
                            state_id: item.state_id || null,
                            start_date: normalizeDate(item.start_date) || defaultStartDate || '',
                            end_date: normalizeDate(item.end_date),
                            operation_executive_id: item.operation_executive_id ? Number(item.operation_executive_id) : null,
                            muster_due_days: Number(item.muster_due_days ?? 0),
                        });
                    };

                    initialSelected.forEach((item) => upsertSelectedLocation(item));
                    initialRemoved.forEach((item) => {
                        const locationId = Number(item.id);

                        if (locationId > 0) {
                            removedLocations.set(locationId, {
                                id: locationId,
                                end_date: normalizeDate(item.end_date) || defaultStartDate || '',
                            });
                        }
                    });

                    const syncSelectionState = () => {
                        const visibleLocationIds = currentItems.map((item) => Number(item.id));
                        const selectedVisibleCount = visibleLocationIds.filter((locationId) => selectedLocations.has(locationId)).length;

                        selectAllCheckbox.checked = visibleLocationIds.length > 0 && selectedVisibleCount === visibleLocationIds.length;
                        selectAllCheckbox.indeterminate = selectedVisibleCount > 0 && selectedVisibleCount < visibleLocationIds.length;

                        currentItems.forEach((item) => {
                            const locationId = Number(item.id);
                            const selected = selectedLocations.get(locationId);
                            const removed = removedLocations.get(locationId);
                            const checkbox = results.querySelector(`[data-location-checkbox][value="${locationId}"]`);
                            const startInput = results.querySelector(`[data-location-start="${locationId}"]`);
                            const endInput = results.querySelector(`[data-location-end="${locationId}"]`);
                            const executiveInput = results.querySelector(`[data-location-executive="${locationId}"]`);
                            const musterDueInput = results.querySelector(`[data-location-muster-due="${locationId}"]`);

                            if (checkbox) {
                                checkbox.checked = !!selected;
                            }

                            if (startInput) {
                                startInput.value = selected?.start_date || defaultStartDate || '';
                            }

                            if (endInput) {
                                endInput.value = selected?.end_date || removed?.end_date || '';
                            }

                            if (executiveInput) {
                                executiveInput.value = String(selected?.operation_executive_id || '');
                            }

                            if (musterDueInput) {
                                musterDueInput.value = Number(selected?.muster_due_days ?? 0);
                            }
                        });
                    };

                    const syncHiddenInputs = () => {
                        hiddenInputs.innerHTML = '';

                        selectedLocations.forEach((item) => {
                            const locationInput = document.createElement('input');
                            locationInput.type = 'hidden';
                            locationInput.name = 'location_ids[]';
                            locationInput.value = item.id;

                            const startInput = document.createElement('input');
                            startInput.type = 'hidden';
                            startInput.name = `location_start_dates[${item.id}]`;
                            startInput.value = item.start_date || '';

                            const endInput = document.createElement('input');
                            endInput.type = 'hidden';
                            endInput.name = `location_end_dates[${item.id}]`;
                            endInput.value = item.end_date || '';

                            const executiveInput = document.createElement('input');
                            executiveInput.type = 'hidden';
                            executiveInput.name = `location_operation_executive_ids[${item.id}]`;
                            executiveInput.value = item.operation_executive_id || '';

                            const musterDueInput = document.createElement('input');
                            musterDueInput.type = 'hidden';
                            musterDueInput.name = `location_muster_due_days[${item.id}]`;
                            musterDueInput.value = Number(item.muster_due_days ?? 0);

                            hiddenInputs.appendChild(locationInput);
                            hiddenInputs.appendChild(startInput);
                            hiddenInputs.appendChild(endInput);
                            hiddenInputs.appendChild(executiveInput);
                            hiddenInputs.appendChild(musterDueInput);
                        });

                        removedLocations.forEach((item) => {
                            const removedInput = document.createElement('input');
                            removedInput.type = 'hidden';
                            removedInput.name = 'removed_location_ids[]';
                            removedInput.value = item.id;

                            const removedEndInput = document.createElement('input');
                            removedEndInput.type = 'hidden';
                            removedEndInput.name = `removed_location_end_dates[${item.id}]`;
                            removedEndInput.value = item.end_date || '';

                            hiddenInputs.appendChild(removedInput);
                            hiddenInputs.appendChild(removedEndInput);
                        });

                        summary.textContent = selectedLocations.size === 0
                            ? 'No locations selected.'
                            : `${selectedLocations.size} location${selectedLocations.size === 1 ? '' : 's'} selected`;

                        syncSelectionState();
                    };

                    const renderRows = () => {
                        if (currentItems.length === 0) {
                            results.innerHTML = '<div class="p-4 text-muted small">No locations found for this state.</div>';
                            return;
                        }

                        const executiveOptions = ['<option value="">Select executive</option>']
                            .concat(executives.map((executive) => `<option value="${executive.id}">${executive.name} (${executive.employee_code})</option>`))
                            .join('');

                        const rows = currentItems.map((item) => {
                            const selected = selectedLocations.get(Number(item.id));
                            const startDate = selected?.start_date || defaultStartDate || '';
                            const endDate = selected?.end_date || '';
                            const executiveId = selected?.operation_executive_id || '';
                            const musterDueDays = Number(selected?.muster_due_days ?? 0);

                            return `
                                <tr data-location-row="${item.id}">
                                    <td style="width: 60px;">
                                        <input type="checkbox" class="form-check-input" data-location-checkbox value="${item.id}" ${selected ? 'checked' : ''}>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">${item.name}</div>
                                        <div class="small text-muted">${item.code || ''}${item.city ? ' / ' + item.city : ''}</div>
                                    </td>
                                    <td style="width: 200px;">
                                        <input type="date" class="form-control form-control-sm" data-location-start="${item.id}" value="${startDate}">
                                    </td>
                                    <td style="width: 200px;">
                                        <input type="date" class="form-control form-control-sm" data-location-end="${item.id}" value="${endDate}">
                                    </td>
                                    <td style="width: 250px;">
                                        <select class="form-select form-select-sm" data-location-executive="${item.id}">
                                            ${executiveOptions}
                                        </select>
                                    </td>
                                    <td style="width: 130px;">
                                        <input type="number" min="0" max="15" class="form-control form-control-sm" data-location-muster-due="${item.id}" value="${musterDueDays}">
                                    </td>
                                </tr>
                            `;
                        }).join('');

                        results.innerHTML = `
                            <table class="table table-sm align-middle service-order-location-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 60px;">Use</th>
                                        <th>Location</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Operation Executive</th>
                                        <th>Muster Due Days</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        `;

                        syncSelectionState();

                        results.querySelectorAll('[data-location-checkbox]').forEach((checkbox) => {
                            checkbox.addEventListener('change', (event) => {
                                const locationId = Number(event.target.value);
                                const item = currentItems.find((entry) => Number(entry.id) === locationId);
                                const startInput = results.querySelector(`[data-location-start="${locationId}"]`);
                                const endInput = results.querySelector(`[data-location-end="${locationId}"]`);
                                const executiveInput = results.querySelector(`[data-location-executive="${locationId}"]`);
                                const musterDueInput = results.querySelector(`[data-location-muster-due="${locationId}"]`);

                                if (event.target.checked) {
                                    removedLocations.delete(locationId);
                                    upsertSelectedLocation({
                                        ...item,
                                        start_date: startInput.value || defaultStartDate || '',
                                        end_date: endInput.value || null,
                                        operation_executive_id: executiveInput.value ? Number(executiveInput.value) : null,
                                        muster_due_days: Number(musterDueInput.value || 0),
                                    });
                                } else {
                                    if (selectedLocations.has(locationId)) {
                                        removedLocations.set(locationId, {
                                            id: locationId,
                                            end_date: endInput.value || defaultStartDate || '',
                                        });
                                    }

                                    selectedLocations.delete(locationId);
                                }

                                syncHiddenInputs();
                            });
                        });

                        results.querySelectorAll('[data-location-start]').forEach((input) => {
                            input.addEventListener('change', (event) => {
                                const locationId = Number(event.target.dataset.locationStart);
                                const selected = selectedLocations.get(locationId);

                                if (selected) {
                                    selected.start_date = event.target.value || defaultStartDate || '';
                                    syncHiddenInputs();
                                }
                            });
                        });

                        results.querySelectorAll('[data-location-end]').forEach((input) => {
                            input.addEventListener('change', (event) => {
                                const locationId = Number(event.target.dataset.locationEnd);
                                const selected = selectedLocations.get(locationId);

                                if (selected) {
                                    selected.end_date = event.target.value || null;
                                }

                                if (removedLocations.has(locationId)) {
                                    removedLocations.get(locationId).end_date = event.target.value || defaultStartDate || '';
                                }

                                syncHiddenInputs();
                            });
                        });

                        results.querySelectorAll('[data-location-executive]').forEach((input) => {
                            input.addEventListener('change', (event) => {
                                const locationId = Number(event.target.dataset.locationExecutive);
                                const selected = selectedLocations.get(locationId);

                                if (selected) {
                                    selected.operation_executive_id = event.target.value ? Number(event.target.value) : null;
                                    syncHiddenInputs();
                                }
                            });
                        });

                        results.querySelectorAll('[data-location-muster-due]').forEach((input) => {
                            input.addEventListener('change', (event) => {
                                const locationId = Number(event.target.dataset.locationMusterDue);
                                const selected = selectedLocations.get(locationId);

                                if (selected) {
                                    selected.muster_due_days = Number(event.target.value || 0);
                                    syncHiddenInputs();
                                }
                            });
                        });
                    };

                    const applyBulkAssignments = () => {
                        const executiveId = bulkExecutive.value ? Number(bulkExecutive.value) : null;
                        const musterDueDays = bulkMusterDue.value === '' ? null : Number(bulkMusterDue.value);

                        selectedLocations.forEach((selected) => {

                            if (bulkExecutive.value !== '') {
                                selected.operation_executive_id = executiveId;
                            }

                            if (musterDueDays !== null) {
                                selected.muster_due_days = musterDueDays;
                            }
                        });

                        syncHiddenInputs();
                    };

                    const fetchLocations = async (page = 1) => {
                        if (!clientId || !stateId) {
                            currentItems = [];
                            currentPage = 1;
                            lastPage = 1;
                            selectAllCheckbox.checked = false;
                            loadMoreButton.classList.add('d-none');
                            meta.textContent = 'Client and state are required before locations can load.';
                            renderRows();
                            return;
                        }

                        if (searchController) {
                            searchController.abort();
                        }

                        searchController = new AbortController();
                        const params = new URLSearchParams({
                            client_id: clientId,
                            state_id: stateId,
                            search: searchInput.value.trim(),
                            page: String(page),
                            per_page: '50',
                        });

                        const response = await fetch(`${endpoint}?${params.toString()}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: searchController.signal,
                        });

                        const payload = await response.json();
                        currentItems = page === 1 ? payload.data : currentItems.concat(payload.data);
                        currentPage = payload.meta.current_page;
                        lastPage = payload.meta.last_page;
                        meta.textContent = `${payload.meta.total} location${payload.meta.total === 1 ? '' : 's'} available, showing ${currentItems.length}`;
                        loadMoreButton.classList.toggle('d-none', currentPage >= lastPage);
                        renderRows();
                    };

                    const selectAllLocations = async (checked) => {
                        if (!checked) {
                            currentItems.forEach((item) => {
                                const locationId = Number(item.id);

                                if (selectedLocations.has(locationId)) {
                                    removedLocations.set(locationId, {
                                        id: locationId,
                                        end_date: defaultStartDate || '',
                                    });
                                }

                                selectedLocations.delete(locationId);
                            });

                            syncHiddenInputs();
                            renderRows();
                            return;
                        }

                        const params = new URLSearchParams({
                            client_id: clientId,
                            state_id: stateId,
                            search: searchInput.value.trim(),
                            all: '1',
                        });
                        const response = await fetch(`${endpoint}?${params.toString()}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const payload = await response.json();

                        payload.data.forEach((locationId) => {
                            const current = currentItems.find((item) => Number(item.id) === Number(locationId));
                            const existing = selectedLocations.get(Number(locationId));
                            upsertSelectedLocation({
                                ...(current || existing || { id: Number(locationId), name: `Location #${locationId}` }),
                                id: Number(locationId),
                                start_date: existing?.start_date || defaultStartDate || '',
                                end_date: existing?.end_date || null,
                            });
                            removedLocations.delete(Number(locationId));
                        });

                        syncHiddenInputs();
                        renderRows();
                    };

                    const debouncedFetch = debounce(() => fetchLocations(1).catch(() => {
                        meta.textContent = 'Unable to load locations right now.';
                    }), 300);

                    searchInput.addEventListener('input', debouncedFetch);
                    loadMoreButton.addEventListener('click', () => fetchLocations(currentPage + 1).catch(() => {
                        meta.textContent = 'Unable to load more locations right now.';
                    }));
                    bulkApplyButton.addEventListener('click', applyBulkAssignments);
                    selectAllCheckbox.addEventListener('change', (event) => {
                        selectAllLocations(event.target.checked).catch(() => {
                            meta.textContent = 'Unable to update location selection right now.';
                        });
                    });

                    syncHiddenInputs();
                    fetchLocations(1).catch(() => {
                        meta.textContent = 'Unable to load locations right now.';
                    });
                });
            });
        </script>
    @endpush
@endsection
