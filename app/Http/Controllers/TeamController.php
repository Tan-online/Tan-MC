<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\OperationArea;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $teams = Team::query()
            ->with(['department:id,name,code', 'operationArea.state:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('lead_name', 'like', "%{$search}%")
                        ->orWhereHas('department', function ($departmentQuery) use ($search) {
                            $departmentQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('operationArea', function ($operationAreaQuery) use ($search) {
                            $operationAreaQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $departments = Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $operationAreas = OperationArea::query()
            ->with('state:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'state_id']);

        return view('master-data.teams.index', compact('teams', 'departments', 'operationAreas', 'search'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('teams.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createTeamModal');
        }

        Team::create($this->payload($request));

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team created successfully.');
    }

    public function update(Request $request, Team $team)
    {
        $validator = Validator::make($request->all(), $this->rules($team));

        if ($validator->fails()) {
            return redirect()
                ->route('teams.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editTeamModal-' . $team->id);
        }

        $team->update($this->payload($request));

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team updated successfully.');
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team deleted successfully.');
    }

    private function rules(?Team $team = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('teams', 'code')->ignore($team?->id)],
            'department_id' => ['required', 'exists:departments,id'],
            'operation_area_id' => ['required', 'exists:operation_areas,id'],
            'lead_name' => ['nullable', 'string', 'max:255'],
            'members_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => $request->filled('code') ? strtoupper((string) $request->input('code')) : null,
            'department_id' => $request->integer('department_id'),
            'operation_area_id' => $request->integer('operation_area_id'),
            'lead_name' => $request->input('lead_name'),
            'members_count' => $request->integer('members_count', 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
