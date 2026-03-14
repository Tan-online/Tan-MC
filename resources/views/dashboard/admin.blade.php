@extends('layouts.app')

@section('title', 'Admin Dashboard | Tan-MC')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Admin Dashboard</h1>
            <p class="text-muted mb-0">Operational summary across client structure, compliance exposure, and dispatch review flow.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-bar-chart-line me-2"></i>Reports
            </a>
            <a href="{{ route('bulk-receive.index') }}" class="btn btn-primary">
                <i class="bi bi-inboxes me-2"></i>Bulk Receive
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Total Clients</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalClients) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Total Contracts</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalContracts) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Service Orders</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalServiceOrders) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Pending Reviews</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($pendingReviewsCount) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-8">
            <div class="surface-card p-4 mb-4">
                <div class="mb-4">
                    <h2 class="h5 fw-bold mb-1">State Compliance</h2>
                    <p class="text-muted mb-0">Top compliance performance by state for the active compliance cycle.</p>
                </div>
                <div style="height: 320px;">
                    <canvas id="stateComplianceChart"></canvas>
                </div>
            </div>

            <div class="surface-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Recent Service Orders</h2>
                        <p class="text-muted mb-0">Latest work requests entering the operational pipeline.</p>
                    </div>
                    <a href="{{ route('service-orders.index') }}" class="btn btn-outline-secondary btn-sm">Open Module</a>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Client</th>
                                <th>Location</th>
                                <th>Team</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentServiceOrders as $serviceOrder)
                                <tr>
                                    <td class="fw-semibold">{{ $serviceOrder->order_no }}</td>
                                    <td>{{ $serviceOrder->contract?->client?->name ?: 'N/A' }}</td>
                                    <td>{{ $serviceOrder->location?->name ?: 'N/A' }}</td>
                                    <td>{{ $serviceOrder->team?->name ?: 'Unassigned' }}</td>
                                    <td><span class="badge text-bg-light border">{{ $serviceOrder->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">No recent service orders available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="surface-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Late Submission Alerts</h2>
                        <p class="text-muted mb-0">Items that need immediate operational follow-up.</p>
                    </div>
                    <a href="{{ route('bulk-receive.index') }}" class="small text-decoration-none">Open queue</a>
                </div>

                <div class="d-flex flex-column gap-3">
                    @forelse ($lateAlerts as $alert)
                        <div class="border rounded-4 p-3">
                            <div class="fw-semibold">{{ $alert->location?->name ?: 'Unknown location' }}</div>
                            <div class="text-muted small">{{ $alert->contract?->contract_no ?: 'No contract' }}</div>
                            <div class="text-muted small">{{ $alert->location?->city ?: 'No city' }}</div>
                        </div>
                    @empty
                        <div class="border rounded-4 p-3 text-muted">No late submission alerts right now.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartData = @json($stateComplianceChart);
            const canvas = document.getElementById('stateComplianceChart');

            if (!canvas || !chartData.labels || chartData.labels.length === 0) {
                return;
            }

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: chartData.datasets.map(function (dataset, index) {
                        const palette = [
                            ['rgba(31, 94, 255, 0.75)', 'rgba(31, 94, 255, 1)'],
                            ['rgba(15, 92, 77, 0.75)', 'rgba(15, 92, 77, 1)'],
                            ['rgba(220, 53, 69, 0.75)', 'rgba(220, 53, 69, 1)'],
                            ['rgba(255, 193, 7, 0.8)', 'rgba(255, 193, 7, 1)'],
                        ][index % 4];

                        return {
                            ...dataset,
                            backgroundColor: palette[0],
                            borderColor: palette[1],
                            borderWidth: 2,
                            borderRadius: 10,
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
@endpush
