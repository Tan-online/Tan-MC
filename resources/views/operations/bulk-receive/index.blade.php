@extends('layouts.app')

@section('title', 'Bulk Receive | Tan-MC')

@section('content')
    <x-page-header
        title="Bulk Receive"
        subtitle="Workflow intake for muster submissions, cycle filtering, and bulk review preparation."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Bulk Receive'],
        ]"
    />

    <div class="surface-card p-4 mb-4 table-loading-shell" data-loading-container>
        <form method="GET" action="{{ route('bulk-receive.index') }}" class="row g-3 align-items-end" data-loading-form>
            <div class="col-md-3">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($clientId === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Contract</label>
                <select name="contract_id" class="form-select">
                    <option value="">Select contract</option>
                    @foreach ($contracts as $contract)
                        <option value="{{ $contract->id }}" @selected($contractId === $contract->id)>{{ $contract->contract_no }} ({{ $contract->locations_count }} sites)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Month</label>
                <select name="month" class="form-select">
                    @foreach (range(1, 12) as $monthOption)
                        <option value="{{ $monthOption }}" @selected($month === $monthOption)>{{ \Carbon\Carbon::create()->month($monthOption)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Year</label>
                <input type="number" name="year" class="form-control" min="2020" max="2100" value="{{ $year }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach (['Pending', 'Received', 'Late', 'Approved', 'Returned', 'Closed'] as $statusOption)
                        <option value="{{ $statusOption }}" @selected($status === $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">
                    <i class="bi bi-funnel me-2"></i>Load Muster Cycle
                </button>
                @if ($cycle)
                    <span class="btn btn-light border disabled">{{ $cycle->cycle_label }}</span>
                    <a href="{{ route('exports.master-data', ['type' => 'muster-roll'] + request()->query()) }}" class="btn btn-outline-primary" data-loading-trigger>
                        <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                    </a>
                @endif
            </div>
        </form>
    </div>

    @if ($cycle)
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6 col-xl-2">
                <div class="surface-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Total Expected</div>
                    <div class="display-6 fw-bold">{{ number_format($cycleSummary['total']) }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="surface-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Pending</div>
                    <div class="display-6 fw-bold text-warning">{{ number_format($cycleSummary['pending']) }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="surface-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Received</div>
                    <div class="display-6 fw-bold text-primary">{{ number_format($cycleSummary['received']) }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="surface-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Late</div>
                    <div class="display-6 fw-bold text-danger">{{ number_format($cycleSummary['late']) }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="surface-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Approved</div>
                    <div class="display-6 fw-bold text-success">{{ number_format($cycleSummary['approved']) }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="surface-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Returned</div>
                    <div class="display-6 fw-bold text-secondary">{{ number_format($cycleSummary['returned']) }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-2">
                <div class="surface-card p-4 h-100">
                    <div class="text-muted small text-uppercase fw-semibold mb-2">Closed</div>
                    <div class="display-6 fw-bold text-dark">{{ number_format($cycleSummary['closed']) }}</div>
                </div>
            </div>
        </div>

        <div class="surface-card p-4 table-loading-shell" data-loading-container>
            <div class="table-panel-head">
                <div>
                    <h2 class="h5 fw-bold mb-1">Location Receive Register</h2>
                    <p class="text-muted mb-0">
                        Cycle window: {{ $cycle->cycle_start_date->format('d M Y') }} to {{ $cycle->cycle_end_date->format('d M Y') }}
                        | Due date: {{ $cycle->due_date->format('d M Y') }}
                    </p>
                </div>

                @if (userCan('muster.submit'))
                    <div class="table-panel-toolbar">
                        <button type="button" class="btn btn-outline-secondary" id="selectAllVisibleLocations">
                            <i class="bi bi-check2-square me-2"></i>Select Visible
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="selectAllLocations">
                            <i class="bi bi-collection-check me-2"></i>Select All Locations
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkReceiveActionModal">
                            <i class="bi bi-inboxes me-2"></i>Bulk Action
                        </button>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('bulk-receive.store') }}" id="bulkReceiveForm">
                @csrf
                <input type="hidden" name="client_id" value="{{ $clientId }}">
                <input type="hidden" name="contract_id" value="{{ $contractId }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="select_all_locations" value="0" id="selectAllLocationsInput">

                <x-table-loading-skeleton :columns="7" :rows="6" />

                <div class="table-content">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 44px;">
                                    @if (userCan('muster.submit'))
                                        <input type="checkbox" class="form-check-input" id="toggleAllVisible">
                                    @endif
                                </th>
                                <th>Location</th>
                                <th>Executive</th>
                                <th>Status</th>
                                <th>Received Via</th>
                                <th>Received At</th>
                                <th class="text-end">Review</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expectedEntries as $expected)
                                <tr>
                                    <td>
                                        @if (userCan('muster.submit'))
                                            <input type="checkbox" class="form-check-input receive-checkbox" name="selected_expected_ids[]" value="{{ $expected->id }}">
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $expected->location?->name }}</div>
                                        <div class="small text-muted">{{ $expected->location?->city ?: 'No city' }}{{ $expected->location?->state ? ' - '.$expected->location->state->name : '' }}</div>
                                    </td>
                                    <td>{{ $expected->executiveMapping?->executive_name ?: 'Unassigned' }}</td>
                                    <td>
                                        <span class="badge {{ match($expected->status) {
                                            'Approved' => 'text-bg-success-subtle text-success border border-success-subtle',
                                            'Returned' => 'text-bg-secondary-subtle text-secondary border border-secondary-subtle',
                                            'Closed' => 'text-bg-dark-subtle text-dark border border-dark-subtle',
                                            'Late' => 'text-bg-danger-subtle text-danger border border-danger-subtle',
                                            'Received' => 'text-bg-primary-subtle text-primary border border-primary-subtle',
                                            default => 'text-bg-warning-subtle text-warning border border-warning-subtle',
                                        } }}">
                                            {{ $expected->status }}
                                        </span>
                                    </td>
                                    <td>{{ $expected->received_via ?: 'Pending' }}</td>
                                    <td>{{ $expected->received_at ? $expected->received_at->format('d M Y h:i A') : 'Not received' }}</td>
                                    <td class="text-end">
                                        @if (in_array($expected->status, ['Received', 'Late'], true) && userCan('muster.review'))
                                            <div class="d-inline-flex gap-2">
                                                <form method="POST" action="{{ route('bulk-receive.review', $expected) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="review_status" value="Approved">
                                                    <button class="btn btn-sm btn-outline-success">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('bulk-receive.review', $expected) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="review_status" value="Returned">
                                                    <button class="btn btn-sm btn-outline-secondary">Return</button>
                                                </form>
                                            </div>
                                        @elseif ($expected->status === 'Approved' && userCan('workflow.final_close'))
                                            <form method="POST" action="{{ route('bulk-receive.final-close', $expected) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-sm btn-outline-dark">Final Close</button>
                                            </form>
                                        @else
                                            <span class="text-muted small">No review action</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No locations matched the selected contract and filters for this cycle.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($expectedEntries)
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-3">
                        <p class="text-muted small mb-0">Showing {{ $expectedEntries->firstItem() ?? 0 }} to {{ $expectedEntries->lastItem() ?? 0 }} of {{ $expectedEntries->total() }} locations</p>
                        {{ $expectedEntries->links() }}
                    </div>
                @endif
                </div>

                <div class="modal fade" id="bulkReceiveActionModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header">
                                <h2 class="modal-title h5 mb-0">Apply Bulk Receive Action</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Action</label>
                                    <select name="action" class="form-select" required>
                                        <option value="received_hard_copy">Received Hard Copy</option>
                                        <option value="received_email">Received Email</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control" rows="3" placeholder="Optional notes for this receive batch"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Apply Action</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    @elseif ($contractId)
        <div class="alert alert-info border-0 shadow-sm">No auto-generated muster cycle was created for the selected contract and month. Check the linked service order period and muster cycle settings.</div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllVisibleButton = document.getElementById('selectAllVisibleLocations');
            const selectAllLocationsButton = document.getElementById('selectAllLocations');
            const toggleAllVisible = document.getElementById('toggleAllVisible');
            const selectAllInput = document.getElementById('selectAllLocationsInput');

            const syncVisibleCheckboxes = function (checked) {
                document.querySelectorAll('.receive-checkbox').forEach(function (checkbox) {
                    checkbox.checked = checked;
                });
            };

            if (toggleAllVisible) {
                toggleAllVisible.addEventListener('change', function () {
                    selectAllInput.value = '0';
                    syncVisibleCheckboxes(toggleAllVisible.checked);
                });
            }

            if (selectAllVisibleButton) {
                selectAllVisibleButton.addEventListener('click', function () {
                    selectAllInput.value = '0';
                    syncVisibleCheckboxes(true);
                });
            }

            if (selectAllLocationsButton) {
                selectAllLocationsButton.addEventListener('click', function () {
                    selectAllInput.value = '1';
                    syncVisibleCheckboxes(true);
                });
            }
        });
    </script>
@endpush
