<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ExecutiveMapping;
use App\Models\Location;
use App\Models\OperationArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExecutiveMappingController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $executiveMappings = ExecutiveMapping::query()
            ->with(['client:id,name,code', 'location:id,name,city', 'operationArea:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('executive_name', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))
                        ->orWhereHas('operationArea', fn ($areaQuery) => $areaQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('executive_name')
            ->paginate(10)
            ->withQueryString();

        $clients = Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $contracts = Contract::query()->where('status', 'Active')->orderBy('contract_no')->get(['id', 'client_id', 'contract_no']);
        $locations = Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'city']);
        $operationAreas = OperationArea::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $executiveUsers = User::query()
            ->with('role:id,name,slug')
            ->where('status', 'Active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'executive'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role_id']);

        return view('master-data.executive-mappings.index', compact('executiveMappings', 'clients', 'contracts', 'locations', 'operationAreas', 'executiveUsers', 'search'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('executive-mappings.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createExecutiveMappingModal');
        }

        ExecutiveMapping::create($this->payload($request));

        return redirect()
            ->route('executive-mappings.index')
            ->with('status', 'Executive mapping created successfully.');
    }

    public function update(Request $request, ExecutiveMapping $executiveMapping)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('executive-mappings.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editExecutiveMappingModal-' . $executiveMapping->id);
        }

        $executiveMapping->update($this->payload($request));

        return redirect()
            ->route('executive-mappings.index')
            ->with('status', 'Executive mapping updated successfully.');
    }

    public function destroy(ExecutiveMapping $executiveMapping)
    {
        $executiveMapping->delete();

        return redirect()
            ->route('executive-mappings.index')
            ->with('status', 'Executive mapping deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'operation_area_id' => ['required', 'exists:operation_areas,id'],
            'executive_user_id' => ['nullable', 'exists:users,id'],
            'executive_name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        $executiveUser = $request->filled('executive_user_id')
            ? User::query()->find($request->integer('executive_user_id'))
            : null;

        return [
            'client_id' => $request->integer('client_id'),
            'contract_id' => $request->filled('contract_id') ? $request->integer('contract_id') : null,
            'location_id' => $request->filled('location_id') ? $request->integer('location_id') : null,
            'operation_area_id' => $request->integer('operation_area_id'),
            'executive_user_id' => $executiveUser?->id,
            'executive_name' => $executiveUser?->name ?? $request->input('executive_name'),
            'designation' => $request->input('designation'),
            'email' => $executiveUser?->email ?? $request->input('email'),
            'phone' => $executiveUser?->phone ?? $request->input('phone'),
            'is_primary' => $request->boolean('is_primary'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
