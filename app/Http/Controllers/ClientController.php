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
        $search = trim((string) $request->string('search'));

        $clients = Client::query()
            ->withCount(['locations', 'contracts'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('industry', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('master-data.clients.index', compact('clients', 'search'));
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

        Client::create($this->payload($request));

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client created successfully.');
    }

    public function update(Request $request, Client $client)
    {
        $validator = Validator::make($request->all(), $this->rules($client));

        if ($validator->fails()) {
            return redirect()
                ->route('clients.index')
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', 'editClientModal-' . $client->id);
        }

        $client->update($this->payload($request));

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        if ($client->locations()->exists() || $client->contracts()->exists() || $client->executiveMappings()->exists()) {
            return redirect()
                ->route('clients.index')
                ->with('error', 'This client cannot be deleted while linked locations, contracts, or executive mappings exist.');
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client deleted successfully.');
    }

    private function rules(?Client $client = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'code')->ignore($client?->id)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'industry' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'code' => $request->filled('code') ? strtoupper((string) $request->input('code')) : null,
            'contact_person' => $request->input('contact_person'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'industry' => $request->input('industry'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
