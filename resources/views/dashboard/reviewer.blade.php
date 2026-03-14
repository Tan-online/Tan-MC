@extends('layouts.app')

@section('title', 'Review Dashboard | Tan-MC')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Review And Approval Dashboard</h1>
            <p class="text-muted mb-0">Focused workspace for approvals, escalations, and compliance review decisions.</p>
        </div>
        <a href="{{ route('bulk-receive.index') }}" class="btn btn-primary">
            <i class="bi bi-shield-check me-2"></i>Open Review Queue
        </a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-md-4">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Pending Approvals</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($pendingApprovalsCount) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Recently Approved</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($recentlyApprovedCount) }}</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="surface-card metric-card p-4">
                <div class="text-muted small text-uppercase fw-semibold mb-2">Escalated Items</div>
                <div class="display-6 fw-bold mb-0">{{ number_format($escalatedItemsCount) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-6">
            <div class="surface-card p-4 h-100">
                <div class="mb-4">
                    <h2 class="h5 fw-bold mb-1">Pending Approvals</h2>
                    <p class="text-muted mb-0">Received and late items waiting for approval action.</p>
                </div>

                <div class="d-flex flex-column gap-3">
                    @forelse ($pendingApprovals as $item)
                        <div class="border rounded-4 p-3">
                            <div class="fw-semibold">{{ $item->location?->name ?: 'Unknown location' }}</div>
                            <div class="text-muted small">{{ $item->contract?->contract_no ?: 'No contract' }}</div>
                            <span class="badge text-bg-light border mt-2">{{ $item->status }}</span>
                        </div>
                    @empty
                        <div class="border rounded-4 p-3 text-muted">No approval items pending.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-xxl-6">
            <div class="surface-card p-4 mb-4">
                <div class="mb-4">
                    <h2 class="h5 fw-bold mb-1">Recently Approved</h2>
                    <p class="text-muted mb-0">Latest approval decisions completed by reviewers.</p>
                </div>

                <div class="d-flex flex-column gap-3">
                    @forelse ($recentApprovals as $item)
                        <div class="border rounded-4 p-3">
                            <div class="fw-semibold">{{ $item->location?->name ?: 'Unknown location' }}</div>
                            <div class="text-muted small">{{ $item->contract?->contract_no ?: 'No contract' }}</div>
                            <div class="text-muted small">Approved by {{ $item->actedBy?->name ?: 'System' }}</div>
                        </div>
                    @empty
                        <div class="border rounded-4 p-3 text-muted">No recent approvals available.</div>
                    @endforelse
                </div>
            </div>

            <div class="surface-card p-4">
                <div class="mb-4">
                    <h2 class="h5 fw-bold mb-1">Escalated Items</h2>
                    <p class="text-muted mb-0">Late and returned submissions requiring follow-up.</p>
                </div>

                <div class="d-flex flex-column gap-3">
                    @forelse ($escalatedItems as $item)
                        <div class="border rounded-4 p-3">
                            <div class="fw-semibold">{{ $item->location?->name ?: 'Unknown location' }}</div>
                            <div class="text-muted small">{{ $item->contract?->contract_no ?: 'No contract' }}</div>
                            <span class="badge {{ $item->status === 'Returned' ? 'text-bg-warning' : 'text-bg-danger' }} mt-2">{{ $item->status }}</span>
                        </div>
                    @empty
                        <div class="border rounded-4 p-3 text-muted">No escalated items right now.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
