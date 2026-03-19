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

    @if (session('status'))
        <div class="alert alert-success border-0 shadow-sm mb-3">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-12">
            <x-table title="Import Batches" description="Background imports with processed row counts, error totals, and final status.">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Processed</th>
                                <th>Errors</th>
                                <th>Completed</th>
                                <th class="text-end actions-cell">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($imports as $import)
                                @php
                                    $isStale = ! in_array($import->status, ['completed', 'failed', 'cancelled'])
                                        && $import->updated_at->diffInMinutes(now()) >= $staleThresholdMinutes;
                                @endphp
                                <tr @class(['table-warning' => $isStale])>
                                    <td>
                                        {{ str($import->type)->replace('-', ' ')->title() }}
                                        @if ($isStale)
                                            <span class="badge text-bg-warning ms-1">Stale</span>
                                        @endif
                                        <div class="small text-muted">{{ $import->original_file_name ?: 'Uploaded file' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ match($import->status) {
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            'cancelled' => 'secondary',
                                            default => 'warning',
                                        } }}">
                                            {{ ucfirst($import->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ number_format($import->inserted_rows) }}
                                        <div class="small text-muted">Inserted or updated</div>
                                    </td>
                                    <td>
                                        {{ number_format($import->failed_rows) }}
                                        @if ($import->failed_rows > 0)
                                            <div class="small text-danger">Validation entries</div>
                                        @endif
                                    </td>
                                    <td>{{ $import->completed_at?->diffForHumans() ?? 'In progress' }}</td>
                                    <td class="text-end actions-cell">
                                        @php
                                            $hasDownloadableFailure = ! empty($import->failure_report)
                                                || ($import->error_message && $import->stored_path);
                                        @endphp
                                        @if (in_array($import->status, ['completed', 'failed', 'cancelled']))
                                            @if ($hasDownloadableFailure && ($import->status === 'failed' || $import->failed_rows > 0))
                                                <div class="d-inline-flex gap-2 align-items-center">
                                                    <a
                                                        href="{{ route('background-tasks.imports.failure-report.download', $import) }}"
                                                        class="btn btn-sm btn-outline-primary"
                                                        data-loading-mode="download"
                                                    >
                                                        Download Errors
                                                    </a>
                                                    @if ($import->status === 'failed')
                                                        <form method="POST" action="{{ route('background-tasks.imports.retry', $import) }}" style="display: inline;">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Retry this import">
                                                                <i class="bi bi-arrow-clockwise"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="small text-muted">—</span>
                                            @endif
                                        @else
                                            <form method="POST" action="{{ route('background-tasks.imports.cancel', $import) }}" onsubmit="return confirm('Cancel this import task?');">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No queued imports yet.</td>
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

@push('scripts')
@endpush
