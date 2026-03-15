@extends('layouts.app')

@section('title', 'Executive Mapping | Tan-MC')

@section('content')
    <x-page-header
        title="Executive Mapping"
        subtitle="Assign executive ownership across clients, locations, and operation areas."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Executive Mapping'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExecutiveMappingModal" @disabled($clients->isEmpty() || $operationAreas->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Mapping
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @if ($clients->isEmpty() || $operationAreas->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add active clients and operation areas before creating executive mappings.</div>
    @endif

    <div class="surface-card p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Executive Coverage</h2>
                <p class="text-muted mb-0">Map client-facing executives to locations and operation areas for visibility.</p>
            </div>

            <form method="GET" action="{{ route('executive-mappings.index') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search mappings">
                </div>
                <button class="btn btn-outline-secondary">Search</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Executive</th>
                        <th>Client</th>
                        <th>Location</th>
                        <th>Operation Area</th>
                        <th>Primary</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($executiveMappings as $mapping)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $mapping->executive_name }}</div>
                                <div class="small text-muted">{{ $mapping->designation ?: 'No designation' }}</div>
                            </td>
                            <td>{{ $mapping->client?->name }}</td>
                            <td>{{ $mapping->location?->name ?: 'All locations' }}</td>
                            <td>{{ $mapping->operationArea?->name }}</td>
                            <td>
                                <span class="badge {{ $mapping->is_primary ? 'text-bg-primary-subtle text-primary border border-primary-subtle' : 'text-bg-light border text-secondary' }}">
                                    {{ $mapping->is_primary ? 'Primary' : 'Secondary' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $mapping->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $mapping->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editExecutiveMappingModal-{{ $mapping->id }}">Edit</button>
                                    <form method="POST" action="{{ route('executive-mappings.destroy', $mapping) }}" onsubmit="return confirm('Delete this executive mapping?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No executive mappings found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <p class="text-muted small mb-0">Showing {{ $executiveMappings->firstItem() ?? 0 }} to {{ $executiveMappings->lastItem() ?? 0 }} of {{ $executiveMappings->total() }} mappings</p>
            {{ $executiveMappings->links() }}
        </div>
    </div>

    <div class="modal fade" id="createExecutiveMappingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('executive-mappings.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Executive Mapping</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Client</label>
                                <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif" required>
                                    <option value="">Select client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Location</label>
                                <select name="location_id" class="form-select @if($errors->has('location_id') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif">
                                    <option value="">All locations</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contract</label>
                                <select name="contract_id" class="form-select @if($errors->has('contract_id') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif">
                                    <option value="">All contracts</option>
                                    @foreach ($contracts as $contract)
                                        <option value="{{ $contract->id }}" @selected(old('contract_id') == $contract->id)>{{ $contract->contract_no }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Operation Area</label>
                                <select name="operation_area_id" class="form-select @if($errors->has('operation_area_id') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif" required>
                                    <option value="">Select area</option>
                                    @foreach ($operationAreas as $operationArea)
                                        <option value="{{ $operationArea->id }}" @selected(old('operation_area_id') == $operationArea->id)>{{ $operationArea->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Executive User</label>
                                <select name="executive_user_id" class="form-select @if($errors->has('executive_user_id') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif">
                                    <option value="">Manual entry</option>
                                    @foreach ($executiveUsers as $executiveUser)
                                        <option value="{{ $executiveUser->id }}" @selected(old('executive_user_id') == $executiveUser->id)>{{ $executiveUser->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Executive Name</label>
                                <input type="text" name="executive_name" class="form-control @if($errors->has('executive_name') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif" value="{{ old('executive_name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Designation</label>
                                <input type="text" name="designation" class="form-control @if($errors->has('designation') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif" value="{{ old('designation') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control @if($errors->has('email') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif" value="{{ old('email') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control @if($errors->has('phone') && session('open_modal') === 'createExecutiveMappingModal') is-invalid @endif" value="{{ old('phone') }}">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="mappingPrimaryCreate" name="is_primary" value="1" @checked(session('open_modal') === 'createExecutiveMappingModal' ? old('is_primary') : false)>
                                    <label class="form-check-label" for="mappingPrimaryCreate">Primary executive</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="mappingActiveCreate" name="is_active" value="1" @checked(session('open_modal') === 'createExecutiveMappingModal' ? old('is_active') : true)>
                                    <label class="form-check-label" for="mappingActiveCreate">Active mapping</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Mapping</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($executiveMappings as $mapping)
        <div class="modal fade" id="editExecutiveMappingModal-{{ $mapping->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('executive-mappings.update', $mapping) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Executive Mapping</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Client</label>
                                    <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif" required>
                                        <option value="">Select client</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected(old('client_id', $mapping->client_id) == $client->id)>{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <select name="location_id" class="form-select @if($errors->has('location_id') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif">
                                        <option value="">All locations</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected(old('location_id', $mapping->location_id) == $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Contract</label>
                                    <select name="contract_id" class="form-select @if($errors->has('contract_id') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif">
                                        <option value="">All contracts</option>
                                        @foreach ($contracts as $contract)
                                            <option value="{{ $contract->id }}" @selected(old('contract_id', $mapping->contract_id) == $contract->id)>{{ $contract->contract_no }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Operation Area</label>
                                    <select name="operation_area_id" class="form-select @if($errors->has('operation_area_id') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif" required>
                                        <option value="">Select area</option>
                                        @foreach ($operationAreas as $operationArea)
                                            <option value="{{ $operationArea->id }}" @selected(old('operation_area_id', $mapping->operation_area_id) == $operationArea->id)>{{ $operationArea->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Executive User</label>
                                    <select name="executive_user_id" class="form-select @if($errors->has('executive_user_id') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif">
                                        <option value="">Manual entry</option>
                                        @foreach ($executiveUsers as $executiveUser)
                                            <option value="{{ $executiveUser->id }}" @selected(old('executive_user_id', $mapping->executive_user_id) == $executiveUser->id)>{{ $executiveUser->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Executive Name</label>
                                    <input type="text" name="executive_name" class="form-control @if($errors->has('executive_name') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif" value="{{ old('executive_name', $mapping->executive_name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Designation</label>
                                    <input type="text" name="designation" class="form-control @if($errors->has('designation') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif" value="{{ old('designation', $mapping->designation) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control @if($errors->has('email') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif" value="{{ old('email', $mapping->email) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control @if($errors->has('phone') && session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id) is-invalid @endif" value="{{ old('phone', $mapping->phone) }}">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" role="switch" id="mappingPrimary-{{ $mapping->id }}" name="is_primary" value="1" @checked(session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id ? old('is_primary') : $mapping->is_primary)>
                                        <label class="form-check-label" for="mappingPrimary-{{ $mapping->id }}">Primary executive</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" role="switch" id="mappingActive-{{ $mapping->id }}" name="is_active" value="1" @checked(session('open_modal') === 'editExecutiveMappingModal-' . $mapping->id ? old('is_active') : $mapping->is_active)>
                                        <label class="form-check-label" for="mappingActive-{{ $mapping->id }}">Active mapping</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Mapping</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
