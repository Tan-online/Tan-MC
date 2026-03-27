@extends('layouts.app')

@section('title', 'Dispatch Entry | Tan-MC')

@push('styles')
    <style>
        .dispatch-filter-stack {
            display: grid;
            gap: 0.85rem;
            margin-bottom: 0.85rem;
        }

        .dispatch-month-grid,
        .dispatch-filter-grid {
            display: grid;
            gap: 0.75rem;
        }

        .dispatch-month-grid {
            grid-template-columns: minmax(200px, 280px) auto;
            align-items: end;
        }

        .dispatch-filter-grid {
            grid-template-columns: minmax(220px, 1.4fr) minmax(180px, 0.9fr) minmax(220px, 1fr) auto;
            align-items: end;
        }

        .dispatch-table-shell {
            overflow-x: auto;
            overflow-y: visible;
        }

        .dispatch-table {
            min-width: 1320px;
        }

        .dispatch-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f7faff;
            box-shadow: inset 0 -1px 0 rgba(219, 228, 240, 0.95);
        }

        .dispatch-table tbody tr:hover {
            background: rgba(31, 94, 255, 0.03);
        }

        .dispatch-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .dispatch-meta-chip {
            padding: 0.4rem 0.72rem;
            border-radius: 999px;
            background: #f2f6fc;
            border: 1px solid rgba(219, 228, 240, 0.95);
            font-size: 0.78rem;
            color: #4b5b6d;
        }

        .dispatch-meta-chip-month {
            background: linear-gradient(135deg, #fff1cc, #ffe0a6);
            border-color: rgba(213, 147, 31, 0.28);
            color: #7b5200;
            font-weight: 700;
        }

        .dispatch-badge {
            min-width: 88px;
            display: inline-flex;
            justify-content: center;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .dispatch-badge-pending {
            background: #fff4dd;
            color: #9c6b00;
        }

        .dispatch-badge-dispatched {
            background: #dff6ea;
            color: #0d6b3d;
        }

        .dispatch-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.4rem;
            white-space: nowrap;
        }

        .dispatch-table .period-cell,
        .dispatch-table .text-truncate-cell {
            min-width: 150px;
        }

        @media (max-width: 1199.98px) {
            .dispatch-filter-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .dispatch-month-grid,
            .dispatch-filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <x-page-header
        title="Dispatch Entry"
        subtitle="Current active service-order locations for the selected wage month, one row per active SO-location."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Dispatch Entry'],
        ]"
    />

    <div class="dispatch-filter-stack">
        <div class="surface-card p-3 p-lg-4">
            <form method="GET" action="{{ route('dispatch-entry.index') }}" class="dispatch-month-grid" data-loading-form data-loading-submit>
                <div>
                    <label for="dispatch-month" class="form-label">Wage Month</label>
                    <select id="dispatch-month" name="month" class="form-select">
                        @foreach ($wageMonthOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($selectedMonthKey === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-primary">Set Month</button>
                </div>
            </form>
        </div>

        <div class="surface-card p-3 p-lg-4 dispatch-filter-card">
            <form method="GET" action="{{ route('dispatch-entry.index') }}" class="dispatch-filter-grid" data-loading-form data-loading-submit>
                <input type="hidden" name="month" value="{{ $selectedMonthKey }}">

                <div>
                    <label for="dispatch-search" class="form-label">Search</label>
                    <input
                        id="dispatch-search"
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Search SO number, client name, location name, executive name"
                    >
                </div>

                <div>
                    <label for="dispatch-status" class="form-label">Status</label>
                    <select id="dispatch-status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (['pending' => 'Pending', 'dispatched' => 'Dispatched'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="dispatch-executive" class="form-label">Operation Executive</label>
                    <select id="dispatch-executive" name="executive_id" class="form-select">
                        <option value="">All executives</option>
                        @foreach ($operationExecutives as $executive)
                            <option value="{{ $executive->id }}" @selected($executiveId === (int) $executive->id)>{{ $executive->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex align-items-end gap-2">
                    <button class="btn btn-primary">Apply</button>
                    <a href="{{ route('dispatch-entry.index', ['month' => $selectedMonthKey]) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <x-table
        title="Dispatch Dataset"
        description="Rows include only currently active sales-order locations, aligned with the Sales Orders active-location view."
        :loading="false"
    >
        <div class="dispatch-meta">
            <span class="dispatch-meta-chip dispatch-meta-chip-month">Wage Month: {{ $selectedMonth->format('F Y') }}</span>
            <span class="dispatch-meta-chip">Rows: {{ number_format($dispatchEntries->total()) }}</span>
            <span class="dispatch-meta-chip">Page Size: 50</span>
        </div>

        <div class="dispatch-table-shell">
            <table class="table align-middle dispatch-table">
                <thead>
                    <tr>
                        <th>SO Number</th>
                        <th>Client Name</th>
                        <th>SO Name</th>
                        <th>Location Code</th>
                        <th>Location Name</th>
                        <th>Period</th>
                        <th>State</th>
                        <th>Operation Executive</th>
                        <th>Status</th>
                        <th>Received By</th>
                        <th>Action Taken Date</th>
                        <th>Despatched Type</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dispatchEntries as $entry)
                        <tr>
                            <td class="fw-semibold">{{ $entry->so_number }}</td>
                            <td class="text-truncate-cell">{{ $entry->client_name ?: 'N/A' }}</td>
                            <td class="text-truncate-cell">{{ $entry->so_name ?: 'N/A' }}</td>
                            <td>{{ $entry->location_code ?: 'N/A' }}</td>
                            <td class="text-truncate-cell">{{ $entry->location_name ?: 'N/A' }}</td>
                            <td class="period-cell">
                                {{ ($entry->period_start_date?->format('d M Y') ?: 'N/A') . ' - ' . ($entry->period_end_date?->format('d M Y') ?: 'N/A') }}
                            </td>
                            <td>{{ $entry->state_name ?: 'N/A' }}</td>
                            <td>{{ $entry->executive_name ?: 'Unassigned' }}</td>
                            <td>
                                <span class="dispatch-badge dispatch-badge-{{ $entry->dispatch_status }}">
                                    {{ ucfirst($entry->dispatch_status) }}
                                </span>
                            </td>
                            <td>{{ $entry->received_by_name ?: 'Pending' }}</td>
                            <td>{{ $entry->action_taken_at?->format('d M Y h:i A') ?: 'Pending' }}</td>
                            <td>{{ $entry->despatched_type ?: 'Pending' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">No active dispatch rows available for the selected wage month.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">
                Showing {{ $dispatchEntries->firstItem() ?? 0 }} to {{ $dispatchEntries->lastItem() ?? 0 }} of {{ $dispatchEntries->total() }} dispatch rows
            </p>
            {{ $dispatchEntries->links() }}
        </x-slot:footer>
    </x-table>
@endsection
