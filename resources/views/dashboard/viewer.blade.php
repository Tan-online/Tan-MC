@extends('layouts.app')

@section('title', 'Dashboard | Tan-MC')

@section('content')
    <x-page-header
        title="Dashboard"
        subtitle="Read-only summary for enterprise visibility across clients, contracts, and service orders."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Dashboard'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-bar-chart-line me-2"></i>Open Reports
                </a>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Total Clients</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalClients) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Total Locations</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalLocations) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Total Contracts</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalContracts) }}</div>
            </div>
        </div>
    </div>

    <div class="surface-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h5 fw-bold mb-1">Recent Service Orders</h2>
                <p class="text-muted mb-0">Latest service orders available for read-only operational visibility.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Client</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentServiceOrders as $serviceOrder)
                        <tr>
                            <td class="fw-semibold">{{ $serviceOrder->order_no }}</td>
                            <td>{{ $serviceOrder->contract?->client?->name ?: 'N/A' }}</td>
                            <td>{{ $serviceOrder->location?->name ?: 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-5 text-muted">No recent service orders available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
