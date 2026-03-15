@extends('layouts.app')

@section('title', 'Service Orders | Tan-MC')

@section('content')
    <x-page-header
        title="Service Orders"
        subtitle="Dispatch-ready work queue with compact status, team, and scheduling visibility."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Service Orders'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                @include('master-data.import-controls', ['type' => 'service-orders', 'label' => 'Service Orders', 'modalId' => 'serviceOrdersImportModal'])
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createServiceOrderModal" @disabled($contracts->isEmpty() || $locations->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Service Order
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @include('master-data.import-report', ['type' => 'service-orders'])

    @if ($contracts->isEmpty() || $locations->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add contracts and locations before creating service orders.</div>
    @endif

    <x-table title="Service Order Queue" description="Track dispatch requests, scheduling, team assignment, and delivery status." :loading="true" :columns="8" :rows="5">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('service-orders.index') }}" class="d-flex flex-wrap gap-2" data-loading-form>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search service orders">
                </div>
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
                <a href="{{ route('exports.master-data', ['type' => 'service-orders'] + request()->query()) }}" class="btn btn-outline-primary" data-loading-trigger>
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Contract</th>
                        <th>Location</th>
                        <th>Team</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($serviceOrders as $serviceOrder)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $serviceOrder->order_no }}</div>
                                <div class="small text-muted">{{ $serviceOrder->priority }} priority</div>
                            </td>
                            <td>{{ $serviceOrder->contract?->contract_no }}</td>
                            <td>{{ $serviceOrder->location?->name }}</td>
                            <td>{{ $serviceOrder->team?->name ?: 'Unassigned' }}</td>
                            <td>
                                <div>{{ optional($serviceOrder->requested_date)->format('d M Y') }}</div>
                                <div class="small text-muted">{{ $serviceOrder->scheduled_date ? optional($serviceOrder->scheduled_date)->format('d M Y') : 'Not scheduled' }}</div>
                            </td>
                            <td><span class="badge text-bg-light border">{{ $serviceOrder->status }}</span></td>
                            <td class="text-end">{{ $serviceOrder->amount !== null ? number_format((float) $serviceOrder->amount, 2) : 'N/A' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editServiceOrderModal-{{ $serviceOrder->id }}">Edit</button>
                                    <form method="POST" action="{{ route('service-orders.destroy', $serviceOrder) }}" onsubmit="return confirm('Delete this service order?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No service orders found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $serviceOrders->firstItem() ?? 0 }} to {{ $serviceOrders->lastItem() ?? 0 }} of {{ $serviceOrders->total() }} service orders</p>
            {{ $serviceOrders->links() }}
        </x-slot:footer>
    </x-table>

    <div class="modal fade" id="createServiceOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('service-orders.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Service Order</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Contract</label>
                                <select name="contract_id" class="form-select @if($errors->has('contract_id') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" required>
                                    <option value="">Select contract</option>
                                    @foreach ($contracts as $contract)
                                        <option value="{{ $contract->id }}" @selected(old('contract_id') == $contract->id)>{{ $contract->contract_no }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Location</label>
                                <select name="location_id" class="form-select @if($errors->has('location_id') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" required>
                                    <option value="">Select location</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Assigned Team</label>
                                <select name="team_id" class="form-select @if($errors->has('team_id') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif">
                                    <option value="">Unassigned</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_id') == $team->id)>{{ $team->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Order No</label>
                                <input type="text" name="order_no" class="form-control @if($errors->has('order_no') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" value="{{ old('order_no') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Requested Date</label>
                                <input type="date" name="requested_date" class="form-control @if($errors->has('requested_date') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" value="{{ old('requested_date') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Scheduled Date</label>
                                <input type="date" name="scheduled_date" class="form-control @if($errors->has('scheduled_date') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" value="{{ old('scheduled_date') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Period Start</label>
                                <input type="date" name="period_start_date" class="form-control @if($errors->has('period_start_date') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" value="{{ old('period_start_date', old('requested_date')) }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Period End</label>
                                <input type="date" name="period_end_date" class="form-control @if($errors->has('period_end_date') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" value="{{ old('period_end_date', old('scheduled_date')) }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Muster Cycle</label>
                                <select name="muster_cycle_type" class="form-select @if($errors->has('muster_cycle_type') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" required>
                                    @foreach ($cycleTypeOptions as $cycleTypeOption)
                                        <option value="{{ $cycleTypeOption }}" @selected(old('muster_cycle_type', '1-last') === $cycleTypeOption)>{{ $cycleTypeOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Due Days</label>
                                <input type="number" min="0" max="15" name="muster_due_days" class="form-control @if($errors->has('muster_due_days') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" value="{{ old('muster_due_days', 0) }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" required>
                                    @foreach ($statusOptions as $status)
                                        <option value="{{ $status }}" @selected(old('status', 'Open') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select @if($errors->has('priority') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" required>
                                    @foreach ($priorityOptions as $priority)
                                        <option value="{{ $priority }}" @selected(old('priority', 'Medium') === $priority)>{{ $priority }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" min="0" name="amount" class="form-control @if($errors->has('amount') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" value="{{ old('amount') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Client</label>
                                <input type="text" class="form-control bg-light" value="Auto from contract" disabled>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="serviceOrderAutoMusterCreate" name="auto_generate_muster" value="1" @checked(session('open_modal') === 'createServiceOrderModal' ? old('auto_generate_muster') : true)>
                                    <label class="form-check-label" for="serviceOrderAutoMusterCreate">Auto generate muster cycles</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control @if($errors->has('remarks') && session('open_modal') === 'createServiceOrderModal') is-invalid @endif" rows="4">{{ old('remarks') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Service Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($serviceOrders as $serviceOrder)
        <div class="modal fade" id="editServiceOrderModal-{{ $serviceOrder->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('service-orders.update', $serviceOrder) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Service Order</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Contract</label>
                                    <select name="contract_id" class="form-select @if($errors->has('contract_id') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" required>
                                        <option value="">Select contract</option>
                                        @foreach ($contracts as $contract)
                                            <option value="{{ $contract->id }}" @selected(old('contract_id', $serviceOrder->contract_id) == $contract->id)>{{ $contract->contract_no }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <select name="location_id" class="form-select @if($errors->has('location_id') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" required>
                                        <option value="">Select location</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected(old('location_id', $serviceOrder->location_id) == $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Assigned Team</label>
                                    <select name="team_id" class="form-select @if($errors->has('team_id') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif">
                                        <option value="">Unassigned</option>
                                        @foreach ($teams as $team)
                                            <option value="{{ $team->id }}" @selected(old('team_id', $serviceOrder->team_id) == $team->id)>{{ $team->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Order No</label>
                                    <input type="text" name="order_no" class="form-control @if($errors->has('order_no') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" value="{{ old('order_no', $serviceOrder->order_no) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Requested Date</label>
                                    <input type="date" name="requested_date" class="form-control @if($errors->has('requested_date') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" value="{{ old('requested_date', optional($serviceOrder->requested_date)->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Scheduled Date</label>
                                    <input type="date" name="scheduled_date" class="form-control @if($errors->has('scheduled_date') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" value="{{ old('scheduled_date', optional($serviceOrder->scheduled_date)->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Period Start</label>
                                    <input type="date" name="period_start_date" class="form-control @if($errors->has('period_start_date') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" value="{{ old('period_start_date', optional($serviceOrder->period_start_date)->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Period End</label>
                                    <input type="date" name="period_end_date" class="form-control @if($errors->has('period_end_date') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" value="{{ old('period_end_date', optional($serviceOrder->period_end_date)->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Muster Cycle</label>
                                    <select name="muster_cycle_type" class="form-select @if($errors->has('muster_cycle_type') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" required>
                                        @foreach ($cycleTypeOptions as $cycleTypeOption)
                                            <option value="{{ $cycleTypeOption }}" @selected(old('muster_cycle_type', $serviceOrder->muster_cycle_type) === $cycleTypeOption)>{{ $cycleTypeOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Due Days</label>
                                    <input type="number" min="0" max="15" name="muster_due_days" class="form-control @if($errors->has('muster_due_days') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" value="{{ old('muster_due_days', $serviceOrder->muster_due_days) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" required>
                                        @foreach ($statusOptions as $status)
                                            <option value="{{ $status }}" @selected(old('status', $serviceOrder->status) === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select @if($errors->has('priority') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" required>
                                        @foreach ($priorityOptions as $priority)
                                            <option value="{{ $priority }}" @selected(old('priority', $serviceOrder->priority) === $priority)>{{ $priority }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Amount</label>
                                    <input type="number" step="0.01" min="0" name="amount" class="form-control @if($errors->has('amount') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" value="{{ old('amount', $serviceOrder->amount) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Client</label>
                                    <input type="text" class="form-control bg-light" value="{{ $serviceOrder->contract?->client?->name ?: 'Auto from contract' }}" disabled>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="serviceOrderAutoMuster-{{ $serviceOrder->id }}" name="auto_generate_muster" value="1" @checked(session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id ? old('auto_generate_muster') : $serviceOrder->auto_generate_muster)>
                                        <label class="form-check-label" for="serviceOrderAutoMuster-{{ $serviceOrder->id }}">Auto generate muster cycles</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control @if($errors->has('remarks') && session('open_modal') === 'editServiceOrderModal-' . $serviceOrder->id) is-invalid @endif" rows="4">{{ old('remarks', $serviceOrder->remarks) }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Service Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
