@extends('layouts.app')

@section('title', 'Users | Tan-MC')

@section('content')
    <x-page-header
        title="Users"
        subtitle="Manage employee accounts, reporting lines, and role-based ERP access."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Users'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <a href="{{ route('exports.master-data', ['type' => 'users'] + request()->query()) }}" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
                @if (userCan('users.create'))
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="bi bi-plus-circle me-2"></i>Add User
                    </button>
                @endif
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <div class="surface-card p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">User Directory</h2>
                <p class="text-muted mb-0">Create employee accounts, assign reporting lines, and manage role-based access.</p>
            </div>

            <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search users">
                </div>
                <button class="btn btn-outline-secondary">Search</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Reporting</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="fw-semibold">{{ $user->employee_code }}</td>
                            <td class="fw-semibold">{{ $user->name }}</td>
                            <td>{{ $user->roleNames() ?: 'Unassigned' }}</td>
                            <td class="small">
                                {{ $user->manager?->name ?: 'N/A' }}
                            </td>
                            <td>
                                <span class="badge {{ $user->status === 'Active' ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $user->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewUserModal-{{ $user->id }}">
                                        View
                                    </button>
                                    @if (userCan('users.edit'))
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal-{{ $user->id }}">
                                            Edit
                                        </button>
                                    @endif
                                    @if (userCan('users.reset_password'))
                                        <form method="POST" action="{{ route('users.reset-password', $user) }}" onsubmit="return confirm('Reset password to employee code + 123 for this user?');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-warning">Reset Password</button>
                                        </form>
                                    @endif
                                    @if ($user->status === 'Active' && userCan('users.deactivate'))
                                        <form method="POST" action="{{ route('users.deactivate', $user) }}" onsubmit="return confirm('Deactivate this user?');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                                        </form>
                                    @elseif ($user->status === 'Inactive' && userCan('users.deactivate'))
                                        <form method="POST" action="{{ route('users.activate', $user) }}" onsubmit="return confirm('Activate this user?');">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-success">Activate</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No users found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <p class="text-muted small mb-0">Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users</p>
            {{ $users->links() }}
        </div>
    </div>

    @if (userCan('users.create'))
        <div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('users.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Add User</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Employee Code</label>
                                    <input type="text" name="employee_code" maxlength="6" class="form-control @if($errors->has('employee_code') && session('open_modal') === 'createUserModal') is-invalid @endif" value="{{ old('employee_code') }}" placeholder="000123" required data-password-source>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Password</label>
                                    <input type="text" class="form-control bg-light" value="{{ old('employee_code') ? old('employee_code') . '123' : '' }}" readonly data-password-target placeholder="Employee code + 123">
                                    <div class="form-text">Default password is generated as employee code + 123.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === 'createUserModal') is-invalid @endif" required>
                                        @foreach ($statusOptions as $statusOption)
                                            <option value="{{ $statusOption }}" @selected(old('status', 'Active') === $statusOption)>{{ $statusOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'createUserModal') is-invalid @endif" value="{{ old('name') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control @if($errors->has('email') && session('open_modal') === 'createUserModal') is-invalid @endif" value="{{ old('email') }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control @if($errors->has('phone') && session('open_modal') === 'createUserModal') is-invalid @endif" value="{{ old('phone') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Designation</label>
                                    <input type="text" name="designation" class="form-control @if($errors->has('designation') && session('open_modal') === 'createUserModal') is-invalid @endif" value="{{ old('designation') }}" placeholder="Manager, HOD, Executive">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select name="department_id" class="form-select @if($errors->has('department_id') && session('open_modal') === 'createUserModal') is-invalid @endif">
                                        <option value="">Select department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Roles</label>
                                    <select name="role_ids[]" class="form-select @if($errors->has('role_ids') && session('open_modal') === 'createUserModal') is-invalid @endif" multiple required size="5">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected(in_array((string) $role->id, array_map('strval', old('role_ids', [])), true))>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Use Ctrl/Cmd + click to assign multiple roles.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Manager</label>
                                    <select name="manager_id" class="form-select @if($errors->has('manager_id') && session('open_modal') === 'createUserModal') is-invalid @endif">
                                        <option value="">Select manager</option>
                                        @foreach ($managerOptions as $managerOption)
                                            <option value="{{ $managerOption->id }}" @selected((string) old('manager_id') === (string) $managerOption->id)>
                                                {{ $managerOption->name }} ({{ $managerOption->employee_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">HOD</label>
                                    <select name="hod_id" class="form-select @if($errors->has('hod_id') && session('open_modal') === 'createUserModal') is-invalid @endif">
                                        <option value="">Select HOD</option>
                                        @foreach ($hodOptions as $hodOption)
                                            <option value="{{ $hodOption->id }}" @selected((string) old('hod_id') === (string) $hodOption->id)>
                                                {{ $hodOption->name }} ({{ $hodOption->employee_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @foreach ($users as $user)
        <div class="modal fade" id="viewUserModal-{{ $user->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">User Details</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><strong>Employee Code:</strong> {{ $user->employee_code }}</div>
                            <div class="col-md-6"><strong>Name:</strong> {{ $user->name }}</div>
                            <div class="col-md-6"><strong>Email:</strong> {{ $user->email }}</div>
                            <div class="col-md-6"><strong>Phone:</strong> {{ $user->phone ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Department:</strong> {{ $user->department?->name ?: 'Unassigned' }}</div>
                            <div class="col-md-6"><strong>Role:</strong> {{ $user->roleNames() ?: 'Unassigned' }}</div>
                            <div class="col-md-6"><strong>Manager:</strong> {{ $user->manager?->name ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>HOD:</strong> {{ $user->hod?->name ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Designation:</strong> {{ $user->designation ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Status:</strong> {{ $user->status }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @php
            $selectedRoleIds = $user->roles->pluck('id')->map(fn ($id) => (string) $id)->all();
            $modalRoleIds = session('open_modal') === 'editUserModal-' . $user->id
                ? array_map('strval', old('role_ids', $selectedRoleIds))
                : $selectedRoleIds;
        @endphp
        <div class="modal fade" id="editUserModal-{{ $user->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('users.update', $user) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit User</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Employee Code</label>
                                    <input type="text" name="employee_code" maxlength="6" class="form-control @if($errors->has('employee_code') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif" value="{{ session('open_modal') === 'editUserModal-' . $user->id ? old('employee_code', $user->employee_code) : $user->employee_code }}" required data-password-source>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Password</label>
                                    <input type="text" class="form-control bg-light" value="{{ (session('open_modal') === 'editUserModal-' . $user->id ? old('employee_code', $user->employee_code) : $user->employee_code) . '123' }}" readonly data-password-target>
                                    <div class="form-text">Use reset password to apply this default password.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif" required>
                                        @foreach ($statusOptions as $statusOption)
                                            <option value="{{ $statusOption }}" @selected((session('open_modal') === 'editUserModal-' . $user->id ? old('status', $user->status) : $user->status) === $statusOption)>{{ $statusOption }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif" value="{{ session('open_modal') === 'editUserModal-' . $user->id ? old('name', $user->name) : $user->name }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control @if($errors->has('email') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif" value="{{ session('open_modal') === 'editUserModal-' . $user->id ? old('email', $user->email) : $user->email }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control @if($errors->has('phone') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif" value="{{ session('open_modal') === 'editUserModal-' . $user->id ? old('phone', $user->phone) : $user->phone }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Designation</label>
                                    <input type="text" name="designation" class="form-control @if($errors->has('designation') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif" value="{{ session('open_modal') === 'editUserModal-' . $user->id ? old('designation', $user->designation) : $user->designation }}" placeholder="Manager, HOD, Executive">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department</label>
                                    <select name="department_id" class="form-select @if($errors->has('department_id') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif">
                                        <option value="">Select department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected((string) (session('open_modal') === 'editUserModal-' . $user->id ? old('department_id', $user->department_id) : $user->department_id) === (string) $department->id)>{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Roles</label>
                                    <select name="role_ids[]" class="form-select @if($errors->has('role_ids') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif" required multiple size="5">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected(in_array((string) $role->id, $modalRoleIds, true))>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Use Ctrl/Cmd + click to assign multiple roles.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Manager</label>
                                    <select name="manager_id" class="form-select @if($errors->has('manager_id') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif">
                                        <option value="">Select manager</option>
                                        @foreach ($managerOptions as $managerOption)
                                            @if ($managerOption->id !== $user->id)
                                                <option value="{{ $managerOption->id }}" @selected((string) (session('open_modal') === 'editUserModal-' . $user->id ? old('manager_id', $user->manager_id) : $user->manager_id) === (string) $managerOption->id)>
                                                    {{ $managerOption->name }} ({{ $managerOption->employee_code }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">HOD</label>
                                    <select name="hod_id" class="form-select @if($errors->has('hod_id') && session('open_modal') === 'editUserModal-' . $user->id) is-invalid @endif">
                                        <option value="">Select HOD</option>
                                        @foreach ($hodOptions as $hodOption)
                                            @if ($hodOption->id !== $user->id)
                                                <option value="{{ $hodOption->id }}" @selected((string) (session('open_modal') === 'editUserModal-' . $user->id ? old('hod_id', $user->hod_id) : $user->hod_id) === (string) $hodOption->id)>
                                                    {{ $hodOption->name }} ({{ $hodOption->employee_code }})
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-password-source]').forEach(function (input) {
                const passwordTarget = input.closest('.row')?.querySelector('[data-password-target]');

                if (!passwordTarget) {
                    return;
                }

                const syncPassword = function () {
                    passwordTarget.value = input.value ? input.value + '123' : '';
                };

                input.addEventListener('input', syncPassword);
                syncPassword();
            });

            const createModal = document.getElementById('createUserModal');

            if (createModal) {
                createModal.addEventListener('hidden.bs.modal', function () {
                    if (@json(session('open_modal') === 'createUserModal')) {
                        return;
                    }

                    const form = createModal.querySelector('form');

                    if (!form) {
                        return;
                    }

                    form.reset();
                    form.querySelectorAll('.is-invalid').forEach(function (field) {
                        field.classList.remove('is-invalid');
                    });
                    const passwordTarget = form.querySelector('[data-password-target]');

                    if (passwordTarget) {
                        passwordTarget.value = '';
                    }
                });
            }
        });
    </script>
@endpush
