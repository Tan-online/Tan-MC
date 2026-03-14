@extends('layouts.app')

@section('title', 'System Overview | Tan-MC')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">System Overview</h1>
            <p class="text-muted mb-0">Enterprise summary for platform health, onboarding flow, and administrative activity.</p>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-bar-chart-line me-2"></i>Open Reports
        </a>
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
                <div class="text-muted small text-uppercase fw-semibold mb-2">Total Locations</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalLocations) }}</div>
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
                <div class="text-muted small text-uppercase fw-semibold mb-2">Total Users</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($totalUsers) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-7">
            <div class="surface-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h5 fw-bold mb-1">System Usage Overview</h2>
                        <p class="text-muted mb-0">Current record distribution across the core ERP modules.</p>
                    </div>
                </div>
                <div style="height: 320px;">
                    <canvas id="systemUsageChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-5">
            <div class="surface-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Recent Activity Log</h2>
                        <p class="text-muted mb-0">Latest setup actions across master and transactional modules.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th>Activity</th>
                                <th class="text-end">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentActivityLog as $activity)
                                <tr>
                                    <td class="fw-semibold">{{ $activity['module'] }}</td>
                                    <td>
                                        <div>{{ $activity['action'] }}</div>
                                        <div class="text-muted small">{{ $activity['label'] }}</div>
                                    </td>
                                    <td class="text-end text-muted small">{{ optional($activity['occurred_at'])->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">No recent activity recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartData = @json($systemUsageOverview);
            const canvas = document.getElementById('systemUsageChart');

            if (!canvas) {
                return;
            }

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: chartData.datasets.map(function (dataset) {
                        return {
                            ...dataset,
                            backgroundColor: [
                                'rgba(31, 94, 255, 0.78)',
                                'rgba(15, 92, 77, 0.78)',
                                'rgba(255, 193, 7, 0.82)',
                                'rgba(13, 110, 253, 0.58)',
                                'rgba(220, 53, 69, 0.78)'
                            ],
                            borderRadius: 12,
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
@endpush
