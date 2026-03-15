@extends('layouts.app')

@section('title', 'Background Tasks')

@section('content')
    <x-page-header
        title="Background Tasks"
        subtitle="Track queued imports, exports, and report generation jobs across the ERP platform."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'Background Tasks'],
        ]"
    />

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-table title="Generated Exports" description="Queued Excel, CSV, and report generation output.">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Format</th>
                                <th>Status</th>
                                <th>Rows</th>
                                <th>Requested By</th>
                                <th>Completed</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($exports as $export)
                                <tr>
                                    <td>{{ str($export->type)->replace('-', ' ')->title() }}</td>
                                    <td class="text-uppercase">{{ $export->format === 'excel' ? 'XLSX' : $export->format }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $export->status === 'completed' ? 'success' : ($export->status === 'failed' ? 'danger' : 'secondary') }}">
                                            {{ ucfirst($export->status) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($export->record_count) }}</td>
                                    <td>{{ $export->user?->name ?? 'System' }}</td>
                                    <td>{{ $export->completed_at?->diffForHumans() ?? 'In progress' }}</td>
                                    <td class="text-end">
                                        @if ($export->status === 'completed')
                                            <a href="{{ route('generated-exports.download', $export) }}" class="btn btn-sm btn-outline-primary">Download</a>
                                        @elseif ($export->status === 'failed')
                                            <span class="small text-danger">{{ $export->error_message }}</span>
                                        @else
                                            <span class="small text-muted">Queued</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No queued exports yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="table-panel-footer mt-3">
                    {{ $exports->links() }}
                </div>
            </x-table>
        </div>

        <div class="col-12 col-xl-5">
            <x-table title="Import Batches" description="Background imports with row counts, failures, and final status.">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Inserted</th>
                                <th>Failed</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($imports as $import)
                                <tr>
                                    <td>{{ str($import->type)->replace('-', ' ')->title() }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $import->status === 'completed' ? 'success' : ($import->status === 'failed' ? 'danger' : 'secondary') }}">
                                            {{ ucfirst($import->status) }}
                                        </span>
                                    </td>
                                    <td>{{ number_format($import->inserted_rows) }}</td>
                                    <td>{{ number_format($import->failed_rows) }}</td>
                                    <td>{{ $import->completed_at?->diffForHumans() ?? 'In progress' }}</td>
                                </tr>
                                @if ($import->status === 'failed' && $import->error_message)
                                    <tr>
                                        <td colspan="5" class="small text-danger">{{ $import->error_message }}</td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No queued imports yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="table-panel-footer mt-3">
                    {{ $imports->links() }}
                </div>
            </x-table>
        </div>
    </div>
@endsection