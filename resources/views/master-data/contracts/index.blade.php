@extends('layouts.app')

@section('title', 'Contracts | Tan-MC')

@section('content')
    <x-page-header
        title="Contracts"
        subtitle="Agreement register for lifecycle, coverage, and service-order linkage."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Contracts'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                @include('master-data.import-controls', ['type' => 'contracts', 'label' => 'Contracts', 'modalId' => 'contractsImportModal'])
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContractModal" @disabled($clients->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Contract
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @include('master-data.import-report', ['type' => 'contracts'])

    @if ($clients->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add active clients before creating contracts.</div>
    @endif

    <x-table title="Contract Register" description="Maintain active agreements and lifecycle dates in a compact format." :loading="true" :columns="6" :rows="5">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('contracts.index') }}" class="d-flex flex-wrap gap-2" data-loading-form>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search contracts">
                </div>
                <select name="client_id" class="form-select">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($clientId === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($statusOptions as $statusOption)
                        <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary">Search</button>
                <a href="{{ route('exports.master-data', ['type' => 'contracts'] + request()->query()) }}" class="btn btn-outline-primary" data-loading-trigger>
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Contract</th>
                                <th>Client</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Orders</th>
                                <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $contract)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $contract->contract_no }}</div>
                                <div class="small text-muted">{{ $contract->contract_name ?: 'No contract name' }}</div>
                            </td>
                            <td>{{ $contract->client?->name }}</td>
                            <td>{{ optional($contract->start_date)->format('d M Y') }}{{ $contract->end_date ? ' - '.optional($contract->end_date)->format('d M Y') : '' }}</td>
                            <td><span class="badge text-bg-light border">{{ $contract->status }}</span></td>
                            <td>{{ $contract->service_orders_count }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewContractModal-{{ $contract->id }}">View</button>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editContractModal-{{ $contract->id }}">Edit</button>
                                    <form method="POST" action="{{ route('contracts.destroy', $contract) }}" onsubmit="return confirm('Delete this contract?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No contracts found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $contracts->firstItem() ?? 0 }} to {{ $contracts->lastItem() ?? 0 }} of {{ $contracts->total() }} contracts</p>
            {{ $contracts->links() }}
        </x-slot:footer>
    </x-table>

    <div class="modal fade" id="createContractModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('contracts.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Contract</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Client</label>
                                <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'createContractModal') is-invalid @endif" required>
                                    <option value="">Select client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contract No</label>
                                <input type="text" name="contract_no" class="form-control @if($errors->has('contract_no') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('contract_no') }}" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Contract Name</label>
                                <input type="text" name="contract_name" class="form-control @if($errors->has('contract_name') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('contract_name') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control @if($errors->has('start_date') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('start_date') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Deactivate Date</label>
                                <input type="date" name="end_date" class="form-control @if($errors->has('end_date') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('end_date') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === 'createContractModal') is-invalid @endif" required>
                                    @foreach ($statusOptions as $status)
                                        <option value="{{ $status }}" @selected(old('status', 'Active') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Scope</label>
                                <textarea name="scope" class="form-control @if($errors->has('scope') && session('open_modal') === 'createContractModal') is-invalid @endif" rows="4">{{ old('scope') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Contract</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($contracts as $contract)
        <div class="modal fade" id="viewContractModal-{{ $contract->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Contract Details</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><strong>Contract No:</strong> {{ $contract->contract_no }}</div>
                            <div class="col-md-6"><strong>Contract Name:</strong> {{ $contract->contract_name ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Client:</strong> {{ $contract->client?->name ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Start Date:</strong> {{ optional($contract->start_date)->format('d M Y') ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Deactivate Date:</strong> {{ optional($contract->end_date)->format('d M Y') ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Status:</strong> {{ $contract->status }}</div>
                            <div class="col-md-6"><strong>Service Orders:</strong> {{ $contract->service_orders_count }}</div>
                            <div class="col-12"><strong>Scope:</strong> {{ $contract->scope ?: 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="editContractModal-{{ $contract->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('contracts.update', $contract) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Contract</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Client</label>
                                    <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" required>
                                        <option value="">Select client</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected(old('client_id', $contract->client_id) == $client->id)>{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contract No</label>
                                    <input type="text" name="contract_no" class="form-control @if($errors->has('contract_no') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('contract_no', $contract->contract_no) }}" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Contract Name</label>
                                    <input type="text" name="contract_name" class="form-control @if($errors->has('contract_name') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('contract_name', $contract->contract_name) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control @if($errors->has('start_date') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('start_date', optional($contract->start_date)->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Deactivate Date</label>
                                    <input type="date" name="end_date" class="form-control @if($errors->has('end_date') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('end_date', optional($contract->end_date)->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" required>
                                        @foreach ($statusOptions as $status)
                                            <option value="{{ $status }}" @selected(old('status', $contract->status) === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Scope</label>
                                    <textarea name="scope" class="form-control @if($errors->has('scope') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" rows="4">{{ old('scope', $contract->scope) }}</textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Contract</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
