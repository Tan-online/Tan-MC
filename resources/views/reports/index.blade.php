@extends('layouts.app')

@section('title', 'Reports | Tan-MC')

@section('content')
    <x-page-header
        title="Compliance Reports"
        subtitle="Standardized reporting view for export-ready monthly compliance analysis."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Reports'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <a href="{{ route('reports.export', ['report' => $reportType, 'format' => 'excel', 'month' => $filters['month'], 'year' => $filters['year']]) }}" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
                <a href="{{ route('reports.export', ['report' => $reportType, 'format' => 'pdf', 'month' => $filters['month'], 'year' => $filters['year']]) }}" class="btn btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
                </a>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <div class="surface-card p-4 mb-4">
        <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Report</label>
                <select name="report" class="form-select">
                    @foreach ($reportOptions as $key => $label)
                        <option value="{{ $key }}" @selected($reportType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Month</label>
                <select name="month" class="form-select">
                    @foreach (range(1, 12) as $monthOption)
                        <option value="{{ $monthOption }}" @selected($filters['month'] === $monthOption)>{{ \Carbon\Carbon::create()->month($monthOption)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Year</label>
                <input type="number" name="year" class="form-control" min="2020" max="2100" value="{{ $filters['year'] }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Apply
                </button>
            </div>
        </form>
    </div>

    <x-table :title="$reportOptions[$reportType]" :description="'Corporate compliance summary for '.\Carbon\Carbon::create($filters['year'], $filters['month'], 1)->format('F Y').'.'">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ match($reportType) {
                            'client-compliance' => 'Client',
                            'state-compliance' => 'State',
                            default => 'Executive',
                        } }}</th>
                        <th>Total Expected</th>
                        <th>Received</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Late</th>
                        <th>Returned</th>
                        <th class="text-end">Compliance %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report as $row)
                        <tr>
                            <td class="fw-semibold">
                                {{ match($reportType) {
                                    'client-compliance' => $row->client_name,
                                    'state-compliance' => $row->state_name,
                                    default => $row->executive_name,
                                } }}
                            </td>
                            <td>{{ number_format($row->total_expected) }}</td>
                            <td>{{ number_format($row->received_count) }}</td>
                            <td>{{ number_format($row->approved_count) }}</td>
                            <td>{{ number_format($row->pending_count) }}</td>
                            <td>{{ number_format($row->late_count) }}</td>
                            <td>{{ number_format($row->returned_count) }}</td>
                            <td class="text-end">{{ number_format((float) $row->compliance_rate, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No report rows available for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <p class="text-muted small mb-0">Showing {{ $report->firstItem() ?? 0 }} to {{ $report->lastItem() ?? 0 }} of {{ $report->total() }} rows</p>
            {{ $report->links() }}
        </x-slot:footer>
    </x-table>
@endsection
