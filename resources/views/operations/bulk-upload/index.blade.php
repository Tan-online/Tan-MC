@extends('layouts.app')

@section('title', 'Bulk Muster Upload | Tan-MC')

@section('content')
    <x-page-header
        title="Bulk Muster Upload"
        subtitle="Upload a single soft-copy against multiple SO-locations for the selected wage month."
        :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Bulk Muster Upload'],
        ]"
    />

    <div class="d-flex justify-content-between align-items-center mb-3" style="background: linear-gradient(135deg, #fff8dc 0%, #ffe6cc 100%); padding: 0.75rem; border-radius: 6px;">
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted fw-semibold" style="font-size: 0.9rem;">Wage Month</span>
            <select id="wageMonthSelector" class="form-select d-inline-block wage-month-input" onchange="updateWageMonth(this.value)">
                @foreach ($wageMonthOptions as $option)
                    <option value="{{ $option['value'] }}" @selected($selectedWageMonth === $option['value'])>
                        {{ $option['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <form method="GET" action="{{ route('bulk-muster.index') }}" id="searchForm" class="d-inline-block">
                <input type="hidden" name="wage_month" value="{{ $selectedWageMonth }}">
                <input type="text" name="search" id="searchInput" placeholder="Search Client / SO Number" value="{{ $search }}" class="form-control" style="min-width: 380px;">
            </form>
        </div>
    </div>

    <div class="surface-card p-4">
        <form method="POST" action="{{ route('bulk-muster.store') }}" enctype="multipart/form-data" id="bulkMusterForm">
            @csrf
            <input type="hidden" name="wage_month" value="{{ $selectedWageMonth }}">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px;">
                                <input type="checkbox" id="toggleAllActive">
                            </th>
                            <th>Client Name</th>
                            <th>SO Number</th>
                            <th>Location Name</th>
                            <th style="width:170px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $isDisabled = in_array(strtolower($row->status), ['submitted', 'approved']);
                                $rowClasses = $isDisabled ? 'text-muted bg-light' : '';
                                $checkboxChecked = $isDisabled ? '' : 'checked';
                            @endphp
                            <tr class="{{ $rowClasses }}">
                                <td>
                                    <input type="checkbox" class="form-check-input select-row" name="selected_pairs[]" value="{{ $row->service_order_id }}:{{ $row->location_id }}" {{ $isDisabled ? 'disabled' : 'checked' }}>
                                </td>
                                <td>{{ $row->client_name }}</td>
                                <td>{{ $row->so_number }}</td>
                                <td>{{ $row->location_name }}</td>
                                <td>
                                    @if ($isDisabled)
                                        <span class="badge bg-secondary">Already Uploaded</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ ucfirst($row->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">No records found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <input type="file" name="file" id="bulkFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip" required>
                </div>
                <div>
                    @php
                        $activeCount = $rows->getCollection()->filter(function ($r) { return !in_array(strtolower($r->status), ['submitted','approved']); })->count();
                    @endphp
                    <button type="submit" class="btn btn-primary" id="uploadBtn" @if($activeCount === 0) disabled @endif>Upload for Selected ({{ $activeCount }})</button>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="text-muted small">Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} locations</div>
                <div>
                    {{ $rows->links() }}
                </div>
            </div>
        </form>
    </div>

@endsection

@push('styles')
    <style>
        tr.bg-light { background-color: #f8f9fa !important; }
    </style>
@endpush

@push('scripts')
    <script>
        (function(){
            // Debounced search
            const searchInput = document.getElementById('searchInput');
            let timeout = null;
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(timeout);
                    timeout = setTimeout(function () {
                        document.getElementById('searchForm').submit();
                    }, 300);
                });
            }

            // Select only active rows when toggling all
            const toggleAll = document.getElementById('toggleAllActive');
            if (toggleAll) {
                toggleAll.addEventListener('change', function () {
                    document.querySelectorAll('.select-row').forEach(function (cb) {
                        if (!cb.disabled) cb.checked = toggleAll.checked;
                    });
                });
            }

            // Ensure checked non-disabled rows by default
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.select-row').forEach(function (cb) {
                    if (!cb.disabled && !cb.checked) cb.checked = true;
                });
            });
        })();
    </script>
@endpush
