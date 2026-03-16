<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\OperationArea;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $openModal = (string) $request->session()->get('open_modal', '');

        $teams = Team::query()
            ->with([
                'department:id,name,code',
                'operationArea.state:id,name',
                'operationExecutive:id,name,employee_code',
                'executives:id,name,employee_code',
                'manager:id,name,employee_code',
                'hod:id,name,employee_code',
            ])
            ->withCount('executives')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('executives', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('operationExecutive', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('manager', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('hod', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
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

        $users = User::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);

        $createSelectedExecutiveIds = collect((array) old('executive_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $editSelectedExecutiveIds = collect((array) old('executive_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $oldSelectionIds = collect();

        if ($openModal === 'createTeamModal') {
            $oldSelectionIds = $oldSelectionIds->merge($createSelectedExecutiveIds);
        }

        if (str_starts_with($openModal, 'editTeamModal-')) {
            $oldSelectionIds = $oldSelectionIds->merge($editSelectedExecutiveIds);
        }

        $selectedExecutiveLookup = $oldSelectionIds->isEmpty()
            ? collect()
            : User::query()
                ->whereIn('id', $oldSelectionIds->all())
                ->where('status', 'Active')
                ->where(function ($query) {
                    $query
                        ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', 'operations'))
                        ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'operations'));
                })
                ->get(['id', 'name', 'employee_code'])
                ->keyBy('id');

        $createSelectedExecutives = $createSelectedExecutiveIds
            ->map(fn ($id) => $selectedExecutiveLookup->get($id))
            ->filter()
            ->values();

        $editSelectedExecutives = $editSelectedExecutiveIds
            ->map(fn ($id) => $selectedExecutiveLookup->get($id))
            ->filter()
            ->values();

        return view('master-data.teams.index', compact(
            'teams',
            'departments',
            'operationAreas',
            'search',
            'users',
            'createSelectedExecutives',
            'editSelectedExecutives',
            'openModal'
        ));
    }

    public function searchExecutives(Request $request)
    {
        $queryText = trim((string) $request->query('q', ''));

        if (mb_strlen($queryText) < 3) {
            return response()->json([]);
        }

        $executives = User::query()
            ->where('status', 'Active')
            ->where(function ($query) {
                $query
                    ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', 'operations'))
                    ->orWhereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'operations'));
            })
            ->where(function ($query) use ($queryText) {
                $query
                    ->where('name', 'like', "%{$queryText}%")
                    ->orWhere('employee_code', 'like', "%{$queryText}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'employee_code']);

        return response()->json($executives);
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

        $team = Team::create($this->payload($request));

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('executive_ids', [])), fn ($id) => $id > 0));
        $syncData = [];
        foreach ($ids as $id) {
            $syncData[$id] = ['is_primary' => false];
        }
        if (! empty($ids)) {
            $syncData[$ids[0]] = ['is_primary' => true];
        }
        $team->executives()->sync($syncData);
        $team->update([
            'operation_executive_id' => $ids[0] ?? null,
            'members_count' => count($ids),
        ]);

        $this->logActivity('teams', 'create', "Created team {$team->name}.", $team, $request->user());

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

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('executive_ids', [])), fn ($id) => $id > 0));
        $syncData = [];
        foreach ($ids as $id) {
            $syncData[$id] = ['is_primary' => false];
        }
        if (! empty($ids)) {
            $syncData[$ids[0]] = ['is_primary' => true];
        }
        $team->executives()->sync($syncData);
        $team->update([
            'operation_executive_id' => $ids[0] ?? null,
            'members_count' => count($ids),
        ]);

        $this->logActivity('teams', 'update', "Updated team {$team->name}.", $team, $request->user());

        return redirect()
            ->route('teams.index')
            ->with('status', 'Team updated successfully.');
    }

    public function destroy(Team $team)
    {
        $teamName = $team->name;
        $team->delete();
        $this->logActivity('teams', 'delete', "Deleted team {$teamName}.", $team->id, request()->user());

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
            'operation_executive_id' => ['nullable', 'exists:users,id'],
            'executive_ids' => ['nullable', 'array'],
            'executive_ids.*' => ['integer', 'exists:users,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'hod_id' => ['nullable', 'exists:users,id'],
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
            'operation_executive_id' => $request->filled('operation_executive_id') ? $request->integer('operation_executive_id') : null,
            'manager_id' => $request->filled('manager_id') ? $request->integer('manager_id') : null,
            'hod_id' => $request->filled('hod_id') ? $request->integer('hod_id') : null,
            'lead_name' => null,
            'members_count' => 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
