@extends('layouts.app')

@section('title', 'My Teams | Tan-MC')

@push('styles')
    <style>
        .workspace-team-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .workspace-team-metric {
            border: 1px solid rgba(219, 228, 240, 0.95);
            border-radius: 14px;
            padding: 1rem;
            background: #f9fbff;
        }

        .workspace-team-metric-label {
            color: #63768c;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .workspace-team-metric-value {
            color: #17304f;
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1.1;
            margin-top: 0.35rem;
        }

        .workspace-team-link {
            color: inherit;
            text-decoration: none;
        }

        .workspace-team-link:hover {
            color: #1f5eff;
        }

        @media (max-width: 767.98px) {
            .workspace-team-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <x-page-header
        title="My Teams"
        subtitle="Read-only team visibility for manager and HOD workspace users."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'My Teams'],
        ]"
    />

    @if ($selectedTeam)
        <x-table
            title="Team Details"
            description="Selected team summary for the active wage month."
            :loading="false"
        >
            <div id="team-details" class="row g-3 mb-3">
                <div class="col-12 col-lg-6">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="text-uppercase small fw-semibold text-muted mb-2">Selected Team</div>
                        <div class="h4 fw-bold mb-1">{{ $selectedTeam->name }}</div>
                        <div class="text-muted mb-3">{{ $selectedTeam->code ?: 'No code assigned' }}</div>
                        <div class="row g-2 small">
                            <div class="col-12 col-md-6"><strong>Department:</strong> {{ $selectedTeam->department?->name ?: 'N/A' }}</div>
                            <div class="col-12 col-md-6"><strong>Operation Area:</strong> {{ $selectedTeam->operationArea?->name ?: 'N/A' }}</div>
                            <div class="col-12 col-md-6"><strong>Manager:</strong> {{ $selectedTeam->manager?->name ?: 'N/A' }}</div>
                            <div class="col-12 col-md-6"><strong>HOD:</strong> {{ $selectedTeam->hod?->name ?: 'N/A' }}</div>
                            <div class="col-12 col-md-6"><strong>Primary Executive:</strong> {{ $selectedTeam->operationExecutive?->name ?: 'N/A' }}</div>
                            <div class="col-12 col-md-6"><strong>Status:</strong> {{ $selectedTeam->is_active ? 'Active' : 'Inactive' }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="text-uppercase small fw-semibold text-muted mb-2">Executives</div>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse ($selectedTeam->executives as $executive)
                                <span class="badge text-bg-light border px-3 py-2">
                                    {{ $executive->name }}
                                    @if ($executive->employee_code)
                                        ({{ $executive->employee_code }})
                                    @endif
                                </span>
                            @empty
                                <span class="text-muted">No executives mapped to this team.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @if ($selectedTeamMetrics)
                <div class="workspace-team-metrics">
                    <div class="workspace-team-metric">
                        <div class="workspace-team-metric-label">Team Members</div>
                        <div class="workspace-team-metric-value">{{ number_format($selectedTeamMetrics['executives']) }}</div>
                    </div>
                    <div class="workspace-team-metric">
                        <div class="workspace-team-metric-label">Visible Service Orders</div>
                        <div class="workspace-team-metric-value">{{ number_format($selectedTeamMetrics['service_orders']) }}</div>
                    </div>
                    <div class="workspace-team-metric">
                        <div class="workspace-team-metric-label">Visible Locations</div>
                        <div class="workspace-team-metric-value">{{ number_format($selectedTeamMetrics['locations']) }}</div>
                    </div>
                </div>
            @endif
        </x-table>
    @endif

    <x-table
        title="Visible Teams"
        description="Only teams directly mapped to you as manager or HOD are shown here."
        :loading="false"
    >
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge text-bg-light border px-3 py-2">Active Wage Month: {{ $activeWageMonthLabel }}</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>Operation Area</th>
                        <th>Manager</th>
                        <th>HOD</th>
                        <th>Members</th>
                        <th>Executives</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teams as $team)
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <a href="{{ route('operations-workspace.teams', ['team_id' => $team->id]) }}#team-details" class="workspace-team-link">
                                        {{ $team->name }}
                                    </a>
                                </div>
                                <div class="small text-muted">{{ $team->code ?: 'N/A' }}</div>
                            </td>
                            <td>{{ $team->operationArea?->name ?: 'N/A' }}</td>
                            <td>{{ $team->manager?->name ?: 'N/A' }}</td>
                            <td>{{ $team->hod?->name ?: 'N/A' }}</td>
                            <td>{{ number_format($team->executives_count) }}</td>
                            <td>{{ $team->executives->pluck('name')->implode(', ') ?: 'Not Available' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No teams are visible in your workspace.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $teams->firstItem() ?? 0 }} to {{ $teams->lastItem() ?? 0 }} of {{ $teams->total() }} teams</p>
            {{ $teams->links() }}
        </x-slot:footer>
    </x-table>
@endsection
