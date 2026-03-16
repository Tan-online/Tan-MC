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
        $user = $request->user();
        $statusOptions = ['Active', 'Inactive'];
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'status' => ['nullable', Rule::in($statusOptions)],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $clientId = (int) ($validated['client_id'] ?? 0);
        $status = (string) ($validated['status'] ?? '');

        $contractsQuery = Contract::query()
            ->select(['id', 'client_id', 'location_id', 'contract_no', 'contract_name', 'start_date', 'end_date', 'status', 'scope'])
            ->with(['client:id,name,code', 'location:id,name'])
            ->withCount('serviceOrders')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('contract_no', 'like', "%{$search}%")
                        ->orWhere('contract_name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('scope', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('start_date');

        $this->accessControl()->scopeContracts($contractsQuery, $user);

        $contracts = $contractsQuery
            ->paginate(25)
            ->withQueryString();

        $clientsQuery = Client::query()->where('is_active', true)->orderBy('name');
        $this->accessControl()->scopeClients($clientsQuery, $user);
        $clients = $clientsQuery->get(['id', 'name', 'code']);

        return view('master-data.contracts.index', compact('contracts', 'clients', 'statusOptions', 'search', 'clientId', 'status'));
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

        $resolvedLocationId = $this->resolveLocationId($request->integer('client_id'));

        if (! $resolvedLocationId) {
            return redirect()
                ->route('contracts.index')
                ->withInput()
                ->with('error', 'No active location found for the selected client.')
                ->with('open_modal', 'createContractModal');
        }

        $contract = Contract::create($this->payload($request, $resolvedLocationId));
        $contract->locations()->sync([$resolvedLocationId]);
        $this->logActivity('contracts', 'create', "Created contract {$contract->contract_no}.", $contract, $request->user());

        return redirect()
            ->route('contracts.index')
            ->with('status', 'Contract created successfully.');
    }

    public function update(Request $request, Contract $contract)
    {
        $this->accessControl()->scopeContracts(Contract::query()->whereKey($contract->id), $request->user())->firstOrFail();

        $validator = Validator::make($request->all(), $this->rules($contract));

        if ($validator->fails()) {
            return redirect()
                ->route('contracts.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editContractModal-' . $contract->id);
        }

        $resolvedLocationId = $this->resolveLocationId($request->integer('client_id')) ?: $contract->location_id;

        $contract->update($this->payload($request, $resolvedLocationId));
        $contract->locations()->sync([$resolvedLocationId]);
        $this->logActivity('contracts', 'update', "Updated contract {$contract->contract_no}.", $contract, $request->user());

        return redirect()
            ->route('contracts.index')
            ->with('status', 'Contract updated successfully.');
    }

    public function destroy(Contract $contract)
    {
        $this->accessControl()->scopeContracts(Contract::query()->whereKey($contract->id), request()->user())->firstOrFail();

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
            'contract_no' => ['required', 'string', 'max:50', Rule::unique('contracts', 'contract_no')->ignore($contract?->id)],
            'contract_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'scope' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function payload(Request $request, int $locationId): array
    {
        return [
            'client_id' => $request->integer('client_id'),
            'location_id' => $locationId,
            'contract_no' => strtoupper((string) $request->input('contract_no')),
            'contract_name' => trim((string) $request->input('contract_name')),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'contract_value' => null,
            'status' => $request->input('status'),
            'scope' => $request->input('scope'),
        ];
    }

    private function resolveLocationId(int $clientId): ?int
    {
        if ($clientId <= 0) {
            return null;
        }

        return Location::query()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->value('id')
            ?? Location::query()->where('client_id', $clientId)->value('id');
    }
}
