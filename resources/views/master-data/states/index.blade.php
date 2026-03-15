@extends('layouts.app')

@section('title', 'States | Tan-MC')

@section('content')
    <x-page-header
        title="States"
        subtitle="Reference geography used across coverage, operations, and compliance reporting."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'States'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStateModal">
                    <i class="bi bi-plus-circle me-2"></i>Add State
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <x-table title="State Coverage" description="Maintain the geographic states used across operation areas and reporting.">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('states.index') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search states">
                </div>
                <button class="btn btn-outline-secondary">Search</button>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>State</th>
                        <th>Code</th>
                        <th>Region</th>
                        <th>Operation Areas</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($states as $state)
                        <tr>
                            <td class="fw-semibold">{{ $state->name }}</td>
                            <td>{{ $state->code }}</td>
                            <td>{{ $state->region ?: 'N/A' }}</td>
                            <td>{{ $state->operation_areas_count }}</td>
                            <td>
                                <span class="badge {{ $state->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $state->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStateModal-{{ $state->id }}">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('states.destroy', $state) }}" onsubmit="return confirm('Delete this state?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No states found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $states->firstItem() ?? 0 }} to {{ $states->lastItem() ?? 0 }} of {{ $states->total() }} states</p>
            {{ $states->links() }}
        </x-slot:footer>
    </x-table>

    <div class="modal fade" id="createStateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('states.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add State</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">State Name</label>
                                <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'createStateModal') is-invalid @endif" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Code</label>
                                <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'createStateModal') is-invalid @endif" value="{{ old('code') }}" placeholder="MH" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Region</label>
                                <input type="text" name="region" class="form-control @if($errors->has('region') && session('open_modal') === 'createStateModal') is-invalid @endif" value="{{ old('region') }}" placeholder="West">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="stateActiveCreate" name="is_active" value="1" @checked(session('open_modal') === 'createStateModal' ? old('is_active') : true)>
                                    <label class="form-check-label" for="stateActiveCreate">Active state</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save State</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($states as $state)
        <div class="modal fade" id="editStateModal-{{ $state->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('states.update', $state) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit State</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">State Name</label>
                                    <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'editStateModal-' . $state->id) is-invalid @endif" value="{{ old('name', $state->name) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'editStateModal-' . $state->id) is-invalid @endif" value="{{ old('code', $state->code) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Region</label>
                                    <input type="text" name="region" class="form-control @if($errors->has('region') && session('open_modal') === 'editStateModal-' . $state->id) is-invalid @endif" value="{{ old('region', $state->region) }}">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="stateActive-{{ $state->id }}" name="is_active" value="1" @checked(session('open_modal') === 'editStateModal-' . $state->id ? old('is_active') : $state->is_active)>
                                        <label class="form-check-label" for="stateActive-{{ $state->id }}">Active state</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update State</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
    @if (session('open_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById(@json(session('open_modal')));

                if (modal) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush
