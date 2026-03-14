@extends('layouts.app')

@section('title', 'Clients | Tan-MC')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Clients</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a class="text-decoration-none" href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Clients</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @include('master-data.import-controls', ['type' => 'clients', 'label' => 'Clients', 'modalId' => 'clientsImportModal'])

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createClientModal">
                <i class="bi bi-plus-circle me-2"></i>Add Client
            </button>
        </div>
    </div>

    @include('master-data.import-report', ['type' => 'clients'])

    <div class="surface-card p-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Client Directory</h2>
                <p class="text-muted mb-0">Manage customer accounts, main contacts, and portfolio coverage.</p>
            </div>

            <form method="GET" action="{{ route('clients.index') }}" class="d-flex gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Search clients">
                </div>
                <button class="btn btn-outline-secondary">Search</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Industry</th>
                        <th>Locations</th>
                        <th>Contracts</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $client->name }}</div>
                                <div class="small text-muted">{{ $client->code ?: 'No code' }}</div>
                            </td>
                            <td>
                                <div>{{ $client->contact_person ?: 'N/A' }}</div>
                                <div class="small text-muted">{{ $client->email ?: $client->phone ?: 'No contact info' }}</div>
                            </td>
                            <td>{{ $client->industry ?: 'N/A' }}</td>
                            <td>{{ $client->locations_count }}</td>
                            <td>{{ $client->contracts_count }}</td>
                            <td>
                                <span class="badge {{ $client->is_active ? 'text-bg-success-subtle text-success border border-success-subtle' : 'text-bg-secondary-subtle text-secondary border border-secondary-subtle' }}">
                                    {{ $client->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
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
                            <td colspan="7" class="text-center py-5 text-muted">No clients found for the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <p class="text-muted small mb-0">Showing {{ $clients->firstItem() ?? 0 }} to {{ $clients->lastItem() ?? 0 }} of {{ $clients->total() }} clients</p>
            {{ $clients->links() }}
        </div>
    </div>

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
                            <div class="col-md-6">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control @if($errors->has('contact_person') && session('open_modal') === 'createClientModal') is-invalid @endif" value="{{ old('contact_person') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Industry</label>
                                <input type="text" name="industry" class="form-control @if($errors->has('industry') && session('open_modal') === 'createClientModal') is-invalid @endif" value="{{ old('industry') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control @if($errors->has('email') && session('open_modal') === 'createClientModal') is-invalid @endif" value="{{ old('email') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control @if($errors->has('phone') && session('open_modal') === 'createClientModal') is-invalid @endif" value="{{ old('phone') }}">
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
                                <div class="col-md-6">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" class="form-control @if($errors->has('contact_person') && session('open_modal') === 'editClientModal-' . $client->id) is-invalid @endif" value="{{ old('contact_person', $client->contact_person) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Industry</label>
                                    <input type="text" name="industry" class="form-control @if($errors->has('industry') && session('open_modal') === 'editClientModal-' . $client->id) is-invalid @endif" value="{{ old('industry', $client->industry) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control @if($errors->has('email') && session('open_modal') === 'editClientModal-' . $client->id) is-invalid @endif" value="{{ old('email', $client->email) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control @if($errors->has('phone') && session('open_modal') === 'editClientModal-' . $client->id) is-invalid @endif" value="{{ old('phone', $client->phone) }}">
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
