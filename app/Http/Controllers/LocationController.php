<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Location;
use App\Models\OperationArea;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $stateId = (int) ($validated['state_id'] ?? 0);

        $locations = Location::query()
            ->select(['id', 'client_id', 'state_id', 'operation_area_id', 'name', 'city', 'address', 'postal_code', 'is_active'])
            ->with(['client:id,name,code', 'state:id,name,code', 'operationArea:id,name'])
            ->withCount(['contracts', 'serviceOrders'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('postal_code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('state', fn ($stateQuery) => $stateQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('operationArea', fn ($areaQuery) => $areaQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($stateId > 0, fn ($query) => $query->where('state_id', $stateId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $clients = Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $states = State::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $operationAreas = OperationArea::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'state_id']);

        return view('master-data.locations.index', compact('locations', 'clients', 'states', 'operationAreas', 'search', 'stateId'));
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

        $location = Location::create($this->payload($request));
        $this->logActivity('locations', 'create', "Created location {$location->name}.", $location, $request->user());

        return redirect()
            ->route('locations.index')
            ->with('status', 'Location created successfully.');
    }

    public function update(Request $request, Location $location)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('locations.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editLocationModal-' . $location->id);
        }

        $location->update($this->payload($request));
        $this->logActivity('locations', 'update', "Updated location {$location->name}.", $location, $request->user());

        return redirect()
            ->route('locations.index')
            ->with('status', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
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
            'operation_area_id' => ['required', 'exists:operation_areas,id'],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'client_id' => $request->integer('client_id'),
            'state_id' => $request->integer('state_id'),
            'operation_area_id' => $request->integer('operation_area_id'),
            'name' => $request->input('name'),
            'city' => $request->input('city'),
            'address' => $request->input('address'),
            'postal_code' => $request->input('postal_code'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
