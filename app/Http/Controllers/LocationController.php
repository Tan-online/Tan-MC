<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Location;
use App\Models\OperationArea;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $stateId = (int) ($validated['state_id'] ?? 0);
        $clientId = (int) ($validated['client_id'] ?? 0);

        $locationsQuery = Location::query()
            ->select(['id', 'client_id', 'state_id', 'operation_area_id', 'code', 'name', 'address', 'is_active'])
            ->with(['client:id,name,code', 'state:id,name,code'])
            ->withCount(['contracts', 'serviceOrders'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('state', fn ($stateQuery) => $stateQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($stateId > 0, fn ($query) => $query->where('state_id', $stateId))
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('name');

        $this->accessControl()->scopeLocations($locationsQuery, $user);

        $locations = $locationsQuery
            ->paginate(25)
            ->withQueryString();

        $clientsQuery = Client::query()->where('is_active', true)->orderBy('name');
        $this->accessControl()->scopeClients($clientsQuery, $user);
        $clients = $clientsQuery->get(['id', 'name', 'code']);
        $states = State::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('master-data.locations.index', compact('locations', 'clients', 'states', 'search', 'stateId', 'clientId'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('locations.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createLocationModal');
        }

        $operationAreaId = $this->resolveOperationAreaId($request->integer('state_id'));

        if (! $operationAreaId) {
            return redirect()
                ->route('locations.index')
                ->withInput()
                ->with('error', 'No operation area found for the selected state. Please create one first.')
                ->with('open_modal', 'createLocationModal');
        }

        $location = Location::create($this->payload($request, $operationAreaId));
        $this->logActivity('locations', 'create', "Created location {$location->name}.", $location, $request->user());

        return redirect()
            ->route('locations.index')
            ->with('status', 'Location created successfully.');
    }

    public function update(Request $request, Location $location)
    {
        $this->accessControl()->scopeLocations(Location::query()->whereKey($location->id), $request->user())->firstOrFail();

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('locations.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editLocationModal-' . $location->id);
        }

        $operationAreaId = $this->resolveOperationAreaId($request->integer('state_id')) ?: $location->operation_area_id;

        $location->update($this->payload($request, $operationAreaId));
        $this->logActivity('locations', 'update', "Updated location {$location->name}.", $location, $request->user());

        return redirect()
            ->route('locations.index')
            ->with('status', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        $this->accessControl()->scopeLocations(Location::query()->whereKey($location->id), request()->user())->firstOrFail();

        if ($location->contracts()->exists() || $location->serviceOrders()->exists() || $location->executiveMappings()->exists()) {
            return redirect()
                ->route('locations.index')
                ->with('error', 'This location cannot be deleted while linked contracts, service orders, or executive mappings exist.');
        }

        $locationName = $location->name;
        $location->delete();
        $this->logActivity('locations', 'delete', "Deleted location {$locationName}.", $location->id, request()->user());

        return redirect()
            ->route('locations.index')
            ->with('status', 'Location deleted successfully.');
    }

    private function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'state_id' => ['required', 'exists:states,id'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('locations', 'code')->ignore(request()->route('location')?->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request, int $operationAreaId): array
    {
        return [
            'client_id' => $request->integer('client_id'),
            'state_id' => $request->integer('state_id'),
            'operation_area_id' => $operationAreaId,
            'code' => $request->filled('code') ? strtoupper((string) $request->input('code')) : null,
            'name' => $request->input('name'),
            'city' => null,
            'address' => $request->input('address'),
            'postal_code' => null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function resolveOperationAreaId(int $stateId): ?int
    {
        if ($stateId <= 0) {
            return null;
        }

        return OperationArea::query()
            ->where('state_id', $stateId)
            ->where('is_active', true)
            ->value('id')
            ?? OperationArea::query()->where('state_id', $stateId)->value('id');
    }
}
