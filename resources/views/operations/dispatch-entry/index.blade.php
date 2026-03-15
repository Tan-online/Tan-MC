@extends('layouts.app')

@section('title', 'Dispatch Entry | Tan-MC')

@section('content')
    <x-page-header
        title="Dispatch Entry"
        subtitle="Track pending dispatch work orders and capture dispatch execution."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Dispatch Entry'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <form method="GET" action="{{ route('dispatch-entry.index') }}" class="d-flex gap-2">
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (['pending', 'dispatched', 'closed'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ ucfirst($statusOption) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-secondary">Filter</button>
                </form>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <x-table title="Dispatch Queue" description="Monitor work orders waiting for dispatch and recently processed entries.">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Client</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Dispatched At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dispatchEntries as $entry)
                        <tr>
                            <td class="fw-semibold">{{ $entry->serviceOrder?->order_no ?: 'N/A' }}</td>
                            <td>{{ $entry->serviceOrder?->contract?->client?->name ?: 'N/A' }}</td>
                            <td>{{ $entry->serviceOrder?->location?->name ?: 'N/A' }}</td>
                            <td><span class="badge text-bg-light border text-uppercase">{{ $entry->status }}</span></td>
                            <td>{{ $entry->dispatched_at?->format('d M Y h:i A') ?: 'Pending' }}</td>
                            <td class="text-end">
                                @if ($entry->status === 'pending' && userCan('service_orders.dispatch'))
                                    <form method="POST" action="{{ route('dispatch-entry.dispatch', $entry) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-primary">Dispatch</button>
                                    </form>
                                @else
                                    <span class="text-muted small">{{ $entry->dispatchedBy?->name ?: 'No action' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No dispatch entries available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $dispatchEntries->firstItem() ?? 0 }} to {{ $dispatchEntries->lastItem() ?? 0 }} of {{ $dispatchEntries->total() }} dispatch entries</p>
            {{ $dispatchEntries->links() }}
        </x-slot:footer>
    </x-table>
@endsection
