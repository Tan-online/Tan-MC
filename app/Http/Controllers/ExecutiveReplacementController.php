<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ExecutiveMapping;
use App\Models\ExecutiveReplacementHistory;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExecutiveReplacementController extends Controller
{
    public function index(Request $request)
    {
        $clientId = $request->integer('client_id');
        $contractId = $request->integer('contract_id');
        $locationId = $request->integer('location_id');

        $clients = Client::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $contracts = Contract::query()
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('contract_no')
            ->get(['id', 'client_id', 'contract_no']);
        $locations = Location::query()
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->orderBy('name')
            ->get(['id', 'client_id', 'name', 'city']);
        $executives = User::query()
            ->with('role:id,name,slug')
            ->where('status', 'Active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'executive'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role_id']);

        $matchingMappings = ExecutiveMapping::query()
            ->with(['client:id,name', 'contract:id,contract_no', 'location:id,name,city', 'executiveUser:id,name'])
            ->when($clientId > 0, fn ($query) => $query->where('client_id', $clientId))
            ->when($contractId > 0, fn ($query) => $query->where('contract_id', $contractId))
            ->when($locationId > 0, fn ($query) => $query->where('location_id', $locationId))
            ->orderBy('executive_name')
            ->get();

        $history = ExecutiveReplacementHistory::query()
            ->with(['client:id,name', 'contract:id,contract_no', 'location:id,name,city', 'oldExecutive:id,name', 'newExecutive:id,name'])
            ->latest('effective_date')
            ->paginate(10)
            ->withQueryString();

        return view('mapping.executive-replacements.index', compact(
            'clients',
            'contracts',
            'locations',
            'executives',
            'matchingMappings',
            'history',
            'clientId',
            'contractId',
            'locationId',
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => ['required', 'exists:clients,id'],
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'old_executive_id' => ['nullable', 'exists:users,id'],
            'new_executive_id' => ['required', 'exists:users,id', 'different:old_executive_id'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('executive-replacements.index', $request->only(['client_id', 'contract_id', 'location_id']))
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createExecutiveReplacementModal');
        }

        $newExecutive = User::query()->findOrFail($request->integer('new_executive_id'));

        $mappings = ExecutiveMapping::query()
            ->where('client_id', $request->integer('client_id'))
            ->when($request->filled('contract_id'), fn ($query) => $query->where('contract_id', $request->integer('contract_id')))
            ->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->integer('location_id')))
            ->when($request->filled('old_executive_id'), fn ($query) => $query->where('executive_user_id', $request->integer('old_executive_id')))
            ->where('is_active', true)
            ->get();

        if ($mappings->isEmpty()) {
            return redirect()
                ->route('executive-replacements.index', $request->only(['client_id', 'contract_id', 'location_id']))
                ->with('error', 'No active executive mappings matched the selected filters.');
        }

        DB::transaction(function () use ($mappings, $request, $newExecutive): void {
            foreach ($mappings as $mapping) {
                ExecutiveReplacementHistory::query()->create([
                    'client_id' => $mapping->client_id,
                    'contract_id' => $mapping->contract_id,
                    'location_id' => $mapping->location_id,
                    'old_executive_id' => $mapping->executive_user_id,
                    'new_executive_id' => $newExecutive->id,
                    'replaced_by_user_id' => $request->user()->id,
                    'effective_date' => $request->input('effective_date'),
                    'notes' => $request->input('notes'),
                ]);

                $mapping->update([
                    'executive_user_id' => $newExecutive->id,
                    'executive_name' => $newExecutive->name,
                    'email' => $newExecutive->email,
                    'phone' => $newExecutive->phone,
                ]);
            }
        });

        return redirect()
            ->route('executive-replacements.index', $request->only(['client_id', 'contract_id', 'location_id']))
            ->with('status', 'Executive replacement applied successfully.');
    }
}
