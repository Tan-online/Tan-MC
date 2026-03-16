@php
    $editing = $serviceOrder !== null;
    $selectedClientId = old('client_id', $serviceOrder?->contract?->client_id);
    $selectedContractId = old('contract_id', $serviceOrder?->contract_id);
    $selectedTeamId = old('team_id', $serviceOrder?->team_id);
    $selectedExecutiveId = old('operation_executive_id', $serviceOrder?->operation_executive_id);
    $selectedLocationIds = collect(old('location_ids', $editing ? $serviceOrder->locations->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values()
        ->all();
@endphp

<div class="row g-3" data-contract-client-filter>
    <div class="col-md-4">
        <label class="form-label">Client</label>
        <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === $modalKey) is-invalid @endif" data-client-select required>
            <option value="">Select client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((int) $selectedClientId === (int) $client->id)>{{ $client->name }} ({{ $client->code }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Contract</label>
        <select name="contract_id" class="form-select @if($errors->has('contract_id') && session('open_modal') === $modalKey) is-invalid @endif" data-contract-select required>
            <option value="">Select contract</option>
            @foreach ($contracts as $contract)
                <option value="{{ $contract->id }}" data-client-id="{{ $contract->client_id }}" @selected((int) $selectedContractId === (int) $contract->id)>{{ $contract->contract_no }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Sales Order No</label>
        <input type="text" name="order_no" class="form-control @if($errors->has('order_no') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('order_no', $serviceOrder?->order_no) }}" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Requested Date</label>
        <input type="date" name="requested_date" class="form-control @if($errors->has('requested_date') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('requested_date', optional($serviceOrder?->requested_date)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Muster Cycle Start Day (1-31)</label>
        <input type="number" min="1" max="31" name="muster_start_day" class="form-control @if($errors->has('muster_start_day') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('muster_start_day', $serviceOrder?->muster_start_day ?? 1) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Muster Due Days</label>
        <input type="number" min="0" max="15" name="muster_due_days" class="form-control @if($errors->has('muster_due_days') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('muster_due_days', $serviceOrder?->muster_due_days ?? 0) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">Assigned Team</label>
        <select name="team_id" class="form-select @if($errors->has('team_id') && session('open_modal') === $modalKey) is-invalid @endif">
            <option value="">Unassigned</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected((int) $selectedTeamId === (int) $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Operation Executive</label>
        <select name="operation_executive_id" class="form-select @if($errors->has('operation_executive_id') && session('open_modal') === $modalKey) is-invalid @endif">
            <option value="">Unassigned</option>
            @foreach ($operationsExecutives as $executive)
                <option value="{{ $executive->id }}" @selected((int) $selectedExecutiveId === (int) $executive->id)>{{ $executive->name }} ({{ $executive->employee_code }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === $modalKey) is-invalid @endif" required>
            @foreach ($statusOptions as $statusOption)
                <option value="{{ $statusOption }}" @selected(old('status', $serviceOrder?->status ?? 'Open') === $statusOption)>{{ $statusOption }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="serviceOrderAutoMuster{{ $editing ? '-' . $serviceOrder->id : '' }}" name="auto_generate_muster" value="1" @checked(session('open_modal') === $modalKey ? old('auto_generate_muster') : ($serviceOrder?->auto_generate_muster ?? true))>
            <label class="form-check-label" for="serviceOrderAutoMuster{{ $editing ? '-' . $serviceOrder->id : '' }}">Auto generate muster cycles</label>
        </div>
    </div>

    <div class="col-12">
        <h6 class="mb-2">Location Mapping</h6>
        @if($errors->has('location_ids') && session('open_modal') === $modalKey)
            <div class="text-danger small mb-2">{{ $errors->first('location_ids') }}</div>
        @endif
        <div class="table-responsive border rounded">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">Use</th>
                        <th>Location</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($locations as $location)
                        @php
                            $locationId = (int) $location->id;
                            $checked = in_array($locationId, $selectedLocationIds, true);
                            $pivot = $editing ? $serviceOrder->locations->firstWhere('id', $locationId)?->pivot : null;
                            $startValue = old('location_start_dates.' . $locationId, !empty($pivot?->start_date) ? \Illuminate\Support\Carbon::parse($pivot->start_date)->format('Y-m-d') : null);
                            $endValue = old('location_end_dates.' . $locationId, !empty($pivot?->end_date) ? \Illuminate\Support\Carbon::parse($pivot->end_date)->format('Y-m-d') : null);
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" name="location_ids[]" value="{{ $locationId }}" @checked($checked)>
                            </td>
                            <td>{{ $location->name }}{{ $location->city ? ' - ' . $location->city : '' }}</td>
                            <td>
                                <input type="date" name="location_start_dates[{{ $locationId }}]" class="form-control form-control-sm" value="{{ $startValue }}">
                            </td>
                            <td>
                                <input type="date" name="location_end_dates[{{ $locationId }}]" class="form-control form-control-sm" value="{{ $endValue }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <small class="text-muted">Select one or more locations that belong to the selected contract. Optional start and end dates can define temporary location coverage.</small>
    </div>

    <div class="col-12">
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control @if($errors->has('remarks') && session('open_modal') === $modalKey) is-invalid @endif" rows="4">{{ old('remarks', $serviceOrder?->remarks) }}</textarea>
    </div>
</div>
