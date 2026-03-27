@extends('layouts.app')

@section('title', 'Workspace Locations | Tan-MC')

@push('styles')
    <style>
        /* Compact ERP styling */
        .wage-month-input {
            min-width: 180px;
            font-weight: 500;
        }

        .workspace-locations-card .card-body,
        .workspace-locations-card .card-footer {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }

        .workspace-locations-filter-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.5rem;
            align-items: end;
        }

        .workspace-locations-actions {
            display: flex;
            gap: 0.5rem;
            align-items: end;
        }

        .workspace-locations-table {
            min-width: 1200px;
        }

        .workspace-locations-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f7faff;
            box-shadow: inset 0 -1px 0 rgba(219, 228, 240, 0.95);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .workspace-locations-table th,
        .workspace-locations-table td {
            padding: 6px 10px;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .workspace-locations-table td:nth-child(4) {
            white-space: normal;
            min-width: 220px;
        }

        /* Action buttons spacing */
        .action-btn {
            margin-right: 4px;
        }

        /* Badge styling compactness */
        .badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.5rem;
        }

        @media (max-width: 991.98px) {
            .workspace-locations-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .workspace-locations-filter-grid {
                grid-template-columns: 1fr;
            }

            .workspace-locations-actions {
                width: 100%;
            }

            .workspace-locations-actions .btn {
                flex: 1 1 0;
            }
        }

        /* Timeline for status history */
        .timeline {
            position: relative;
            padding: 0 0 0 1.5rem;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 1rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
        }

        .timeline-content {
            padding: 0.5rem 0;
        }
    </style>
@endpush

