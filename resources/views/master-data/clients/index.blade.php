@extends('layouts.app')

@section('title', 'Clients | Tan-MC')

@section('content')
    <x-page-header
        title="Clients"
        subtitle="Compact client master for account ownership, coverage, and contact management."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Clients'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                @include('master-data.import-controls', ['type' => 'clients', 'label' => 'Clients', 'modalId' => 'clientsImportModal'])
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClientModal">
                    <i class="bi bi-plus-circle me-2"></i>Add Client
                </button>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    @include('master-data.import-report', ['type' => 'clients'])

    <x-table title="Client Directory" description="Manage customer accounts and portfolio coverage in a compact list." :loading="true" :columns="6" :rows="5">
        <x-slot:toolbar>
            <form method="GET" action="{{ route('clients.index') }}" class="d-flex flex-wrap gap-2" data-loading-form>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search by client name or code">
                </div>
                <select name="status" class="form-select">
                    <option value="">All status</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                </select>
                <button class="btn btn-outline-secondary">Search</button>
                <a href="{{ route('exports.master-data', ['type' => 'clients'] + request()->query()) }}" class="btn btn-outline-primary" data-loading-trigger>
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
            </form>
        </x-slot:toolbar>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Client Code</th>
                        <th>Client Name</th>
                        <th>Locations</th>
                        <th>Contracts</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td class="fw-semibold">{{ $client->code ?: 'N/A' }}</td>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->locations_count }}</td>
                            <td>{{ $client->contracts_count }}</td>
                            <td>
                                <span class="badge {{ $client->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $client->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewClientModal-{{ $client->id }}">View</button>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editClientModal-{{ $client->id }}">Edit</button>
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Delete this client?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No clients found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $clients->firstItem() ?? 0 }} to {{ $clients->lastItem() ?? 0 }} of {{ $clients->total() }} clients</p>
            {{ $clients->links() }}
        </x-slot:footer>
    </x-table>

    <div class="modal fade" id="createClientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('clients.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Add Client</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Client Name</label>
                                <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'createClientModal') is-invalid @endif" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Code</label>
                                <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'createClientModal') is-invalid @endif" value="{{ old('code') }}" placeholder="CL-001">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="clientActiveCreate" name="is_active" value="1" @checked(session('open_modal') === 'createClientModal' ? old('is_active') : true)>
                                    <label class="form-check-label" for="clientActiveCreate">Active client</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach ($clients as $client)
        <div class="modal fade" id="viewClientModal-{{ $client->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Client Details</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><strong>Client Name:</strong> {{ $client->name }}</div>
                            <div class="col-md-6"><strong>Client Code:</strong> {{ $client->code ?: 'N/A' }}</div>
                            <div class="col-md-6"><strong>Locations:</strong> {{ $client->locations_count }}</div>
                            <div class="col-md-6"><strong>Contracts:</strong> {{ $client->contracts_count }}</div>
                            <div class="col-md-6"><strong>Status:</strong> {{ $client->is_active ? 'Active' : 'Inactive' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="editClientModal-{{ $client->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <form method="POST" action="{{ route('clients.update', $client) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h5 mb-0">Edit Client</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Client Name</label>
                                    <input type="text" name="name" class="form-control @if($errors->has('name') && session('open_modal') === 'editClientModal-' . $client->id) is-invalid @endif" value="{{ old('name', $client->name) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Code</label>
                                    <input type="text" name="code" class="form-control @if($errors->has('code') && session('open_modal') === 'editClientModal-' . $client->id) is-invalid @endif" value="{{ old('code', $client->code) }}">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="clientActive-{{ $client->id }}" name="is_active" value="1" @checked(session('open_modal') === 'editClientModal-' . $client->id ? old('is_active') : $client->is_active)>
                                        <label class="form-check-label" for="clientActive-{{ $client->id }}">Active client</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Client</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
