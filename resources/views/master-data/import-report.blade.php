@php
    $report = session('import_report');
@endphp

@if ($report && ($report['type'] ?? null) === $type)
    <div class="alert {{ ($report['failed'] ?? 0) > 0 ? 'alert-warning' : 'alert-success' }} border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">{{ $report['label'] }} import summary</div>
        <div>Inserted rows: {{ $report['inserted'] ?? 0 }} | Failed rows: {{ $report['failed'] ?? 0 }}</div>
    </div>

    @if (! empty($report['failures']))
        <div class="surface-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Import Error Report</h2>
                    <p class="text-muted mb-0">Only valid rows were inserted. Review the failed rows below and re-upload the corrected file.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Field</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['failures'] as $failure)
                            <tr>
                                <td>{{ $failure['row'] }}</td>
                                <td>{{ str_replace('_', ' ', \Illuminate\Support\Str::title($failure['attribute'])) }}</td>
                                <td>{{ implode(' ', $failure['errors']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
