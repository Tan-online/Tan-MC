@extends('layouts.app')

@section('title', 'Operations Dashboard | Tan-MC')

@section('content')
    <x-page-header
        title="Operations Dashboard"
        subtitle="Execution-focused view for dispatch handling, daily muster intake, and location activity."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Operations Dashboard'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <a href="{{ route('bulk-receive.index') }}" class="btn btn-primary">
                    <i class="bi bi-inboxes me-2"></i>Open Bulk Receive
                </a>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Pending Dispatch</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($pendingDispatchCount) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Today's Muster Submission</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($todayMusterSubmissionCount) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Location Activity</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($activeLocationCount) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Team Status</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($activeTeamsCount) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-8">
            <div class="surface-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Location Activity</h2>
                        <p class="text-muted mb-0">Most recent location-level compliance actions from the muster queue.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Location</th>
                                <th>Contract</th>
                                <th>Cycle</th>
                                <th>Status</th>
                                <th class="text-end">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($locationActivities as $activity)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $activity->location?->name ?: 'Unknown location' }}</div>
                                        <div class="text-muted small">{{ $activity->location?->city ?: 'No city' }}</div>
                                    </td>
                                    <td>{{ $activity->contract?->contract_no ?: 'N/A' }}</td>
                                    <td>{{ $activity->musterCycle?->cycle_label ?: 'N/A' }}</td>
                                    <td><span class="badge text-bg-light border">{{ $activity->status }}</span></td>
                                    <td class="text-end text-muted small">{{ optional($activity->last_action_at)->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">No location activity recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-4">
            <div class="surface-card p-4 mb-4">
                <div class="mb-4">
                    <h2 class="h5 fw-bold mb-1">Team Status Overview</h2>
                    <p class="text-muted mb-0">Live team availability for the field operations setup.</p>
                </div>
                <div style="height: 260px;">
                    <canvas id="teamStatusChart"></canvas>
                </div>
            </div>

            <div class="surface-card p-4">
                <div class="mb-4">
                    <h2 class="h5 fw-bold mb-1">Team Status</h2>
                    <p class="text-muted mb-0">Current active and inactive team setup snapshot.</p>
                </div>

                <div class="d-flex flex-column gap-3">
                    @forelse ($teamStatuses as $team)
                        <div class="border rounded-4 p-3">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $team->name }}</div>
                                    <div class="text-muted small">{{ $team->operationArea?->name ?: 'No area assigned' }}</div>
                                </div>
                                <span class="badge {{ $team->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $team->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="text-muted small mt-2">Members: {{ number_format($team->members_count ?? 0) }}</div>
                        </div>
                    @empty
                        <div class="border rounded-4 p-3 text-muted">No teams available.</div>
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
            const chartData = @json($teamStatusOverview);
            const canvas = document.getElementById('teamStatusChart');

            if (!canvas) {
                return;
            }

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: chartData.datasets.map(function (dataset) {
                        return {
                            ...dataset,
                            backgroundColor: ['rgba(25, 135, 84, 0.78)', 'rgba(108, 117, 125, 0.68)'],
                            borderWidth: 0
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
                    cutout: '68%'
                }
            });
        });
    </script>
@endpush
