@extends('layouts.app')

@section('title', 'Dashboard | Tan-MC')

@push('styles')
    <style>
        .dashboard-container {
            min-height: auto;
            height: auto;
            max-height: none;
            overflow: visible;
            padding-right: 0.25rem;
        }

        .dashboard-container .row {
            align-items: flex-start;
        }

        .dashboard-chart {
            position: relative;
            min-height: 240px;
        }

        .dashboard-chart.dashboard-chart-lg {
            min-height: 280px;
        }

        @media (max-width: 1199.98px) {
            .dashboard-container {
                max-height: none;
                overflow: visible;
                padding-right: 0;
            }
        }
    </style>
@endpush

@section('content')
    <div class="dashboard-container">
        <x-page-header
            title="Dashboard"
            subtitle="Operational control view for compliance, service execution, and review queues."
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Dashboard'],
            ]"
        >
            <x-slot:actions>
                <x-action-buttons>
                    <a href="{{ route('reports.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-bar-chart-line me-2"></i>View Reports
                    </a>
                    <a href="{{ route('bulk-receive.index') }}" class="btn btn-primary">
                        <i class="bi bi-inboxes me-2"></i>Open Bulk Receive
                    </a>
                </x-action-buttons>
            </x-slot:actions>
        </x-page-header>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card metric-card p-4">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-2">Active Clients</div>
                            <div class="display-6 fw-bold mb-1">{{ number_format($activeClientsCount) }}</div>
                            <div class="text-success small"><i class="bi bi-database-check"></i> Live count from clients</div>
                        </div>
                        <div class="metric-icon">
                            <i class="bi bi-buildings fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card metric-card p-4">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-2">Service Orders</div>
                            <div class="display-6 fw-bold mb-1">{{ number_format($serviceOrdersCount) }}</div>
                            <div class="text-success small"><i class="bi bi-database-check"></i> Live count from service orders</div>
                        </div>
                        <div class="metric-icon">
                            <i class="bi bi-clipboard2-pulse fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card metric-card p-4">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-2">Teams on Field</div>
                            <div class="display-6 fw-bold mb-1">{{ number_format($teamsOnFieldCount) }}</div>
                            <div class="text-primary small"><i class="bi bi-database-check"></i> Live count from teams</div>
                        </div>
                        <div class="metric-icon">
                            <i class="bi bi-people fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card metric-card p-4">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-2">Pending Reviews</div>
                            <div class="display-6 fw-bold mb-1">{{ number_format($pendingReviewsCount) }}</div>
                            <div class="text-warning small"><i class="bi bi-hourglass-split"></i> Pending dispatch review items</div>
                        </div>
                        <div class="metric-icon">
                            <i class="bi bi-shield-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card p-4">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Expected Muster</div>
                    <div class="display-6 fw-bold mb-1">{{ number_format($musterSummary['expected']) }}</div>
                    <div class="text-muted small">Current month expected compliance submissions</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card p-4">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Received Muster</div>
                    <div class="display-6 fw-bold mb-1 text-primary">{{ number_format($musterSummary['received']) }}</div>
                    <div class="text-muted small">Submissions captured on time or after cycle close</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card p-4">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Pending Muster</div>
                    <div class="display-6 fw-bold mb-1 text-warning">{{ number_format($musterSummary['pending']) }}</div>
                    <div class="text-muted small">Locations still waiting for submission intake</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="surface-card p-4">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Late Muster</div>
                    <div class="display-6 fw-bold mb-1 text-danger">{{ number_format($musterSummary['late']) }}</div>
                    <div class="text-muted small">Cycle closed without timely receipt</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xxl-8">
                <div class="surface-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">State Compliance</h2>
                            <p class="text-muted mb-0">State-wise compliance status for the current cycle.</p>
                        </div>
                    </div>
                    <div class="dashboard-chart">
                        <canvas id="stateComplianceChart"></canvas>
                    </div>
                </div>

                <div class="surface-card p-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Recent Sales Orders</h2>
                            <p class="text-muted mb-0">Track incoming work requests, assigned teams, and approval status.</p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="search" class="form-control border-start-0" placeholder="Search sales orders" data-table-search>
                            </div>
                            <button class="btn btn-outline-secondary">
                                <i class="bi bi-download me-2"></i>Export
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-3">
                            <thead>
                                <tr>
                                    <th>Sales Order</th>
                                    <th>Client</th>
                                    <th>Location</th>
                                    <th>Team</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentServiceOrders as $serviceOrder)
                                    <tr data-search-row>
                                        <td class="fw-semibold">{{ $serviceOrder->order_no }}</td>
                                        <td>{{ $serviceOrder->contract?->client?->name ?: 'N/A' }}</td>
                                        <td>{{ $serviceOrder->location?->name ?: 'N/A' }}</td>
                                        <td>{{ $serviceOrder->team?->name ?: 'Unassigned' }}</td>
                                        <td><span class="badge text-bg-light border">{{ $serviceOrder->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">No recent sales orders available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <p class="text-muted small mb-0">Showing the latest {{ $recentServiceOrders->count() }} sales orders</p>

                        <nav aria-label="Orders pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item active"><a class="page-link" href="{{ route('service-orders.index') }}">Open Sales Orders</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xxl-4">
                <div class="surface-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Executive Performance</h2>
                        <a href="{{ route('reports.index', ['report' => 'executive-performance']) }}" class="small text-decoration-none">View report</a>
                    </div>
                    <div class="dashboard-chart">
                        <canvas id="executivePerformanceChart"></canvas>
                    </div>
                </div>

                <div class="surface-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Late Submission Alerts</h2>
                        <a href="{{ route('bulk-receive.index', ['status' => 'Late']) }}" class="small text-decoration-none">View all</a>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        @forelse ($lateAlerts as $alert)
                            <div class="border rounded-4 p-3">
                                <div class="fw-semibold">{{ $alert->location?->name ?: 'Unknown location' }}</div>
                                <div class="text-muted small mb-2">{{ $alert->contract?->contract_no ?: 'No contract number' }}{{ $alert->location?->city ? ' - '.$alert->location->city : '' }}</div>
                                <span class="badge text-bg-danger-subtle text-danger border border-danger-subtle">Late submission pending action</span>
                            </div>
                        @empty
                            <div class="border rounded-4 p-3 text-muted">No late submission alerts right now.</div>
                        @endforelse
                    </div>
                </div>

                <div class="surface-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 fw-bold mb-0">Monthly Compliance Trend</h2>
                        <span class="badge rounded-pill text-bg-light border">Live</span>
                    </div>

                    <div class="dashboard-chart dashboard-chart-lg">
                        <canvas id="monthlyComplianceTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const stateChartData = @json($stateComplianceChart);
            const executiveChartData = @json($executivePerformanceChart);
            const monthlyTrendData = @json($monthlyTrendChart);

            const renderChart = function (elementId, type, data, options) {
                const canvas = document.getElementById(elementId);

                if (!canvas || !data.labels || data.labels.length === 0) {
                    return;
                }

                new Chart(canvas, {
                    type: type,
                    data: {
                        labels: data.labels,
                        datasets: data.datasets.map(function (dataset, index) {
                            const palette = [
                                ['rgba(31, 94, 255, 0.75)', 'rgba(31, 94, 255, 1)'],
                                ['rgba(15, 92, 77, 0.75)', 'rgba(15, 92, 77, 1)'],
                                ['rgba(220, 53, 69, 0.75)', 'rgba(220, 53, 69, 1)'],
                                ['rgba(255, 193, 7, 0.75)', 'rgba(255, 193, 7, 1)'],
                            ][index % 4];

                            return {
                                ...dataset,
                                backgroundColor: palette[0],
                                borderColor: palette[1],
                                borderWidth: 2,
                                borderRadius: type === 'bar' ? 8 : 0,
                                tension: 0.35,
                                fill: false,
                            };
                        }),
                    },
                    options: options,
                });
            };

            renderChart('stateComplianceChart', 'bar', stateChartData, {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true },
                },
                plugins: {
                    legend: { position: 'bottom' },
                },
            });

            renderChart('executivePerformanceChart', 'bar', executiveChartData, {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(15, 39, 71, 0.08)' } },
                    y: { grid: { display: false } },
                },
                plugins: {
                    legend: { position: 'bottom' },
                },
            });

            renderChart('monthlyComplianceTrendChart', 'line', monthlyTrendData, {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true },
                },
                plugins: {
                    legend: { position: 'bottom' },
                },
            });
        });
    </script>
@endpush
