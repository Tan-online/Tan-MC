<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));

        $clientsQuery = Client::query()
            ->select(['id', 'name', 'code', 'is_active'])
            ->withCount(['locations', 'contracts'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name');

        $this->accessControl()->scopeClients($clientsQuery, $user);

        $clients = $clientsQuery
            ->paginate(25)
            ->withQueryString();

        return view('master-data.clients.index', compact('clients', 'search', 'status'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return redirect()
                ->route('clients.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'createClientModal');
        }

        $client = Client::create($this->payload($request));
        $this->logActivity('clients', 'create', "Created client {$client->name}.", $client, $request->user());

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client created successfully.');
    }

    public function update(Request $request, Client $client)
    {
        $this->accessControl()->scopeClients(Client::query()->whereKey($client->id), $request->user())->firstOrFail();

        $validator = Validator::make($request->all(), $this->rules($client));

        if ($validator->fails()) {
            return redirect()
                ->route('clients.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editClientModal-' . $client->id);
        }

        $client->update($this->payload($request));
        $this->logActivity('clients', 'update', "Updated client {$client->name}.", $client, $request->user());

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $this->accessControl()->scopeClients(Client::query()->whereKey($client->id), request()->user())->firstOrFail();

        if ($client->locations()->exists() || $client->contracts()->exists() || $client->executiveMappings()->exists()) {
            return redirect()
                ->route('clients.index')
                ->with('error', 'This client cannot be deleted while linked locations, contracts, or executive mappings exist.');
        }

        $clientName = $client->name;
        $client->delete();
        $this->logActivity('clients', 'delete', "Deleted client {$clientName}.", $client->id, request()->user());

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client deleted successfully.');
    }

    private function rules(?Client $client = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'code')->ignore($client?->id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => $request->filled('code') ? strtoupper((string) $request->input('code')) : null,
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'industry' => null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