@section('content')
    <x-page-header
        title="Workspace Locations"
        subtitle="Scoped location execution list with role-based visibility and month-based tracking."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Workspace Locations'],
        ]"
    />

    <div class="d-flex justify-content-between align-items-center mb-3" style="background: linear-gradient(135deg, #fff8dc 0%, #ffe6cc 100%); padding: 0.75rem; border-radius: 6px;">
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted fw-semibold" style="font-size: 0.9rem;">Wage Month</span>
            <select name="wage_month_selector" class="form-select d-inline-block wage-month-input" onchange="updateWageMonth(this.value)">
                @foreach ($wageMonthOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($selectedWageMonth === $option['value'])>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <form method="GET" action="{{ route('operations-workspace.export-locations') }}" class="d-inline">
            @foreach(request()->query() as $key => $value)
                @if(in_array($key, ['client_id', 'location_id', 'executive_id', 'status', 'wage_month']))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <button type="submit" class="btn btn-sm btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </button>
        </form>
    </div>

    <script>
        function updateWageMonth(value) {
            const url = new URL(window.location);
            url.searchParams.set('wage_month', value);
            window.location = url.toString();
        }
    </script>

    <div class="workspace-locations-card">
        <x-table
            title="Location List"
            description="Compact location list with secure server-side pagination."
            :loading="false"
        >
            <form method="GET" action="{{ route('operations-workspace.locations') }}" class="workspace-locations-filter-grid mb-3" data-loading-form data-loading-submit>
                <div>
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">All clients</option>
                        @foreach ($clientOptions as $clientOption)
                            <option value="{{ $clientOption->client_id }}" @selected($selectedClientId === (int) $clientOption->client_id)>{{ $clientOption->client_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Location</label>
                    <select name="location_id" class="form-select">
                        <option value="">All locations</option>
                        @foreach ($locationOptions as $locationOption)
                            <option value="{{ $locationOption->location_id }}" @selected($selectedLocationId === (int) $locationOption->location_id)>{{ $locationOption->location_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="pending" @selected($selectedStatus === 'pending')>Pending</option>
                        <option value="submit" @selected($selectedStatus === 'submit')>Submitted</option>
                        <option value="return" @selected($selectedStatus === 'return')>Returned</option>
                        <option value="received" @selected($selectedStatus === 'received')>Received</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Executive Name</label>
                    <select name="executive_id" class="form-select">
                        <option value="">All executives</option>
                        @foreach ($executiveOptions as $executiveOption)
                            <option value="{{ $executiveOption->executive_id }}" @selected($selectedExecutiveId === (int) $executiveOption->executive_id)>{{ $executiveOption->executive_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="workspace-locations-actions">
                    <button class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="{{ route('operations-workspace.locations') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm align-middle workspace-locations-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3%;">Sr. No</th>
                            <th>Client Name</th>
                            <th>SO Number</th>
                            <th style="width: 8%;">LC Code</th>
                            <th>Location Name</th>
                            <th>Executive</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Action Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locationRows as $index => $row)
                            <tr>
                                <td class="text-muted small">{{ $locationRows->firstItem() + $index }}</td>
                                <td>{{ $row->client_name }}</td>
                                <td>{{ $row->so_number }}</td>
                                <td>{{ $row->location_code ?: 'N/A' }}</td>
                                <td class="fw-semibold">{{ $row->location_name }}</td>
                                <td>{{ $row->executive_name ?: 'Unassigned' }}</td>
                                <td>
                                    @php
                                        $statusBadgeClass = match($row->status) {
                                            'pending' => 'bg-warning text-dark',
                                            'submitted' => 'bg-info text-white',
                                            'approved' => 'bg-success text-white',
                                            'rejected' => 'bg-danger text-white',
                                            'returned' => 'bg-orange text-white',
                                            default => 'bg-secondary text-white'
                                        };
                                        $statusLabel = match($row->status) {
                                            'pending' => 'Pending',
                                            'submitted' => 'Submitted',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                            'returned' => 'Returned',
                                            default => ucfirst($row->status)
                                        };
                                    @endphp
                                    <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                                    {{-- Show reviewer remarks for rejected/returned statuses --}}
                                    @if(in_array($row->status, ['rejected', 'returned']) && $row->reviewer_remarks)
                                        <div class="small text-muted mt-2" style="max-width: 200px; word-wrap: break-word;">
                                            <strong>Remarks:</strong> {{ $row->reviewer_remarks }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        // Only show submission_type for submitted and approved statuses
                                        $showSubmissionType = in_array($row->status, ['submitted', 'approved']) && $row->submission_type_display;
                                    @endphp
                                    @if($showSubmissionType)
                                        @php
                                            $typeLabel = match($row->submission_type_display) {
                                                'hard_copy' => 'Hard Copy',
                                                'email' => 'Email',
                                                'courier' => 'Courier',
                                                'soft_copy_upload' => 'Upload',
                                                default => ucfirst(str_replace('_', ' ', $row->submission_type_display))
                                            };
                                        @endphp
                                        <span class="small text-muted">{{ $typeLabel }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">
                                    @php
                                        // Only show submitted_at for submitted and approved statuses
                                        $showSubmittedDate = in_array($row->status, ['submitted', 'approved']) && $row->submitted_at;
                                    @endphp
                                    @if($showSubmittedDate)
                                        {{ \Carbon\Carbon::parse($row->submitted_at)->format('d/m/y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm gap-1" role="group">
                                        {{-- Download button - For soft copy uploads --}}
                                        @if($row->file_path && $row->submission_type === 'soft_copy_upload')
                                            <a href="{{ route('operations-workspace.download-file', $row->id) }}" class="btn btn-sm btn-outline-info action-btn" title="Download File" download>
                                                <i class="bi bi-download"></i>
                                            </a>
                                        @endif

                                        {{-- Submit button - For operations users with pending, rejected, or returned status --}}
                                        @if($actionAvailabilities['is_operations_user'] && in_array($row->status, ['pending', 'rejected', 'returned']))
                                            <button type="button" class="btn btn-sm btn-success action-btn" data-bs-toggle="modal" data-bs-target="#submitModal-{{ $row->id }}" title="{{ in_array($row->status, ['rejected', 'returned']) ? 'Resubmit Location' : 'Submit Location' }}">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        @endif

                                        {{-- View history - Available to all --}}
                                        <button type="button" class="btn btn-sm btn-outline-primary action-btn" data-bs-toggle="modal" data-bs-target="#statusHistoryModal-{{ $row->id }}" title="View Status History">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        {{-- Reviewer actions - Only for approvers with submitted status --}}
                                        @if($actionAvailabilities['is_approver'] && $row->status === 'submitted')
                                            {{-- Approve button --}}
                                            <button type="button" class="btn btn-sm btn-outline-success action-btn" data-bs-toggle="modal" data-bs-target="#approveModal-{{ $row->id }}" title="Approve">
                                                <i class="bi bi-check2-all"></i>
                                            </button>

                                            {{-- Return for correction button --}}
                                            <button type="button" class="btn btn-sm btn-outline-warning action-btn" data-bs-toggle="modal" data-bs-target="#returnModal-{{ $row->id }}" title="Return for Correction">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>

                                            {{-- Reject button --}}
                                            <button type="button" class="btn btn-sm btn-outline-danger action-btn" data-bs-toggle="modal" data-bs-target="#rejectModal-{{ $row->id }}" title="Reject">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">No workspace locations found for your current scope.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot:footer>
                <p class="text-muted small mb-0">Showing {{ $locationRows->firstItem() ?? 0 }} to {{ $locationRows->lastItem() ?? 0 }} of {{ $locationRows->total() }} locations</p>
                {{ $locationRows->links() }}
            </x-slot:footer>
        </x-table>
    </div>

    {{-- Submit Modal --}}
    @foreach ($locationRows as $row)
        <div class="modal fade" id="submitModal-{{ $row->id }}" tabindex="-1" aria-labelledby="submitModalLabel-{{ $row->id }}" aria-hidden="true">
            <div class="modal-dialog modal-sm" style="max-width: 450px;">
                <div class="modal-content">
                    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <h6 class="modal-title text-white mb-0" id="submitModalLabel-{{ $row->id }}">
                            <i class="bi bi-upload me-2"></i>Submit - {{ $row->location_code }}
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('operations-workspace.submit-location', $row->id) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body p-3">
                            <input type="hidden" name="wage_month" value="{{ $selectedWageMonth }}">
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">Client</small>
                                    <div class="small">{{ $row->client_name }}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">SO #</small>
                                    <div class="small">{{ $row->so_number }}</div>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">Location Code</small>
                                    <div class="small">{{ $row->location_code ?: 'N/A' }}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">Month</small>
                                    <div class="small">{{ \Carbon\Carbon::createFromFormat('Y-m', $selectedWageMonth)->format('M Y') }}</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block fw-bold">Location</small>
                                <div class="small fw-semibold">{{ $row->location_name }}</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold mb-2">Submission Type *</label>
                                <select name="type" class="form-select form-select-sm" id="submissionType-{{ $row->id }}" required>
                                    <option value="">Select type</option>
                                    <option value="hard_copy">Hard Copy</option>
                                    <option value="email">Email</option>
                                    <option value="courier">Courier</option>
                                    <option value="soft_copy_upload">Soft Copy Upload</option>
                                </select>
                            </div>

                            <div class="mb-3" id="fileUploadDiv-{{ $row->id }}" style="display: none;">
                                <label class="form-label small fw-bold mb-2">Upload File *</label>
                                <input type="file" class="form-control form-control-sm" name="file" id="fileInput-{{ $row->id }}" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip">
                                <small class="text-muted d-block mt-1">PDF, DOC, DOCX, XLS, XLSX, ZIP (Max 10MB)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold mb-2">Remarks</label>
                                <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Add remarks..." style="font-size: 0.85rem;"></textarea>
                            </div>

                            <div class="alert alert-info alert-sm py-2 px-3 mb-0" style="font-size: 0.85rem; border-radius: 4px;">
                                <i class="bi bi-info-circle me-2"></i><strong>Date & Time:</strong> {{ \Carbon\Carbon::now('Asia/Kolkata')->format('d/m/y H:i') }} IST
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 p-3 gap-2">
                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-check-circle me-1"></i>Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('submissionType-{{ $row->id }}').addEventListener('change', function() {
                const fileDiv = document.getElementById('fileUploadDiv-{{ $row->id }}');
                const fileInput = document.getElementById('fileInput-{{ $row->id }}');
                
                if (this.value && this.value !== 'hard_copy') {
                    fileDiv.style.display = 'block';
                    fileInput.required = true;
                } else {
                    fileDiv.style.display = 'none';
                    fileInput.required = false;
                    fileInput.value = '';
                }
            });
        </script>
    @endforeach
        </div>

    {{-- Status History Modals --}}
    @foreach ($locationRows as $row)
        <div class="modal fade" id="statusHistoryModal-{{ $row->id }}" tabindex="-1" aria-labelledby="statusHistoryModalLabel-{{ $row->id }}" aria-hidden="true">
            <div class="modal-dialog modal-sm" style="max-width: 450px;">
                <div class="modal-content">
                    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                        <h6 class="modal-title text-white mb-0" id="statusHistoryModalLabel-{{ $row->id }}">
                            <i class="bi bi-clock-history me-2"></i>Status History
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-3">
                        <div class="mb-3" style="background: #f8f9fa; padding: 10px 12px; border-radius: 4px; border-left: 3px solid #0d6efd;">
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">Client</small>
                                    <div class="small fw-semibold">{{ $row->client_name }}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">Code</small>
                                    <div class="small fw-semibold">{{ $row->location_code ?: 'N/A' }}</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted d-block fw-bold">Location</small>
                                    <div class="small fw-semibold">{{ $row->location_name }}</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">SO Start Date</small>
                                    <div class="small fw-semibold">
                                        @if($row->so_start_date)
                                            {{ \Carbon\Carbon::parse($row->so_start_date)->format('d/m/y') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">Location Start</small>
                                    <div class="small fw-semibold">
                                        @if($row->location_start_date)
                                            {{ \Carbon\Carbon::parse($row->location_start_date)->format('d/m/y') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block fw-bold">Location End</small>
                                    <div class="small fw-semibold">
                                        @if($row->location_end_date)
                                            {{ \Carbon\Carbon::parse($row->location_end_date)->format('d/m/y') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="timeline" style="border-left: 2px solid #e0e0e0; padding-left: 12px; margin-left: 6px;">
                            <div class="timeline-item" style="margin-bottom: 12px;">
                                <div style="position: absolute; left: -7px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #0d6efd; border: 2px solid #ffffff;"></div>
                                <div>
                                    <div class="fw-bold small">Current Status</div>
                                    <div class="small text-primary fw-semibold" style="font-size: 1rem;">
                                        @php
                                            $statusLabel = match($row->status) {
                                                'pending' => 'Pending',
                                                'submit' => 'Submitted',
                                                'return' => 'Returned',
                                                'received' => 'Received',
                                                default => ucfirst($row->status)
                                            };
                                        @endphp
                                        {{ $statusLabel }}
                                    </div>
                                    @if($row->action_date)
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-calendar3"></i> {{ \Carbon\Carbon::parse($row->action_date, 'Asia/Kolkata')->format('d/m/y H:i') }} IST
                                        </small>
                                    @endif
                                    @if($row->type)
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-box"></i> 
                                            @php
                                                $typeLabel = match($row->type) {
                                                    'hard_copy' => 'Hard Copy',
                                                    'email' => 'Email',
                                                    'courier' => 'Courier',
                                                    'soft_copy_upload' => 'Upload',
                                                    default => ucfirst(str_replace('_', ' ', $row->type))
                                                };
                                            @endphp
                                            {{ $typeLabel }}
                                        </small>
                                    @endif
                                    @if($row->remarks)
                                        <small class="text-secondary d-block mt-2 p-2" style="background: #fff3cd; border-radius: 3px;">
                                            <strong>Note:</strong> {{ Str::limit($row->remarks, 100) }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info alert-sm py-2 px-3 mb-0 mt-3" style="font-size: 0.85rem; border-radius: 4px;">
                            <i class="bi bi-shield-check"></i> Full audit trail maintained
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reject/Cancel Modal --}}
        <div class="modal fade" id="rejectModal-{{ $row->id }}" tabindex="-1" aria-labelledby="rejectModalLabel-{{ $row->id }}" aria-hidden="true">
            <div class="modal-dialog modal-sm" style="max-width: 450px;">
                <div class="modal-content">
                    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                        <h6 class="modal-title text-white mb-0" id="rejectModalLabel-{{ $row->id }}">
                            <i class="bi bi-x-circle me-2"></i>Reject/Cancel - {{ $row->location_code }}
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('operations-workspace.reject-location', $row->id) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body p-3">
                            <input type="hidden" name="wage_month" value="{{ $selectedWageMonth }}">
                            
                            <div class="alert alert-warning alert-sm py-2 px-3 mb-3" style="border-radius: 4px; font-size: 0.85rem;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> This will reject for {{ \Carbon\Carbon::createFromFormat('Y-m', $selectedWageMonth)->format('M Y') }}
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold mb-2">Location Information</label>
                                <div class="small text-muted">
                                    <div><strong>Client:</strong> {{ $row->client_name }}</div>
                                    <div><strong>Location:</strong> {{ $row->location_name }} ({{ $row->location_code }})</div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold mb-2">Reason for Rejection *</label>
                                <textarea name="remarks" class="form-control form-control-sm" rows="3" required placeholder="Why are you rejecting this assignment..." style="font-size: 0.85rem;"></textarea>
                                <small class="text-muted d-block mt-1">This will be visible in status history</small>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 p-3 gap-2">
                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="bi bi-x-circle me-1"></i>Reject
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Approve Modal --}}
        <div class="modal fade" id="approveModal-{{ $row->id }}" tabindex="-1" aria-labelledby="approveModalLabel-{{ $row->id }}" aria-hidden="true">
            <div class="modal-dialog modal-sm" style="max-width: 450px;">
                <div class="modal-content">
                    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%); border: none;">
                        <h6 class="modal-title text-dark mb-0" id="approveModalLabel-{{ $row->id }}">
                            <i class="bi bi-check2-circle me-2"></i>Approve - {{ $row->location_code }}
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('operations-workspace.approve-location', $row->id) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body p-3">
                            <input type="hidden" name="wage_month" value="{{ $selectedWageMonth }}">
                            
                            <div class="alert alert-info alert-sm py-2 px-3 mb-3" style="border-radius: 4px; font-size: 0.85rem;">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Approving submission for:</strong> {{ \Carbon\Carbon::createFromFormat('Y-m', $selectedWageMonth)->format('M Y') }}
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold mb-2">Location Information</label>
                                <div class="small text-muted">
                                    <div><strong>Client:</strong> {{ $row->client_name }}</div>
                                    <div><strong>Location:</strong> {{ $row->location_name }} ({{ $row->location_code }})</div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold mb-2">Review Notes (Optional)</label>
                                <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="Add any notes..." style="font-size: 0.85rem;"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 p-3 gap-2">
                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-check2-all me-1"></i>Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Return for Correction Modal --}}
        <div class="modal fade" id="returnModal-{{ $row->id }}" tabindex="-1" aria-labelledby="returnModalLabel-{{ $row->id }}" aria-hidden="true">
            <div class="modal-dialog modal-sm" style="max-width: 450px;">
                <div class="modal-content">
                    <div class="modal-header bg-gradient" style="background: linear-gradient(135deg, #f6b93b 0%, #e58e26 100%); border: none;">
                        <h6 class="modal-title text-white mb-0" id="returnModalLabel-{{ $row->id }}">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Return for Correction - {{ $row->location_code }}
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('operations-workspace.return-location', $row->id) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body p-3">
                            <input type="hidden" name="wage_month" value="{{ $selectedWageMonth }}">
                            
                            <div class="alert alert-warning alert-sm py-2 px-3 mb-3" style="border-radius: 4px; font-size: 0.85rem;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Note:</strong> User will be able to resubmit
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold mb-2">Location Information</label>
                                <div class="small text-muted">
                                    <div><strong>Client:</strong> {{ $row->client_name }}</div>
                                    <div><strong>Location:</strong> {{ $row->location_name }} ({{ $row->location_code }})</div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-bold mb-2">Reason for Return (Required) *</label>
                                <textarea name="remarks" class="form-control form-control-sm" rows="3" required placeholder="What needs to be corrected..." style="font-size: 0.85rem;"></textarea>
                                <small class="text-muted d-block mt-1">User will see this message</small>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 p-3 gap-2">
                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-warning">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Return
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

