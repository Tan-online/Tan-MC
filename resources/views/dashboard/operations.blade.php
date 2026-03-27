@extends('layouts.app')

@section('title', 'Operations Dashboard | Tan-MC')

@push('styles')
    <style>
        .ops-dashboard-stack {
            display: grid;
            gap: 0.85rem;
        }

        .ops-page-intro {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            justify-content: space-between;
            gap: 0.65rem;
            margin-bottom: 0.15rem;
        }

        .ops-page-title {
            color: #17304f;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
            margin: 0;
        }

        .ops-page-subtitle {
            color: #63768c;
            font-size: 0.98rem;
            margin: 0.2rem 0 0;
        }

        .ops-page-breadcrumb {
            color: #71859d;
            font-size: 0.83rem;
            font-weight: 600;
        }

        .ops-month-banner {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 0.9rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #fff2d6, #ffe3a8);
            border: 1px solid rgba(212, 153, 34, 0.22);
        }

        .ops-month-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.4rem 0.72rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.72);
            color: #805300;
            font-weight: 700;
            border: 0;
        }

        .ops-month-chip-link {
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            cursor: pointer;
        }

        .ops-month-chip-link:hover {
            color: #805300;
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(128, 83, 0, 0.12);
        }

        .ops-card-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .ops-metric-card {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            background: #fff;
            border: 1px solid rgba(219, 228, 240, 0.92);
            box-shadow: 0 8px 22px rgba(15, 39, 71, 0.06);
        }

        .ops-metric-label {
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #63768c;
            margin-bottom: 0.45rem;
        }

        .ops-metric-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            color: #17304f;
        }

        .ops-team-modal-list {
            display: grid;
            gap: 0.85rem;
        }

        .ops-team-modal-item {
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(219, 228, 240, 0.8);
        }

        .ops-team-modal-item:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .ops-team-modal-label {
            color: #63768c;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }

        .ops-team-modal-value {
            color: #17304f;
            font-size: 1rem;
            font-weight: 600;
        }

        .ops-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
            align-items: end;
        }

        .ops-table-shell {
            overflow-x: auto;
        }

        .ops-table {
            min-width: 1080px;
            margin-bottom: 0;
        }

        .ops-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f7faff;
            box-shadow: inset 0 -1px 0 rgba(219, 228, 240, 0.95);
        }

        .ops-status-badge {
            display: inline-flex;
            justify-content: center;
            min-width: 92px;
            padding: 0.28rem 0.62rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .ops-status-pending {
            background: #fff4dd;
            color: #9c6b00;
        }

        .ops-status-received,
        .ops-status-approved,
        .ops-status-closed {
            background: #dff6ea;
            color: #0d6b3d;
        }

        .ops-status-late,
        .ops-status-returned {
            background: #fde3e6;
            color: #b42336;
        }

        @media (max-width: 1199.98px) {
            .ops-card-grid,
            .ops-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .ops-card-grid,
            .ops-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@php
    $statusClassMap = [
        'Pending' => 'ops-status-pending',
        'Received' => 'ops-status-received',
        'Approved' => 'ops-status-approved',
        'Closed' => 'ops-status-closed',
        'Late' => 'ops-status-late',
        'Returned' => 'ops-status-returned',
    ];
@endphp

@section('content')
    <div class="ops-dashboard-stack">
        <div class="ops-page-intro">
            <div>
                <h1 class="ops-page-title">Operations Workspace</h1>
                <p class="ops-page-subtitle">Role-aware employee dashboard for wage-month execution, team visibility, and location tracking.</p>
            </div>
            <div class="ops-page-breadcrumb">Home / Operations Workspace</div>
        </div>

        <div class="ops-month-banner">
            <div>
                <div class="text-uppercase small fw-semibold text-muted">Active Wage Month</div>
                <div class="h5 fw-bold mb-0">{{ $activeWageMonthLabel }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="ops-month-chip"><i class="bi bi-calendar3"></i>{{ $activeWageMonthLabel }}</span>
                @if ($workspaceTeamId)
                    <button
                        type="button"
                        class="ops-month-chip ops-month-chip-link"
                        data-bs-toggle="modal"
                        data-bs-target="#workspaceTeamModal"
                    >
                        <i class="bi bi-people"></i>Team: {{ $workspaceTeamName }}
                    </button>
                @else
                    <span class="ops-month-chip"><i class="bi bi-people"></i>Team: {{ $workspaceTeamName }}</span>
                @endif
            </div>
        </div>

        <div class="ops-card-grid">
            <div class="ops-metric-card">
                <div class="ops-metric-label">Previous Pending</div>
                <div class="ops-metric-value">{{ number_format($metricPreviousPending) }}</div>
            </div>
            <div class="ops-metric-card">
                <div class="ops-metric-label">{{ $metricCurrentPendingLabel }}</div>
                <div class="ops-metric-value">{{ number_format($metricCurrentPending) }}</div>
            </div>
            <div class="ops-metric-card">
                <div class="ops-metric-label">Total Locations</div>
                <div class="ops-metric-value">{{ number_format($metricTotalLocations) }}</div>
            </div>
            <div class="ops-metric-card">
                <div class="ops-metric-label">Returned</div>
                <div class="ops-metric-value">{{ number_format($metricReturned) }}</div>
            </div>
        </div>

        <div class="surface-card p-3 p-lg-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Location List</h2>
                    <p class="text-muted mb-0">Employee sees own rows; manager and HOD see team aggregation for the active wage month.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 text-muted small">
                    <span>Contracts: {{ number_format($assignedContractCount) }}</span>
                    <span>Service Orders: {{ number_format($visibleServiceOrderCount) }}</span>
                </div>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="ops-filter-grid mb-3" data-loading-form data-loading-submit>
                <div>
                    <label class="form-label" for="ops-client-filter">Client</label>
                    <select id="ops-client-filter" name="client_id" class="form-select">
                        <option value="">All clients</option>
                        @foreach ($clientOptions as $clientOption)
                            <option value="{{ $clientOption->client_id }}" @selected($selectedClientId === (int) $clientOption->client_id)>{{ $clientOption->client_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="ops-location-filter">Location</label>
                    <select id="ops-location-filter" name="location_id" class="form-select">
                        <option value="">All locations</option>
                        @foreach ($locationOptions as $locationOption)
                            <option value="{{ $locationOption->location_id }}" @selected($selectedLocationId === (int) $locationOption->location_id)>{{ $locationOption->location_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="ops-status-filter">Status</label>
                    <select id="ops-status-filter" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $statusOption)
                            <option value="{{ $statusOption }}" @selected($selectedStatus === $statusOption)>{{ $statusOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-primary">Apply</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            <div class="ops-table-shell">
                <table class="table align-middle ops-table">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>SO Number</th>
                            <th>Location Code</th>
                            <th>Location Name</th>
                            <th>Operation Executive Name</th>
                            <th>Status</th>
                            <th>Received By</th>
                            <th>Mode</th>
                            <th class="text-end">Action Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locationRows as $row)
                            @php
                                $statusClass = $statusClassMap[$row->workspace_status] ?? 'ops-status-pending';
                            @endphp
                            <tr>
                                <td>{{ $row->client_name }}</td>
                                <td>{{ $row->so_number }}</td>
                                <td>{{ $row->location_code ?: 'N/A' }}</td>
                                <td class="fw-semibold">{{ $row->location_name }}</td>
                                <td>{{ $row->executive_name ?: 'Unassigned' }}</td>
                                <td><span class="ops-status-badge {{ $statusClass }}">{{ $row->workspace_status }}</span></td>
                                <td>{{ $row->received_by_name ?: 'Pending' }}</td>
                                <td>{{ $row->submission_mode ?: 'Pending' }}</td>
                                <td class="text-end text-muted">{{ $row->action_taken_at ? \Carbon\Carbon::parse($row->action_taken_at)->format('d M Y h:i A') : 'Pending' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No scoped locations found for the active wage month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                <p class="text-muted small mb-0">Showing {{ $locationRows->firstItem() ?? 0 }} to {{ $locationRows->lastItem() ?? 0 }} of {{ $locationRows->total() }} locations</p>
                {{ $locationRows->links() }}
            </div>
        </div>

        @if ($isOperationsSupervisor && $teamPerformance)
            <div class="surface-card p-3 p-lg-4">
                <div class="mb-3">
                    <h2 class="h5 fw-bold mb-1">Team Performance</h2>
                    <p class="text-muted mb-0">Current wage month summary grouped by employee.</p>
                </div>

                <div class="ops-table-shell">
                    <table class="table align-middle ops-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th class="text-end">Total Locations</th>
                                <th class="text-end">Submitted</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Returned</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($teamPerformance as $member)
                                <tr>
                                    <td>{{ $member->employee_name }}</td>
                                    <td class="text-end">{{ number_format($member->total_locations) }}</td>
                                    <td class="text-end">{{ number_format($member->submitted_count) }}</td>
                                    <td class="text-end">{{ number_format($member->received_count) }}</td>
                                    <td class="text-end">{{ number_format($member->returned_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">No team performance rows available for the active wage month.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                    <p class="text-muted small mb-0">
                        Showing {{ $teamPerformance->firstItem() ?? 0 }} to {{ $teamPerformance->lastItem() ?? 0 }}
                        @if ($teamPerformance->hasMorePages())
                            team rows
                        @else
                            visible team rows
                        @endif
                    </p>
                    {{ $teamPerformance->links() }}
                </div>
            </div>
        @endif

        <div class="surface-card p-3 p-lg-4">
            <div class="mb-3">
                <h2 class="h5 fw-bold mb-1">Recent Service Orders</h2>
                <p class="text-muted mb-0">Latest visible service orders in the current operations scope.</p>
            </div>
            <div class="row g-3">
                @forelse ($recentServiceOrders as $serviceOrder)
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded-4 p-3 h-100">
                            <div class="fw-semibold">{{ $serviceOrder->order_no }}</div>
                            <div class="small text-muted">{{ $serviceOrder->contract?->client?->name ?: 'No client' }}</div>
                            <div class="small text-muted mt-2">{{ $serviceOrder->location?->name ?: 'No location assigned' }}</div>
                            <div class="small text-muted">{{ $serviceOrder->status }}</div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="border rounded-4 p-3 text-muted">No service orders available in your current scope.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($workspaceTeam)
        <div class="modal fade" id="workspaceTeamModal" tabindex="-1" aria-labelledby="workspaceTeamModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 mb-1" id="workspaceTeamModalLabel">{{ $workspaceTeam->name }}</h2>
                            <div class="small text-muted">Team summary</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ops-team-modal-list">
                            <div class="ops-team-modal-item">
                                <div class="ops-team-modal-label">Team Member</div>
                                <div class="ops-team-modal-value">
                                    {{ $workspaceTeam->executives->pluck('name')->implode(', ') ?: 'Not Available' }}
                                </div>
                            </div>
                            <div class="ops-team-modal-item">
                                <div class="ops-team-modal-label">HOD Name</div>
                                <div class="ops-team-modal-value">{{ $workspaceTeam->hod?->name ?: 'N/A' }}</div>
                            </div>
                            <div class="ops-team-modal-item">
                                <div class="ops-team-modal-label">Manager Name</div>
                                <div class="ops-team-modal-value">{{ $workspaceTeam->manager?->name ?: 'N/A' }}</div>
                            </div>
                            <div class="ops-team-modal-item">
                                <div class="ops-team-modal-label">Department Name</div>
                                <div class="ops-team-modal-value">{{ $workspaceTeam->department?->name ?: 'N/A' }}</div>
                            </div>
                            <div class="ops-team-modal-item">
                                <div class="ops-team-modal-label">Operation Area</div>
                                <div class="ops-team-modal-value">{{ $workspaceTeam->operationArea?->name ?: 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
