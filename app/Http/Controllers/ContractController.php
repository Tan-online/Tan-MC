<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $statusOptions = ['Draft', 'Active', 'Expired', 'Closed'];
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'status' => ['nullable', Rule::in($statusOptions)],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $clientId = (int) ($validated['client_id'] ?? 0);
        $status = (string) ($validated['status'] ?? '');

        $contracts = Contract::query()
            ->select(['id', 'client_id', 'location_id', 'contract_no', 'start_date', 'end_date', 'contract_value', 'status', 'scope'])
            ->with(['client:id,name,code', 'location:id,name,city', 'locations:id,name,city'])
            ->withCount('locations')
            ->withCount('serviceOrders')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('contract_no', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('scope', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('location', fn ($locationQuery) => $locationQuery->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"));
                });
            })
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('start_date')
            ->paginate(25)
            ->withQueryString();

        $clients = Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
        $locations = Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'client_id', 'name', 'city']);

        return view('master-data.contracts.index', compact('contracts', 'clients', 'locations', 'statusOptions', 'search', 'clientId', 'status'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('contracts.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createContractModal');
        }

        $contract = Contract::create($this->payload($request));
        $contract->locations()->sync($this->locationIds($request, $contract->location_id));
        $this->logActivity('contracts', 'create', "Created contract {$contract->contract_no}.", $contract, $request->user());

        return redirect()
            ->route('contracts.index')
            ->with('status', 'Contract created successfully.');
    }

    public function update(Request $request, Contract $contract)
    {
        $validator = Validator::make($request->all(), $this->rules($contract));

        if ($validator->fails()) {
            return redirect()
                ->route('contracts.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editContractModal-' . $contract->id);
        }

        $contract->update($this->payload($request));
        $contract->locations()->sync($this->locationIds($request, $contract->location_id));
        $this->logActivity('contracts', 'update', "Updated contract {$contract->contract_no}.", $contract, $request->user());

        return redirect()
            ->route('contracts.index')
            ->with('status', 'Contract updated successfully.');
    }

    public function destroy(Contract $contract)
    {
        if ($contract->serviceOrders()->exists()) {
            return redirect()
                ->route('contracts.index')
                ->with('error', 'This contract cannot be deleted while service orders are linked to it.');
        }

        $contractNo = $contract->contract_no;
        $contract->delete();
        $this->logActivity('contracts', 'delete', "Deleted contract {$contractNo}.", $contract->id, request()->user());

        return redirect()
            ->route('contracts.index')
            ->with('status', 'Contract deleted successfully.');
    }

    private function rules(?Contract $contract = null): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
            'contract_no' => ['required', 'string', 'max:50', Rule::unique('contracts', 'contract_no')->ignore($contract?->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Draft', 'Active', 'Expired', 'Closed'])],
            'scope' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'client_id' => $request->integer('client_id'),
            'location_id' => $request->integer('location_id'),
            'contract_no' => strtoupper((string) $request->input('contract_no')),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'contract_value' => $request->filled('contract_value') ? $request->input('contract_value') : null,
            'status' => $request->input('status'),
            'scope' => $request->input('scope'),
        ];
    }

    private function locationIds(Request $request, int $primaryLocationId): array
    {
        $locationIds = collect($request->input('location_ids', []))
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->push($primaryLocationId)
            ->unique()
            ->values()
            ->all();

        return $locationIds;
    }
}
