@extends('layouts.app')

@section('title', 'Reports | Tan-MC')

@section('content')
    <x-page-header
        title="Compliance Reports"
        subtitle="Role-scoped reporting with searchable muster status and compliance exports."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Reports'],
        ]"
    >
        <x-slot:actions>
            <x-action-buttons>
                <a href="{{ route('reports.export', ['report' => $reportType, 'format' => 'excel'] + $filters) }}" class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </a>
                <a href="{{ route('reports.export', ['report' => $reportType, 'format' => 'pdf'] + $filters) }}" class="btn btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
                </a>
            </x-action-buttons>
        </x-slot:actions>
    </x-page-header>

    <div class="surface-card p-4 mb-4">
        <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Report</label>
                <select name="report" class="form-select">
                    @foreach ($reportOptions as $key => $label)
                        <option value="{{ $key }}" @selected($reportType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="Client, contract, location, executive">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                    <option value="0">All clients</option>
                    @foreach ($filterOptions['clients'] as $client)
                        <option value="{{ $client->id }}" @selected($filters['client_id'] === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Contract</label>
                <select name="contract_id" class="form-select">
                    <option value="0">All contracts</option>
                    @foreach ($filterOptions['contracts'] as $contract)
                        <option value="{{ $contract->id }}" @selected($filters['contract_id'] === $contract->id)>{{ $contract->contract_no }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach ($filterOptions['statuses'] as $statusOption)
                        <option value="{{ $statusOption }}" @selected($filters['status'] === $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Month</label>
                <select name="month" class="form-select">
                    @foreach (range(1, 12) as $monthOption)
                        <option value="{{ $monthOption }}" @selected($filters['month'] === $monthOption)>{{ \Carbon\Carbon::create()->month($monthOption)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Year</label>
                <input type="number" name="year" class="form-control" min="2020" max="2100" value="{{ $filters['year'] }}">
            </div>
            <div class="col-lg-2 col-md-4 d-flex gap-2">
                <button class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-2"></i>Apply
                </button>
                <a href="{{ route('reports.index', ['report' => $reportType]) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <x-table :title="$reportOptions[$reportType]" :description="'Report period: '.\Carbon\Carbon::create($filters['year'], $filters['month'], 1)->format('F Y').'.'">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        @foreach ($columns as $columnLabel)
                            <th>{{ $columnLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report as $row)
                        <tr>
                            @foreach (array_keys($columns) as $column)
                                @php
                                    $value = $row->{$column} ?? null;
                                    $isDateColumn = in_array($column, ['received_at', 'last_action_at', 'due_date'], true);
                                    $isNumberColumn = in_array($column, ['total_expected', 'received_count', 'approved_count', 'pending_count', 'late_count', 'returned_count'], true);
                                    $isPercentColumn = $column === 'compliance_rate';
                                @endphp
                                <td class="{{ $column === array_key_first($columns) ? 'fw-semibold' : '' }} {{ $isPercentColumn ? 'text-end' : '' }}">
                                    @if ($isDateColumn)
                                        {{ $value ? \Carbon\Carbon::parse($value)->format('d M Y H:i') : 'N/A' }}
                                    @elseif ($isPercentColumn)
                                        {{ number_format((float) $value, 2) }}
                                    @elseif ($isNumberColumn)
                                        {{ number_format((int) $value) }}
                                    @else
                                        {{ $value ?: 'N/A' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="text-center py-5 text-muted">No report rows available for the selected filters.</td>
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
