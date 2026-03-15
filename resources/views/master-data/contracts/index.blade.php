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
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContractModal" @disabled($clients->isEmpty() || $locations->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Contract
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @include('master-data.import-report', ['type' => 'contracts'])

    @if ($clients->isEmpty() || $locations->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add active clients and locations before creating contracts.</div>
    @endif

    <x-table title="Contract Register" description="Maintain active agreements, lifecycle dates, values, and linked sites." :loading="true" :columns="9" :rows="5">
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
                                <th>Location</th>
                                <th>Covered Sites</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Orders</th>
                        <th class="text-end">Value</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contracts as $contract)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $contract->contract_no }}</div>
                                <div class="small text-muted">{{ $contract->scope ? \Illuminate\Support\Str::limit($contract->scope, 40) : 'No scope added' }}</div>
                            </td>
                            <td>{{ $contract->client?->name }}</td>
                            <td>{{ $contract->location?->name }}</td>
                            <td>{{ $contract->locations_count }}</td>
                            <td>{{ optional($contract->start_date)->format('d M Y') }}{{ $contract->end_date ? ' - '.optional($contract->end_date)->format('d M Y') : '' }}</td>
                            <td><span class="badge text-bg-light border">{{ $contract->status }}</span></td>
                            <td>{{ $contract->service_orders_count }}</td>
                            <td class="text-end">{{ $contract->contract_value !== null ? number_format((float) $contract->contract_value, 2) : 'N/A' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
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
                            <td colspan="9" class="text-center py-5 text-muted">No contracts found for the current search.</td>
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
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('contracts.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Contract</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Client</label>
                                <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'createContractModal') is-invalid @endif" required>
                                    <option value="">Select client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Location</label>
                                <select name="location_id" class="form-select @if($errors->has('location_id') && session('open_modal') === 'createContractModal') is-invalid @endif" required>
                                    <option value="">Select location</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Covered Locations</label>
                                <select name="location_ids[]" class="form-select" multiple size="6">
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(collect(old('location_ids', old('location_id') ? [old('location_id')] : []))->contains($location->id))>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Select all locations covered by the contract. The primary location above will always be included.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contract No</label>
                                <input type="text" name="contract_no" class="form-control @if($errors->has('contract_no') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('contract_no') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control @if($errors->has('start_date') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('start_date') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control @if($errors->has('end_date') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('end_date') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Contract Value</label>
                                <input type="number" step="0.01" min="0" name="contract_value" class="form-control @if($errors->has('contract_value') && session('open_modal') === 'createContractModal') is-invalid @endif" value="{{ old('contract_value') }}">
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
        <div class="modal fade" id="editContractModal-{{ $contract->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
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
                                <div class="col-md-4">
                                    <label class="form-label">Client</label>
                                    <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" required>
                                        <option value="">Select client</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected(old('client_id', $contract->client_id) == $client->id)>{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <select name="location_id" class="form-select @if($errors->has('location_id') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" required>
                                        <option value="">Select location</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected(old('location_id', $contract->location_id) == $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Covered Locations</label>
                                    <select name="location_ids[]" class="form-select" multiple size="6">
                                        @php
                                            $selectedLocationIds = collect(session('open_modal') === 'editContractModal-' . $contract->id ? old('location_ids', $contract->locations->pluck('id')->all()) : $contract->locations->pluck('id')->all());
                                        @endphp
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected($selectedLocationIds->contains($location->id))>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Use this list to include all sites covered by the contract for bulk receive processing.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contract No</label>
                                    <input type="text" name="contract_no" class="form-control @if($errors->has('contract_no') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('contract_no', $contract->contract_no) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control @if($errors->has('start_date') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('start_date', optional($contract->start_date)->format('Y-m-d')) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control @if($errors->has('end_date') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('end_date', optional($contract->end_date)->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Contract Value</label>
                                    <input type="number" step="0.01" min="0" name="contract_value" class="form-control @if($errors->has('contract_value') && session('open_modal') === 'editContractModal-' . $contract->id) is-invalid @endif" value="{{ old('contract_value', $contract->contract_value) }}">
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
