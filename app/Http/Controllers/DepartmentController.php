<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $departments = Department::query()
            ->withCount('teams')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('master-data.departments.index', compact('departments', 'search'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('departments.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createDepartmentModal');
        }

        $department = Department::create($this->payload($request));
        $this->logActivity('departments', 'create', "Created department {$department->name}.", $department, $request->user());

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department created successfully.');
    }

    public function update(Request $request, Department $department)
    {
        $validator = Validator::make($request->all(), $this->rules($department));

        if ($validator->fails()) {
            return redirect()
                ->route('departments.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editDepartmentModal-' . $department->id);
        }

        $department->update($this->payload($request));
        $this->logActivity('departments', 'update', "Updated department {$department->name}.", $department, $request->user());

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        if ($department->teams()->exists()) {
            return redirect()
                ->route('departments.index')
                ->with('error', 'This department cannot be deleted while teams are assigned to it.');
        }

        $departmentName = $department->name;
        $department->delete();
        $this->logActivity('departments', 'delete', "Deleted department {$departmentName}.", $department->id, request()->user());

        return redirect()
            ->route('departments.index')
            ->with('status', 'Department deleted successfully.');
    }

    private function rules(?Department $department = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => $request->filled('code') ? strtoupper((string) $request->input('code')) : null,
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
