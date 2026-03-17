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
        .modal-dialog,
        .modal-content,
        .modal-body {
            overflow: visible;
        }

        .modal .ts-wrapper {
            overflow: visible;
            position: relative;
            z-index: 1056;
        }

        .modal .ts-control {
            align-items: center;
            border-color: #dee2e6;
            cursor: text;
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
            min-height: 52px;
            padding: 0.5rem 2.5rem 0.5rem 0.75rem;
        }

        .modal .ts-control input {
            flex: 1 0 10rem;
            min-width: 8rem;
        }

        .modal .ts-control > .item {
            margin: 0;
        }

        .modal .ts-wrapper.multi.has-items .ts-control > input {
            margin: 0;
        }

        .modal .ts-wrapper.form-select,
        .modal .ts-wrapper.single,
        .modal .ts-wrapper.multi {
            width: 100%;
        }

        .modal .ts-wrapper.focus .ts-control,
        .modal .ts-control.focus,
        .modal .ts-control.focused {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .modal .ts-dropdown {
            border: 1px solid rgba(13, 110, 253, 0.12);
            border-radius: 0.75rem;
            box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.18);
            left: 0;
            margin-top: 0.375rem;
            overflow: hidden;
            position: absolute;
            top: 100%;
            min-width: 100%;
            z-index: 1061;
        }

        .modal .ts-dropdown .option,
        .modal .ts-dropdown .no-results,
        .modal .ts-dropdown .create {
            padding: 0.875rem 1rem;
        }

        .modal .ts-dropdown .option.active,
        .modal .ts-dropdown .option:hover {
            background-color: #eef5ff;
            color: #0a58ca;
        }

        .modal .ts-dropdown-content {
            max-height: 320px;
            overflow-y: auto;
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
            const DEBOUNCE_DELAY = 300;
            const RESULT_LIMIT = 20;

            const resetOptions = function (tomSelect) {
                const selectedValues = new Set(tomSelect.items.map(function (item) {
                    return String(item);
                }));

                tomSelect.clearOptions(function (option, value) {
                    return selectedValues.has(String(value));
                });
                tomSelect.refreshOptions(false);
            };

            const stopPendingWork = function (state) {
                if (state.debounceTimer) {
                    window.clearTimeout(state.debounceTimer);
                    state.debounceTimer = null;
                }

                if (state.abortController) {
                    state.abortController.abort();
                    state.abortController = null;
                }
            };

            const destroyExecutiveSelect = function (select) {
                const tomSelect = select.tomselect;
                const state = select._executiveTomState;

                if (!tomSelect || !state) {
                    return;
                }

                state.destroyed = true;
                stopPendingWork(state);
                tomSelect.close();
                tomSelect.destroy();
                delete select._executiveTomState;
            };

            const createExecutiveSelect = function (select) {
                if (select.tomselect) {
                    return select.tomselect;
                }

                const modal = select.closest('.modal');
                const searchUrl = select.dataset.searchUrl;
                const minChars = Number(select.dataset.minChars || 3);
                const placeholder = select.dataset.placeholder || 'Type at least 3 characters to search executives...';

                if (!modal || !searchUrl) {
                    return null;
                }

                const state = {
                    abortController: null,
                    debounceTimer: null,
                    destroyed: false,
                    requestId: 0,
                    lastQuery: ''
                };

                select._executiveTomState = state;

                const tomSelect = new TomSelect(select, {
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name', 'employee_code'],
                    searchConjunction: 'and',
                    maxOptions: RESULT_LIMIT,
                    maxItems: null,
                    create: false,
                    preload: false,
                    openOnFocus: false,
                    loadThrottle: null,
                    closeAfterSelect: false,
                    hideSelected: true,
                    placeholder: placeholder,
                    noResultsText: 'No executives found',
                    plugins: {
                        remove_button: {
                            title: 'Remove this item'
                        }
                    },
                    shouldLoad: function (query) {
                        return query.trim().length >= minChars;
                    },
                    render: {
                        option: function (data, escape) {
                            const code = data.employee_code
                                ? ' <span class="text-muted">(' + escape(data.employee_code) + ')</span>'
                                : '';

                            return '<div class="d-flex align-items-center justify-content-between gap-2"><span>' + escape(data.name) + '</span>' + code + '</div>';
                        },
                        item: function (data, escape) {
                            const code = data.employee_code ? ' (' + escape(data.employee_code) + ')' : '';
                            return '<div>' + escape(data.name) + code + '</div>';
                        }
                    },
                    load: function (query, callback) {
                        const normalizedQuery = query.trim();
                        const control = this;

                        console.log('[Executive Search] query:', normalizedQuery);

                        stopPendingWork(state);

                        if (normalizedQuery.length < minChars) {
                            state.lastQuery = '';
                            resetOptions(control);
                            control.close();
                            callback();
                            return;
                        }

                        state.lastQuery = normalizedQuery;
                        const currentRequestId = ++state.requestId;

                        state.debounceTimer = window.setTimeout(function () {
                            if (state.destroyed) {
                                callback();
                                return;
                            }

                            const controller = new AbortController();
                            state.abortController = controller;

                            fetch(searchUrl + '?q=' + encodeURIComponent(normalizedQuery), {
                                signal: controller.signal,
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            })
                                .then(function (response) {
                                    if (!response.ok) {
                                        throw new Error('Network error: ' + response.status);
                                    }

                                    return response.json();
                                })
                                .then(function (json) {
                                    console.log('[Executive Search] response:', json);

                                    if (
                                        state.destroyed ||
                                        controller.signal.aborted ||
                                        currentRequestId !== state.requestId ||
                                        normalizedQuery !== state.lastQuery
                                    ) {
                                        callback();
                                        return;
                                    }

                                    resetOptions(control);

                                    const results = Array.isArray(json) ? json.slice(0, RESULT_LIMIT) : [];

                                    callback(results);

                                    window.requestAnimationFrame(function () {
                                        if (
                                            state.destroyed ||
                                            currentRequestId !== state.requestId ||
                                            normalizedQuery !== state.lastQuery
                                        ) {
                                            return;
                                        }

                                        control.refreshOptions(true);
                                        control.open();
                                    });
                                })
                                .catch(function (error) {
                                    if (error.name !== 'AbortError') {
                                        console.error('[Executive Search] Fetch error:', error);
                                    }

                                    callback();
                                })
                                .finally(function () {
                                    if (state.abortController === controller) {
                                        state.abortController = null;
                                    }
                                });
                        }, DEBOUNCE_DELAY);
                    },
                    onInitialize: function () {
                        const control = this;
                        const input = control.control_input;

                        input.removeAttribute('readonly');
                        input.setAttribute('autocomplete', 'off');

                        input.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                        });

                        control.control.addEventListener('mousedown', function (event) {
                            if (event.target.closest('.remove')) {
                                return;
                            }

                            window.requestAnimationFrame(function () {
                                input.removeAttribute('readonly');
                                input.focus({ preventScroll: true });
                            });
                        });
                    },
                    onType: function (value) {
                        if (value.trim().length !== 0) {
                            return;
                        }

                        state.lastQuery = '';
                        stopPendingWork(state);
                        resetOptions(this);
                        this.close();
                    },
                    onItemAdd: function () {
                        state.lastQuery = '';
                        stopPendingWork(state);
                        resetOptions(this);
                        this.setTextboxValue('');
                        this.lastValue = '';
                        this.close();
                    }
                });

                return tomSelect;
            };

            document.querySelectorAll('.modal').forEach(function (modal) {
                modal.addEventListener('shown.bs.modal', function () {
                    document.querySelectorAll('.auto-dismiss').forEach(function (alert) {
                        alert.remove();
                    });

                    modal.querySelectorAll('[data-executive-select]').forEach(function (select) {
                        const tomSelect = createExecutiveSelect(select);

                        if (tomSelect) {
                            tomSelect.control_input.removeAttribute('readonly');
                            window.requestAnimationFrame(function () {
                                tomSelect.control_input.focus({ preventScroll: true });
                            });
                        }
                    });
                });

                modal.addEventListener('hide.bs.modal', function () {
                    modal.querySelectorAll('[data-executive-select]').forEach(function (select) {
                        if (select.tomselect) {
                            select.tomselect.close();
                        }
                    });
                });

                modal.addEventListener('hidden.bs.modal', function () {
                    modal.querySelectorAll('[data-executive-select]').forEach(function (select) {
                        destroyExecutiveSelect(select);
                    });
                });
            });

            document.querySelectorAll('.modal.show').forEach(function (modal) {
                modal.querySelectorAll('[data-executive-select]').forEach(function (select) {
                    createExecutiveSelect(select);
                });
            });
        });
    </script>
@endpush
