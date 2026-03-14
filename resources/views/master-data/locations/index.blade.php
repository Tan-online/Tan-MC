@extends('layouts.app')

@section('title', 'Locations | Tan-MC')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Locations</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a class="text-decoration-none" href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Locations</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @include('master-data.import-controls', ['type' => 'locations', 'label' => 'Locations', 'modalId' => 'locationsImportModal'])

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLocationModal" @disabled($clients->isEmpty() || $states->isEmpty() || $operationAreas->isEmpty())>
                <i class="bi bi-plus-circle me-2"></i>Add Location
            </button>
        </div>
    </div>

    @include('master-data.import-report', ['type' => 'locations'])

    @if ($clients->isEmpty() || $states->isEmpty() || $operationAreas->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add active clients, states, and operation areas before creating locations.</div>
    @endif

    <div class="surface-card p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Location Master</h2>
                <p class="text-muted mb-0">Track client sites, service coverage regions, and linked operational areas.</p>
            </div>

            <form method="GET" action="{{ route('locations.index') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search locations">
                </div>
                <button class="btn btn-outline-secondary">Search</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Location</th>
                        <th>Client</th>
                        <th>Coverage</th>
                        <th>Contracts</th>
                        <th>Orders</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $location->name }}</div>
                                <div class="small text-muted">{{ $location->city ?: 'No city' }}{{ $location->postal_code ? ' • '.$location->postal_code : '' }}</div>
                            </td>
                            <td>{{ $location->client?->name }}</td>
                            <td>
                                <div>{{ $location->state?->name }}</div>
                                <div class="small text-muted">{{ $location->operationArea?->name }}</div>
                            </td>
                            <td>{{ $location->contracts_count }}</td>
                            <td>{{ $location->service_orders_count }}</td>
                            <td>
                                <span class="badge {{ $location->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $location->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editLocationModal-{{ $location->id }}">Edit</button>
                                    <form method="POST" action="{{ route('locations.destroy', $location) }}" onsubmit="return confirm('Delete this location?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No locations found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <p class="text-muted small mb-0">Showing {{ $locations->firstItem() ?? 0 }} to {{ $locations->lastItem() ?? 0 }} of {{ $locations->total() }} locations</p>
            {{ $locations->links() }}
        </div>
    </div>

    <div class="modal fade" id="createLocationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('locations.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Location</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Client</label>
                                <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'createLocationModal') is-invalid @endif" required>
                                    <option value="">Select client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location Name</label>
                                <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'createLocationModal') is-invalid @endif" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">State</label>
                                <select name="state_id" class="form-select @if($errors->has('state_id') && session('open_modal') === 'createLocationModal') is-invalid @endif" required>
                                    <option value="">Select state</option>
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ $state->name }} ({{ $state->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Operation Area</label>
                                <select name="operation_area_id" class="form-select @if($errors->has('operation_area_id') && session('open_modal') === 'createLocationModal') is-invalid @endif" required>
                                    <option value="">Select area</option>
                                    @foreach ($operationAreas as $operationArea)
                                        <option value="{{ $operationArea->id }}" @selected(old('operation_area_id') == $operationArea->id)>{{ $operationArea->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control @if($errors->has('city') && session('open_modal') === 'createLocationModal') is-invalid @endif" value="{{ old('city') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control @if($errors->has('postal_code') && session('open_modal') === 'createLocationModal') is-invalid @endif" value="{{ old('postal_code') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control @if($errors->has('address') && session('open_modal') === 'createLocationModal') is-invalid @endif" rows="3">{{ old('address') }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="locationActiveCreate" name="is_active" value="1" @checked(session('open_modal') === 'createLocationModal' ? old('is_active') : true)>
                                    <label class="form-check-label" for="locationActiveCreate">Active location</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($locations as $location)
        <div class="modal fade" id="editLocationModal-{{ $location->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('locations.update', $location) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Location</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Client</label>
                                    <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'editLocationModal-' . $location->id) is-invalid @endif" required>
                                        <option value="">Select client</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected(old('client_id', $location->client_id) == $client->id)>{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Location Name</label>
                                    <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'editLocationModal-' . $location->id) is-invalid @endif" value="{{ old('name', $location->name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">State</label>
                                    <select name="state_id" class="form-select @if($errors->has('state_id') && session('open_modal') === 'editLocationModal-' . $location->id) is-invalid @endif" required>
                                        <option value="">Select state</option>
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}" @selected(old('state_id', $location->state_id) == $state->id)>{{ $state->name }} ({{ $state->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Operation Area</label>
                                    <select name="operation_area_id" class="form-select @if($errors->has('operation_area_id') && session('open_modal') === 'editLocationModal-' . $location->id) is-invalid @endif" required>
                                        <option value="">Select area</option>
                                        @foreach ($operationAreas as $operationArea)
                                            <option value="{{ $operationArea->id }}" @selected(old('operation_area_id', $location->operation_area_id) == $operationArea->id)>{{ $operationArea->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control @if($errors->has('city') && session('open_modal') === 'editLocationModal-' . $location->id) is-invalid @endif" value="{{ old('city', $location->city) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" name="postal_code" class="form-control @if($errors->has('postal_code') && session('open_modal') === 'editLocationModal-' . $location->id) is-invalid @endif" value="{{ old('postal_code', $location->postal_code) }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control @if($errors->has('address') && session('open_modal') === 'editLocationModal-' . $location->id) is-invalid @endif" rows="3">{{ old('address', $location->address) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="locationActive-{{ $location->id }}" name="is_active" value="1" @checked(session('open_modal') === 'editLocationModal-' . $location->id ? old('is_active') : $location->is_active)>
                                        <label class="form-check-label" for="locationActive-{{ $location->id }}">Active location</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Location</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
