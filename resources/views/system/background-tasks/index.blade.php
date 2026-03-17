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

    @php
        $staleThresholdMinutes = 60;
        $hasRunning = $exports->contains(fn ($e) => ! in_array($e->status, ['completed', 'failed', 'cancelled']))
            || $imports->contains(fn ($i) => ! in_array($i->status, ['completed', 'failed', 'cancelled']));
        $autoDownloadImport = $imports->first(function ($import) {
            $hasDownloadableFailure = ! empty($import->failure_report)
                || ($import->error_message && $import->stored_path);

            return $hasDownloadableFailure && ($import->status === 'failed' || $import->failed_rows > 0);
        });
    @endphp

    @if ($hasRunning)
        <div class="alert alert-info border-0 shadow-sm mb-3 d-flex align-items-center justify-content-between" id="autoRefreshBanner">
            <div><i class="bi bi-arrow-repeat me-2"></i>Tasks in progress — page auto-refreshes every 30 seconds.</div>
            <button class="btn btn-sm btn-outline-secondary" onclick="clearAutoRefresh()">Stop auto-refresh</button>
        </div>
    @endif

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
                                @php
                                    $isStale = ! in_array($export->status, ['completed', 'failed', 'cancelled'])
                                        && $export->updated_at->diffInMinutes(now()) >= $staleThresholdMinutes;
                                @endphp
                                <tr @class(['table-warning' => $isStale])>
                                    <td>
                                        {{ str($export->type)->replace('-', ' ')->title() }}
                                        @if ($isStale)
                                            <span class="badge text-bg-warning ms-1" title="Running for over {{ $staleThresholdMinutes }} minutes">Stale</span>
                                        @endif
                                    </td>
                                    <td class="text-uppercase">{{ $export->format === 'excel' ? 'XLSX' : $export->format }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ match($export->status) {
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            'cancelled' => 'secondary',
                                            default => 'warning',
                                        } }}">
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
                                        @elseif ($export->status === 'cancelled')
                                            <span class="small text-muted">Cancelled</span>
                                        @else
                                            <div class="d-inline-flex gap-2 align-items-center">
                                                <span class="small text-muted"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Running</span>
                                                <form method="POST" action="{{ route('background-tasks.exports.cancel', $export) }}" onsubmit="return confirm('Cancel this export task?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                                </form>
                                            </div>
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
                                <th class="text-end">Action</th>
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
                                    <td>{{ number_format($import->inserted_rows) }}</td>
                                    <td>{{ number_format($import->failed_rows) }}</td>
                                    <td>{{ $import->completed_at?->diffForHumans() ?? 'In progress' }}</td>
                                    <td class="text-end">
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

    @if ($autoDownloadImport)
        <a
            href="{{ route('background-tasks.imports.failure-report.download', $autoDownloadImport) }}"
            id="autoImportFailureDownload"
            class="d-none"
            data-import-key="{{ $autoDownloadImport->id }}-{{ $autoDownloadImport->updated_at?->timestamp ?? 0 }}"
        >
            Download import errors
        </a>
    @endif
@endsection

@push('scripts')
    @if ($hasRunning || $autoDownloadImport)
        <script>
            const autoImportFailureDownload = document.getElementById('autoImportFailureDownload');
            const pendingRefreshStorageKey = 'background-tasks:auto-refresh-pending';

            @if ($hasRunning)
                let refreshTimer = setTimeout(function () {
                    window.sessionStorage.setItem(pendingRefreshStorageKey, '1');
                    window.location.reload();
                }, 30000);

                function clearAutoRefresh() {
                    clearTimeout(refreshTimer);
                    window.sessionStorage.removeItem(pendingRefreshStorageKey);
                    document.getElementById('autoRefreshBanner')?.remove();
                }
            @else
                const refreshedFromAutoRefresh = window.sessionStorage.getItem(pendingRefreshStorageKey) === '1';
                window.sessionStorage.removeItem(pendingRefreshStorageKey);

                function clearAutoRefresh() {}
            @endif

            if (autoImportFailureDownload) {
                const storageKey = 'import-failure-download:' + autoImportFailureDownload.dataset.importKey;
                const shouldAutoDownload = @json($hasRunning) ? false : (typeof refreshedFromAutoRefresh === 'undefined' ? true : refreshedFromAutoRefresh);

                if (shouldAutoDownload && ! window.sessionStorage.getItem(storageKey)) {
                    window.sessionStorage.setItem(storageKey, '1');
                    window.setTimeout(function () {
                        autoImportFailureDownload.click();
                    }, 250);
                }
            }
        </script>
    @endif
@endpush
