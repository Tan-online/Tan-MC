@extends('layouts.app')

@section('title', 'Operation Areas | Tan-MC')

@section('content')
    <x-page-header
        title="Operation Areas"
        subtitle="Coverage zones for field execution, dispatching, and reporting hierarchy."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Operation Areas'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOperationAreaModal" @disabled($states->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Operation Area
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @if ($states->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add at least one active state before creating operation areas.</div>
    @endif

    <x-table title="Area Master" description="Manage coverage areas and map them to the states used by the field teams.">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('operation-areas.index') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search operation areas">
                </div>
                <button class="btn btn-outline-secondary">Search</button>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>State</th>
                        <th>Description</th>
                        <th>Teams</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($operationAreas as $operationArea)
                        <tr>
                            <td class="fw-semibold">{{ $operationArea->name }}</td>
                            <td>{{ $operationArea->code ?: 'N/A' }}</td>
                            <td>{{ $operationArea->state?->name }} ({{ $operationArea->state?->code }})</td>
                            <td class="text-muted">{{ $operationArea->description ?: 'No description added.' }}</td>
                            <td>{{ $operationArea->teams_count }}</td>
                            <td>
                                <span class="badge {{ $operationArea->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $operationArea->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editOperationAreaModal-{{ $operationArea->id }}">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('operation-areas.destroy', $operationArea) }}" onsubmit="return confirm('Delete this operation area?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No operation areas found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $operationAreas->firstItem() ?? 0 }} to {{ $operationAreas->lastItem() ?? 0 }} of {{ $operationAreas->total() }} operation areas</p>
            {{ $operationAreas->links() }}
        </x-slot:footer>
    </x-table>

    <div class="modal fade" id="createOperationAreaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('operation-areas.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Operation Area</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Area Name</label>
                                <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'createOperationAreaModal') is-invalid @endif" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Code</label>
                                <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'createOperationAreaModal') is-invalid @endif" value="{{ old('code') }}" placeholder="MUM-W">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">State</label>
                                <select name="state_id" class="form-select @if($errors->has('state_id') && session('open_modal') === 'createOperationAreaModal') is-invalid @endif" required>
                                    <option value="">Select state</option>
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ $state->name }} ({{ $state->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control @if($errors->has('description') && session('open_modal') === 'createOperationAreaModal') is-invalid @endif" rows="4">{{ old('description') }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="operationAreaActiveCreate" name="is_active" value="1" @checked(session('open_modal') === 'createOperationAreaModal' ? old('is_active') : true)>
                                    <label class="form-check-label" for="operationAreaActiveCreate">Active operation area</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Operation Area</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($operationAreas as $operationArea)
        <div class="modal fade" id="editOperationAreaModal-{{ $operationArea->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('operation-areas.update', $operationArea) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Operation Area</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Area Name</label>
                                    <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'editOperationAreaModal-' . $operationArea->id) is-invalid @endif" value="{{ old('name', $operationArea->name) }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'editOperationAreaModal-' . $operationArea->id) is-invalid @endif" value="{{ old('code', $operationArea->code) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">State</label>
                                    <select name="state_id" class="form-select @if($errors->has('state_id') && session('open_modal') === 'editOperationAreaModal-' . $operationArea->id) is-invalid @endif" required>
                                        <option value="">Select state</option>
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}" @selected(old('state_id', $operationArea->state_id) == $state->id)>{{ $state->name }} ({{ $state->code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control @if($errors->has('description') && session('open_modal') === 'editOperationAreaModal-' . $operationArea->id) is-invalid @endif" rows="4">{{ old('description', $operationArea->description) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="operationAreaActive-{{ $operationArea->id }}" name="is_active" value="1" @checked(session('open_modal') === 'editOperationAreaModal-' . $operationArea->id ? old('is_active') : $operationArea->is_active)>
                                        <label class="form-check-label" for="operationAreaActive-{{ $operationArea->id }}">Active operation area</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Operation Area</button>
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
