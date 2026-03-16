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
                <a href="{{ route('exports.master-data', ['type' => 'teams'] + request()->query()) }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
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
                        <th>Manager</th>
                        <th>HOD</th>
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
                            <td>{{ $team->manager?->name ?: 'N/A' }}</td>
                            <td>{{ $team->hod?->name ?: 'N/A' }}</td>
                            <td>{{ $team->executives_count }}</td>
                            <td>
                                <span class="badge {{ $team->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $team->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewTeamModal-{{ $team->id }}">View</button>
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
                            <td colspan="8" class="text-center py-5 text-muted">No teams found for the current search.</td>
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
                            <div class="col-12">
                                <label class="form-label">Operation Executives</label>
                                @php
                                    $createOldIds = array_map('intval', (array) old('executive_ids', []));
                                @endphp
                                <select
                                    name="executive_ids[]"
                                    id="createTeamExecutiveIds"
                                    class="form-select @if($errors->has('executive_ids') && session('open_modal') === 'createTeamModal') is-invalid @endif"
                                    multiple
                                    data-executive-select
                                    data-dropdown-parent="#createTeamModal"
                                    data-search-url="{{ route('api.executives.search') }}"
                                    data-min-chars="3"
                                    data-placeholder="Search executive name or employee code..."
                                >
                                    @foreach ($createSelectedExecutives as $exec)
                                        <option value="{{ $exec->id }}" @selected(in_array((int) $exec->id, $createOldIds, true))>
                                            {{ $exec->name }} ({{ $exec->employee_code }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Search and select one or more executive names.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Manager</label>
                                <select name="manager_id" class="form-select @if($errors->has('manager_id') && session('open_modal') === 'createTeamModal') is-invalid @endif">
                                    <option value="">Select manager</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(old('manager_id') == $user->id)>{{ $user->name }} ({{ $user->employee_code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">HOD</label>
                                <select name="hod_id" class="form-select @if($errors->has('hod_id') && session('open_modal') === 'createTeamModal') is-invalid @endif">
                                    <option value="">Select HOD</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(old('hod_id') == $user->id)>{{ $user->name }} ({{ $user->employee_code }})</option>
                                    @endforeach
                                </select>
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
        <div class="modal fade" id="viewTeamModal-{{ $team->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Team Details</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><strong>Team:</strong> {{ $team->name }}</div>
                            <div class="col-md-6"><strong>Code:</strong> {{ $team->code ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Department:</strong> {{ $team->department?->name ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Operation Area:</strong> {{ $team->operationArea?->name ?: 'N/A' }}</div>
                            <div class="col-12">
                                <strong>Operation Executives:</strong>
                                @forelse ($team->executives as $exec)
                                    <span class="badge text-bg-secondary-subtle text-secondary border border-secondary-subtle ms-1">{{ $exec->name }}</span>
                                @empty
                                    <span class="text-muted">N/A</span>
                                @endforelse
                            </div>
                            <div class="col-md-6"><strong>Manager:</strong> {{ $team->manager?->name ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>HOD:</strong> {{ $team->hod?->name ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Members:</strong> {{ $team->executives_count }}</div>
                            <div class="col-md-6"><strong>Status:</strong> {{ $team->is_active ? 'Active' : 'Inactive' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                                <div class="col-12">
                                    <label class="form-label">Operation Executives</label>
                                    @php
                                        $isOpenEditModal = session('open_modal') === 'editTeamModal-' . $team->id;
                                        $existingExecIds = $isOpenEditModal
                                            ? array_map('intval', (array) old('executive_ids', $team->executives->pluck('id')->all()))
                                            : $team->executives->pluck('id')->map(fn ($id) => (int) $id)->all();

                                        $editSelectedSource = $isOpenEditModal
                                            ? $editSelectedExecutives
                                            : $team->executives;
                                    @endphp
                                    <select
                                        name="executive_ids[]"
                                        id="editTeamExecutiveIds-{{ $team->id }}"
                                        class="form-select @if($errors->has('executive_ids') && $isOpenEditModal) is-invalid @endif"
                                        multiple
                                        data-executive-select
                                        data-dropdown-parent="#editTeamModal-{{ $team->id }}"
                                        data-search-url="{{ route('api.executives.search') }}"
                                        data-min-chars="3"
                                        data-placeholder="Search executive name or employee code..."
                                    >
                                        @foreach ($editSelectedSource as $exec)
                                            <option value="{{ $exec->id }}" @selected(in_array((int) $exec->id, $existingExecIds, true))>
                                                {{ $exec->name }} ({{ $exec->employee_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Search and select one or more executive names.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Manager</label>
                                    <select name="manager_id" class="form-select @if($errors->has('manager_id') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif">
                                        <option value="">Select manager</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" @selected(old('manager_id', $team->manager_id) == $user->id)>{{ $user->name }} ({{ $user->employee_code }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">HOD</label>
                                    <select name="hod_id" class="form-select @if($errors->has('hod_id') && session('open_modal') === 'editTeamModal-' . $team->id) is-invalid @endif">
                                        <option value="">Select HOD</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" @selected(old('hod_id', $team->hod_id) == $user->id)>{{ $user->name }} ({{ $user->employee_code }})</option>
                                        @endforeach
                                    </select>
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

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Tom Select in modals - ensure dropdown is visible */
        .modal .ts-wrapper {
            position: relative;
            z-index: 1;
        }

        .modal .ts-dropdown {
            z-index: 9999 !important;
            position: fixed !important;
            max-height: 300px;
            min-width: 300px;
        }

        .modal .ts-dropdown-content {
            max-height: 280px;
            overflow-y: auto;
        }

        .ts-dropdown > div {
            padding: 8px 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .ts-dropdown > div:last-child {
            border-bottom: none;
        }

        .ts-dropdown > div:hover {
            background-color: #e7f1ff;
            cursor: pointer;
        }

        .ts-dropdown > div.selected {
            background-color: #0d6efd;
            color: white;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const escapeHtml = function (value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            };

            document.querySelectorAll('[data-executive-select]').forEach(function (select) {
                const searchUrl = select.dataset.searchUrl;
                const minChars = Number(select.dataset.minChars || 3);
                const modal = select.closest('.modal');

                if (!searchUrl) {
                    console.warn('No search URL found');
                    return;
                }

                const tomSelect = new TomSelect(select, {
                    valueField: 'id',
                    labelField: 'name',
                    searchField: 'name',
                    maxOptions: 20,
                    maxItems: null,
                    create: false,
                    plugins: ['remove_button'],
                    dropdownParent: modal,
                    preload: 'focus',
                    load: function (query, callback) {
                        if (!query || query.trim().length < minChars) {
                            return callback();
                        }
                        
                        console.log('[API] Calling:', searchUrl + '?q=' + query);
                        
                        fetch(searchUrl + '?q=' + encodeURIComponent(query.trim()), {
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(json => {
                            console.log('[API] Response:', json);
                            callback(Array.isArray(json) ? json : []);
                        })
                        .catch(() => callback());
                    },
                    render: {
                        option: function (data, escape) {
                            const code = data.employee_code ? ' (' + data.employee_code + ')' : '';
                            return '<div>' + escape(data.name) + escape(code) + '</div>';
                        },
                        item: function (data, escape) {
                            const code = data.employee_code ? ' (' + data.employee_code + ')' : '';
                            return '<div>' + escape(data.name) + escape(code) + '</div>';
                        }
                    }
                });
            });
        });
    </script>
@endpush
