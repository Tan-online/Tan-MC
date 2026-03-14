<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
                        ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $roles = Role::query()->orderBy('id')->get(['id', 'name', 'slug']);

        $managerOptions = User::query()
            ->with('role:id,name,slug')
            ->where('status', 'Active')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'hod', 'manager']))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'role_id']);

        $hodOptions = User::query()
            ->with('role:id,name,slug')
            ->where('status', 'Active')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'hod']))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'role_id']);

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

        User::create($this->payload($request) + [
            'password' => Hash::make($this->defaultPassword((string) $request->input('employee_code'))),
        ]);

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

        return redirect()
            ->route('users.index')
            ->with('status', 'User deactivated successfully.');
    }

    public function resetPassword(User $user)
    {
        $user->update([
            'password' => Hash::make($this->defaultPassword($user->employee_code)),
            'status' => 'Active',
        ]);

        return redirect()
            ->route('users.index')
            ->with('status', 'Password reset successfully. Default password is employee code + 123.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'employee_code' => ['required', 'digits:6', Rule::unique('users', 'employee_code')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
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
            'email' => strtolower((string) $request->input('email')),
            'phone' => $request->input('phone'),
            'department_id' => $request->filled('department_id') ? $request->integer('department_id') : null,
            'role_id' => $request->integer('role_id'),
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
