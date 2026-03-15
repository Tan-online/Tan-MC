<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $statusOptions = ['Active', 'Inactive'];

        $users = User::query()
            ->with([
                'department:id,name',
                'role:id,name,slug',
                'roles:id,name,slug',
                'manager:id,name,employee_code',
                'hod:id,name,employee_code',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('department', fn ($departmentQuery) => $departmentQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $roles = Role::query()
            ->whereIn('slug', collect(config('erp.roles', []))->pluck('slug')->all())
            ->get(['id', 'name', 'slug'])
            ->sortBy(fn (Role $role) => array_search($role->slug, config('erp.role_priority', []), true))
            ->values();

        $reportingOptions = User::query()
            ->with(['role:id,name,slug', 'roles:id,name,slug'])
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'designation', 'role_id'])
            ->filter(function (User $candidate) {
                $designation = Str::lower((string) $candidate->designation);

                return $candidate->hasRole(['super_admin', 'admin', 'reviewer', 'manager', 'hod'])
                    || Str::contains($designation, ['admin', 'manager', 'hod', 'head']);
            })
            ->values();

        $managerOptions = $reportingOptions;
        $hodOptions = $reportingOptions;

        return view('master-data.users.index', compact('users', 'departments', 'roles', 'managerOptions', 'hodOptions', 'search', 'statusOptions'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('users.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createUserModal');
        }

        $user = User::create($this->payload($request) + [
            'password' => Hash::make($this->defaultPassword((string) $request->input('employee_code'))),
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);
        $user->syncRoles($request->input('role_ids', []));
        $this->logActivity('users', 'create', "Created user {$user->name} ({$user->employee_code}).", $user, $request->user());

        return redirect()
            ->route('users.index')
            ->with('status', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), $this->rules($user));

        if ($validator->fails()) {
            return redirect()
                ->route('users.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editUserModal-' . $user->id);
        }

        $user->update($this->payload($request, $user));
        $user->syncRoles($request->input('role_ids', []));
        $this->logActivity('users', 'update', "Updated user {$user->name} ({$user->employee_code}).", $user, $request->user());

        return redirect()
            ->route('users.index')
            ->with('status', 'User updated successfully.');
    }

    public function deactivate(User $user, Request $request)
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('users.index')
                ->with('error', 'You cannot deactivate your own account.');
        }

        $user->update([
            'status' => 'Inactive',
        ]);
        $this->logActivity('users', 'deactivate', "Deactivated user {$user->name} ({$user->employee_code}).", $user, $request->user());

        return redirect()
            ->route('users.index')
            ->with('status', 'User deactivated successfully.');
    }

    public function resetPassword(User $user, Request $request)
    {
        $user->update([
            'password' => Hash::make($this->defaultPassword($user->employee_code)),
            'status' => 'Active',
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);
        $this->logActivity('users', 'reset_password', "Reset password for {$user->name} ({$user->employee_code}).", $user, $request->user());

        return redirect()
            ->route('users.index')
            ->with('status', 'Password reset successfully. Default password is employee code + 123.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'employee_code' => ['required', 'digits:6', Rule::unique('users', 'employee_code')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'manager_id' => ['nullable', 'exists:users,id', Rule::notIn(array_filter([$user?->id]))],
            'hod_id' => ['nullable', 'exists:users,id', Rule::notIn(array_filter([$user?->id]))],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    private function payload(Request $request, ?User $user = null): array
    {
        $managerId = $request->filled('manager_id') ? $request->integer('manager_id') : null;
        $hodId = $request->filled('hod_id') ? $request->integer('hod_id') : null;

        if ($user && $managerId === $user->id) {
            $managerId = null;
        }

        if ($user && $hodId === $user->id) {
            $hodId = null;
        }

        return [
            'employee_code' => (string) $request->input('employee_code'),
            'name' => $request->input('name'),
            'designation' => $request->filled('designation') ? trim((string) $request->input('designation')) : null,
            'email' => strtolower((string) $request->input('email')),
            'phone' => $request->input('phone'),
            'department_id' => $request->filled('department_id') ? $request->integer('department_id') : null,
            'manager_id' => $managerId,
            'hod_id' => $hodId,
            'status' => $request->input('status'),
        ];
    }

    private function defaultPassword(string $employeeCode): string
    {
        return trim($employeeCode) . '123';
    }
}
