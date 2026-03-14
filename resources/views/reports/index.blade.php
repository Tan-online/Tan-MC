@extends('layouts.app')

@section('title', 'Reports | Tan-MC')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Compliance Reports</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a class="text-decoration-none" href="{{ route('dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Reports</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('reports.export', ['report' => $reportType, 'format' => 'excel', 'month' => $filters['month'], 'year' => $filters['year']]) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
            </a>
            <a href="{{ route('reports.export', ['report' => $reportType, 'format' => 'pdf', 'month' => $filters['month'], 'year' => $filters['year']]) }}" class="btn btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
            </a>
        </div>
    </div>

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

    <div class="surface-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">{{ $reportOptions[$reportType] }}</h2>
                <p class="text-muted mb-0">Corporate compliance summary for {{ \Carbon\Carbon::create($filters['year'], $filters['month'], 1)->format('F Y') }}.</p>
            </div>
        </div>

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

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <p class="text-muted small mb-0">Showing {{ $report->firstItem() ?? 0 }} to {{ $report->lastItem() ?? 0 }} of {{ $report->total() }} rows</p>
            {{ $report->links() }}
        </div>
    </div>
@endsection
