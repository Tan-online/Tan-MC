<?php

namespace App\Http\Controllers;

use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class StateController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $states = State::query()
            ->withCount('operationAreas')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('master-data.states.index', compact('states', 'search'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('states.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createStateModal');
        }

        State::create($this->payload($request));

        return redirect()
            ->route('states.index')
            ->with('status', 'State created successfully.');
    }

    public function update(Request $request, State $state)
    {
        $validator = Validator::make($request->all(), $this->rules($state));

        if ($validator->fails()) {
            return redirect()
                ->route('states.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editStateModal-' . $state->id);
        }

        $state->update($this->payload($request));

        return redirect()
            ->route('states.index')
            ->with('status', 'State updated successfully.');
    }

    public function destroy(State $state)
    {
        if ($state->operationAreas()->exists()) {
            return redirect()
                ->route('states.index')
                ->with('error', 'This state cannot be deleted while operation areas are linked to it.');
        }

        $state->delete();

        return redirect()
            ->route('states.index')
            ->with('status', 'State deleted successfully.');
    }

    private function rules(?State $state = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', Rule::unique('states', 'code')->ignore($state?->id)],
            'region' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => strtoupper((string) $request->input('code')),
            'region' => $request->input('region'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
