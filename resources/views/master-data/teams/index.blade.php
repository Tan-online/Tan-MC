@extends('layouts.app')

@section('title', 'Teams | Tan-MC')

@section('content')
    <x-page-header
        title="Teams"
        subtitle="Compact field-team management for area ownership, leads, and active delivery coverage."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Teams'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTeamModal" @disabled($departments->isEmpty() || $operationAreas->isEmpty())>
                    <i class="bi bi-plus-circle me-2"></i>Add Team
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @if ($departments->isEmpty() || $operationAreas->isEmpty())
        <div class="alert alert-info border-0 shadow-sm">Add active departments and operation areas before creating teams.</div>
    @endif

    <x-table title="Field Teams" description="Manage delivery teams, their assignment areas, and on-ground ownership.">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('teams.index') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search teams">
                </div>
                <button class="btn btn-outline-secondary">Search</button>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Department</th>
                        <th>Operation Area</th>
                        <th>Lead</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teams as $team)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $team->name }}</div>
                                <div class="small text-muted">{{ $team->code ?: 'No code' }}</div>
                            </td>
                            <td>{{ $team->department?->name }}</td>
                            <td>
                                <div>{{ $team->operationArea?->name }}</div>
                                <div class="small text-muted">{{ $team->operationArea?->state?->name }}</div>
                            </td>
                            <td>{{ $team->lead_name ?: 'N/A' }}</td>
                            <td>{{ $team->members_count }}</td>
                            <td>
                                <span class="badge {{ $team->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $team->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTeamModal-{{ $team->id }}">
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('teams.destroy', $team) }}" onsubmit="return confirm('Delete this team?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No teams found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $teams->firstItem() ?? 0 }} to {{ $teams->lastItem() ?? 0 }} of {{ $teams->total() }} teams</p>
            {{ $teams->links() }}
        </x-slot:footer>
    </x-table>

    <div class="modal fade" id="createTeamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('teams.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Team</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Team Name</label>
                                <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'createTeamModal') is-invalid @endif" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Code</label>
                                <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'createTeamModal') is-invalid @endif" value="{{ old('code') }}" placeholder="TEAM-A">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-select @if($errors->has('department_id') && session('open_modal') === 'createTeamModal') is-invalid @endif" required>
                                    <option value="">Select department</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }} ({{ $department->code ?: 'No code' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Operation Area</label>
                                <select name="operation_area_id" class="form-select @if($errors->has('operation_area_id') && session('open_modal') === 'createTeamModal') is-invalid @endif" required>
                                    <option value="">Select operation area</option>
                                    @foreach ($operationAreas as $operationArea)
                                        <option value="{{ $operationArea->id }}" @selected(old('operation_area_id') == $operationArea->id)>{{ $operationArea->name }} ({{ $operationArea->state?->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lead Name</label>
                                <input type="text" name="lead_name" class="form-control @if($errors->has('lead_name') && session('open_modal') === 'createTeamModal') is-invalid @endif" value="{{ old('lead_name') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Members Count</label>
                                <input type="number" min="0" name="members_count" class="form-control @if($errors->has('members_count') && session('open_modal') === 'createTeamModal') is-invalid @endif" value="{{ old('members_count', 0) }}">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="teamActiveCreate" name="is_active" value="1" @checked(session('open_modal') === 'createTeamModal' ? old('is_active') : true)>
                                    <label class="form-check-label" for="teamActiveCreate">Active team</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Team</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($teams as $team)
        <div class="modal fade" id="editTeamModal-{{ $team->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('teams.update', $team) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Team</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Team Name</label>
                                    <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif" value="{{ old('name', $team->name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif" value="{{ old('code', $team->code) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select name="department_id" class="form-select @if($errors->has('department_id') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif" required>
                                        <option value="">Select department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected(old('department_id', $team->department_id) == $department->id)>{{ $department->name }} ({{ $department->code ?: 'No code' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Operation Area</label>
                                    <select name="operation_area_id" class="form-select @if($errors->has('operation_area_id') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif" required>
                                        <option value="">Select operation area</option>
                                        @foreach ($operationAreas as $operationArea)
                                            <option value="{{ $operationArea->id }}" @selected(old('operation_area_id', $team->operation_area_id) == $operationArea->id)>{{ $operationArea->name }} ({{ $operationArea->state?->name }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Lead Name</label>
                                    <input type="text" name="lead_name" class="form-control @if($errors->has('lead_name') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif" value="{{ old('lead_name', $team->lead_name) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Members Count</label>
                                    <input type="number" min="0" name="members_count" class="form-control @if($errors->has('members_count') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif" value="{{ old('members_count', $team->members_count) }}">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="teamActive-{{ $team->id }}" name="is_active" value="1" @checked(session('open_modal') === 'editTeamModal-' . $team->id ? old('is_active') : $team->is_active)>
                                        <label class="form-check-label" for="teamActive-{{ $team->id }}">Active team</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Team</button>
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
