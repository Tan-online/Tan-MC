@php
    $today = now()->toDateString();
    $clientId = (int) ($serviceOrder->contract?->client_id ?? 0);
    $activeAssignedLocations = ($serviceOrder->relationLoaded('activeLocations') ? $serviceOrder->activeLocations : $serviceOrder->locations)
        ->filter(function ($location) use ($serviceOrder, $clientId, $today) {
            $pivotEndDate = $location->pivot?->end_date;
            $isActiveAssignment = empty($pivotEndDate) || \Illuminate\Support\Carbon::parse($pivotEndDate)->gte($today);

            return $isActiveAssignment
                && (int) ($location->state_id ?? 0) === (int) $serviceOrder->state_id
                && ($clientId <= 0 || (int) ($location->client_id ?? 0) === $clientId)
                && (($location->is_active ?? true) === true || (int) ($location->is_active ?? 1) === 1);
        })
        ->values();
    $modalOpen = session('open_modal') === $modalKey;
    $selectedLocationIds = collect(old('location_ids', $activeAssignedLocations->pluck('id')->all()))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values();
    $locationSource = $activeAssignedLocations->keyBy('id');
    $allAssignedLocationSource = $serviceOrder->locations->keyBy('id');
    $initialSelectedLocations = $selectedLocationIds
        ->map(function (int $locationId) use ($locationSource, $serviceOrder, $modalOpen) {
            $location = $locationSource->get($locationId);
            $pivot = $serviceOrder->locations->firstWhere('id', $locationId)?->pivot;

            if (! $location) {
                return null;
            }

            return [
                'id' => $locationId,
                'name' => $location->name,
                'code' => $location->code,
                'city' => $location->city,
                'state_id' => $location->state_id,
                'start_date' => old(
                    'location_start_dates.' . $locationId,
                    $modalOpen && old('location_start_dates.' . $locationId)
                        ? old('location_start_dates.' . $locationId)
                        : (! empty($pivot?->start_date)
                            ? \Illuminate\Support\Carbon::parse($pivot->start_date)->format('Y-m-d')
                            : optional($serviceOrder->requested_date)->format('Y-m-d'))
                ),
                'end_date' => old(
                    'location_end_dates.' . $locationId,
                    $modalOpen && old('location_end_dates.' . $locationId)
                        ? old('location_end_dates.' . $locationId)
                        : (! empty($pivot?->end_date)
                            ? \Illuminate\Support\Carbon::parse($pivot->end_date)->format('Y-m-d')
                            : null)
                ),
                'operation_executive_id' => old(
                    'location_operation_executive_ids.' . $locationId,
                    $pivot?->operation_executive_id
                ),
                'muster_due_days' => old(
                    'location_muster_due_days.' . $locationId,
                    (int) ($pivot?->muster_due_days ?? 0)
                ),
            ];
        })
        ->filter()
        ->values();
    $initialRemovedLocations = collect(old('removed_location_ids', []))
        ->map(function ($id) use ($allAssignedLocationSource, $modalOpen) {
            $locationId = (int) $id;
            $location = $allAssignedLocationSource->get($locationId);

            if (! $location || $locationId <= 0) {
                return null;
            }

            return [
                'id' => $locationId,
                'end_date' => old(
                    'removed_location_end_dates.' . $locationId,
                    $modalOpen && old('removed_location_end_dates.' . $locationId)
                        ? old('removed_location_end_dates.' . $locationId)
                        : (! empty($location->pivot?->end_date)
                            ? \Illuminate\Support\Carbon::parse($location->pivot->end_date)->format('Y-m-d')
                            : null)
                ),
            ];
        })
        ->filter()
        ->values();
@endphp

<div
    class="service-order-location-manager"
    data-service-order-location-form
    data-modal-key="{{ $modalKey }}"
    data-locations-endpoint="{{ route('api.locations.index') }}"
    data-client-id="{{ $serviceOrder->contract?->client_id }}"
    data-state-id="{{ $serviceOrder->state_id }}"
    data-default-start-date="{{ old('requested_date', optional($serviceOrder->requested_date)->format('Y-m-d')) }}"
    data-initial-selected='@json($initialSelectedLocations)'
    data-initial-removed='@json($initialRemovedLocations)'
    data-executives='@json($operationsExecutives->map(fn ($executive) => ["id" => $executive->id, "name" => $executive->name, "employee_code" => $executive->employee_code])->values())'
>
    <input type="hidden" name="location_sync_submitted" value="1">

    <div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
        <div>
            <h6 class="mb-1">Manage Locations</h6>
            <p class="text-muted small mb-0">Only active locations under {{ $serviceOrder->state?->name ?: 'the selected state' }} are shown for this sales order.</p>
        </div>
        <div class="service-order-location-context text-muted small">
            <div><strong>Client:</strong> {{ $serviceOrder->contract?->client?->name ?: 'N/A' }}</div>
            <div><strong>State:</strong> {{ $serviceOrder->state?->name ?: 'N/A' }}</div>
        </div>
    </div>

    @if($errors->has('location_ids') && $modalOpen)
        <div class="alert alert-danger py-2 px-3 small mb-3">{{ $errors->first('location_ids') }}</div>
    @endif

    <div class="border rounded-3 service-order-location-panel">
        <div class="service-order-location-toolbar p-3 border-bottom d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group service-order-location-search">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control" placeholder="Search location code, name, city" data-location-search>
            </div>
            <div class="service-order-location-bulk-tools d-flex flex-wrap gap-2 align-items-center">
                <select class="form-select form-select-sm" data-bulk-executive style="min-width: 220px;">
                    <option value="">Apply executive to selected</option>
                    @foreach ($operationsExecutives as $executive)
                        <option value="{{ $executive->id }}">{{ $executive->name }} ({{ $executive->employee_code }})</option>
                    @endforeach
                </select>
                <input type="number" min="0" max="15" class="form-control form-control-sm" placeholder="Due days" data-bulk-muster-due style="width: 110px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-apply-bulk-assignments>Apply</button>
            </div>
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="selectAllLocations{{ $serviceOrder->id }}" data-location-select-all>
                <label class="form-check-label" for="selectAllLocations{{ $serviceOrder->id }}">Select All</label>
            </div>
            <span class="small text-muted ms-auto" data-location-summary>No locations selected.</span>
        </div>
        <div class="service-order-location-results" data-location-results></div>
        <div class="p-3 border-top d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="small text-muted" data-location-meta>Loading locations.</span>
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-location-load-more>Load more</button>
        </div>
    </div>

    <div class="d-none" data-location-hidden-inputs></div>
    <small class="text-muted d-block mt-2">Use the scrollbar inside the location list when a state has many locations. Each selected location can keep its own executive, muster due days, and start or end date.</small>
</div>