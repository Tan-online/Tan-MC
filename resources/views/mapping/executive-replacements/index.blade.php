@extends('layouts.app')

@section('title', 'Executive Replacement | Tan-MC')

@section('content')
    <x-page-header
        title="Executive Replacement"
        subtitle="Reassign operational ownership cleanly across selected client, contract, and location scopes."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Executive Replacement'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExecutiveReplacementModal">
                    <i class="bi bi-arrow-repeat me-2"></i>Replace Executive
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <div class="surface-card p-4 mb-4">
        <form method="GET" action="{{ route('executive-replacements.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($clientId === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contract</label>
                <select name="contract_id" class="form-select">
                    <option value="">All contracts</option>
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected($contractId === $contract->id)>{{ $contract->contract_no }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Location</label>
                <select name="location_id" class="form-select">
                    <option value="">All locations</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected($locationId === $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-outline-primary">
                    <i class="bi bi-funnel me-2"></i>Filter Mappings
                </button>
            </div>
        </form>
    </div>

    <div class="surface-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Current Executive Coverage</h2>
                <p class="text-muted mb-0">Preview the mappings that will be affected by the selected replacement filters.</p>
            </div>
            <span class="badge rounded-pill text-bg-light border">{{ $matchingMappings->count() }} matching mappings</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contract</th>
                        <th>Location</th>
                        <th>Executive</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matchingMappings as $mapping)
                        <tr>
                            <td>{{ $mapping->client?->name }}</td>
                            <td>{{ $mapping->contract?->contract_no ?: 'All contracts' }}</td>
                            <td>{{ $mapping->location?->name ?: 'All locations' }}</td>
                            <td>{{ $mapping->executiveUser?->name ?: $mapping->executive_name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">No mappings matched the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="surface-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Replacement History</h2>
                <p class="text-muted mb-0">Track executive changes with effective dates for audit and compliance continuity.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Effective Date</th>
                        <th>Client</th>
                        <th>Contract</th>
                        <th>Location</th>
                        <th>Old Executive</th>
                        <th>New Executive</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $entry)
                        <tr>
                            <td>{{ $entry->effective_date?->format('d M Y') }}</td>
                            <td>{{ $entry->client?->name }}</td>
                            <td>{{ $entry->contract?->contract_no ?: 'All contracts' }}</td>
                            <td>{{ $entry->location?->name ?: 'All locations' }}</td>
                            <td>{{ $entry->oldExecutive?->name ?: 'Unassigned' }}</td>
                            <td>{{ $entry->newExecutive?->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No replacement history recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <p class="text-muted small mb-0">Showing {{ $history->firstItem() ?? 0 }} to {{ $history->lastItem() ?? 0 }} of {{ $history->total() }} replacements</p>
            {{ $history->links() }}
        </div>
    </div>

    <div class="modal fade" id="createExecutiveReplacementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('executive-replacements.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Replace Executive</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Client</label>
                                <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === 'createExecutiveReplacementModal') is-invalid @endif" required>
                                    <option value="">Select client</option>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id', $clientId) == $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Contract</label>
                                <select name="contract_id" class="form-select @if($errors->has('contract_id') && session('open_modal') === 'createExecutiveReplacementModal') is-invalid @endif">
                                    <option value="">All contracts</option>
                                    @foreach ($contracts as $contract)
                                        <option value="{{ $contract->id }}" @selected(old('contract_id', $contractId) == $contract->id)>{{ $contract->contract_no }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Location</label>
                                <select name="location_id" class="form-select @if($errors->has('location_id') && session('open_modal') === 'createExecutiveReplacementModal') is-invalid @endif">
                                    <option value="">All locations</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('location_id', $locationId) == $location->id)>{{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Old Executive</label>
                                <select name="old_executive_id" class="form-select @if($errors->has('old_executive_id') && session('open_modal') === 'createExecutiveReplacementModal') is-invalid @endif">
                                    <option value="">Any current executive</option>
                                    @foreach ($executives as $executive)
                                        <option value="{{ $executive->id }}" @selected(old('old_executive_id') == $executive->id)>{{ $executive->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Executive</label>
                                <select name="new_executive_id" class="form-select @if($errors->has('new_executive_id') && session('open_modal') === 'createExecutiveReplacementModal') is-invalid @endif" required>
                                    <option value="">Select executive</option>
                                    @foreach ($executives as $executive)
                                        <option value="{{ $executive->id }}" @selected(old('new_executive_id') == $executive->id)>{{ $executive->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Effective Date</label>
                                <input type="date" name="effective_date" class="form-control @if($errors->has('effective_date') && session('open_modal') === 'createExecutiveReplacementModal') is-invalid @endif" value="{{ old('effective_date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Notes</label>
                                <input type="text" name="notes" class="form-control @if($errors->has('notes') && session('open_modal') === 'createExecutiveReplacementModal') is-invalid @endif" value="{{ old('notes') }}" placeholder="Optional handover or scope note">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Apply Replacement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
