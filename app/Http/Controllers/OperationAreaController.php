<?php

namespace App\Http\Controllers;

use App\Models\OperationArea;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class OperationAreaController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $operationAreas = OperationArea::query()
            ->with(['state:id,name,code'])
            ->withCount('teams')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('state', function ($stateQuery) use ($search) {
                            $stateQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $states = State::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('master-data.operation-areas.index', compact('operationAreas', 'states', 'search'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('operation-areas.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createOperationAreaModal');
        }

        OperationArea::create($this->payload($request));

        return redirect()
            ->route('operation-areas.index')
            ->with('status', 'Operation area created successfully.');
    }

    public function update(Request $request, OperationArea $operationArea)
    {
        $validator = Validator::make($request->all(), $this->rules($operationArea));

        if ($validator->fails()) {
            return redirect()
                ->route('operation-areas.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editOperationAreaModal-' . $operationArea->id);
        }

        $operationArea->update($this->payload($request));

        return redirect()
            ->route('operation-areas.index')
            ->with('status', 'Operation area updated successfully.');
    }

    public function destroy(OperationArea $operationArea)
    {
        if ($operationArea->teams()->exists() || $operationArea->locations()->exists() || $operationArea->executiveMappings()->exists()) {
            return redirect()
                ->route('operation-areas.index')
                ->with('error', 'This operation area cannot be deleted while teams, locations, or executive mappings are linked to it.');
        }

        $operationArea->delete();

        return redirect()
            ->route('operation-areas.index')
            ->with('status', 'Operation area deleted successfully.');
    }

    private function rules(?OperationArea $operationArea = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('operation_areas', 'code')->ignore($operationArea?->id)],
            'state_id' => ['required', 'exists:states,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => $request->filled('code') ? strtoupper((string) $request->input('code')) : null,
            'state_id' => $request->integer('state_id'),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
