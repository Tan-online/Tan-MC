@php
    $editing = $serviceOrder !== null;
    $selectedClientId = old('client_id', $serviceOrder?->contract?->client_id);
    $selectedContractId = old('contract_id', $serviceOrder?->contract_id);
    $selectedStateId = old('state_id', $serviceOrder?->state_id ?? $serviceOrder?->location?->state_id ?? $serviceOrder?->locations->first()?->state_id);
    $selectedStatus = old('status', $serviceOrder?->display_status ?? 'Active');
@endphp

<div
    class="row g-2 service-order-form"
    data-contract-client-filter
    data-modal-key="{{ $modalKey }}"
    data-basic-service-order-form
>
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Client</label>
        <select name="client_id" class="form-select @if($errors->has('client_id') && session('open_modal') === $modalKey) is-invalid @endif" data-client-select required>
            <option value="">Select client</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected((int) $selectedClientId === (int) $client->id)>{{ $client->name }} ({{ $client->code }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Contract</label>
        <select name="contract_id" class="form-select @if($errors->has('contract_id') && session('open_modal') === $modalKey) is-invalid @endif" data-contract-select required>
            <option value="">Select contract</option>
            @foreach ($contracts as $contract)
                <option value="{{ $contract->id }}" data-client-id="{{ $contract->client_id }}" @selected((int) $selectedContractId === (int) $contract->id)>{{ $contract->contract_no }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-4 col-md-6">
        <label class="form-label">State</label>
        <select name="state_id" class="form-select @if($errors->has('state_id') && session('open_modal') === $modalKey) is-invalid @endif" data-state-select required>
            <option value="">Select state</option>
            @foreach ($states as $state)
                <option value="{{ $state->id }}" data-client-ids="{{ implode(',', $stateClientMap[$state->id] ?? []) }}" @selected((int) $selectedStateId === (int) $state->id)>{{ $state->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-lg-4 col-md-6">
        <label class="form-label">Sales Order No</label>
        <input type="text" name="order_no" class="form-control @if($errors->has('order_no') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('order_no', $serviceOrder?->order_no) }}" required>
    </div>

    <div class="col-lg-4 col-md-6">
        <label class="form-label">SO Name</label>
        <input type="text" name="so_name" class="form-control @if($errors->has('so_name') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('so_name', $serviceOrder?->so_name) }}" placeholder="Enter sales order name">
    </div>

    <div class="col-lg-4 col-md-6">
        <label class="form-label">SO Start Date</label>
        <input type="date" name="requested_date" class="form-control @if($errors->has('requested_date') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('requested_date', optional($serviceOrder?->requested_date)->format('Y-m-d')) }}" data-so-start-date required>
    </div>
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Muster Cycle Start Day (1-31)</label>
        <input type="number" min="1" max="31" name="muster_start_day" class="form-control @if($errors->has('muster_start_day') && session('open_modal') === $modalKey) is-invalid @endif" value="{{ old('muster_start_day', $serviceOrder?->muster_start_day ?? 1) }}" required>
    </div>
    <div class="col-lg-4 col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select @if($errors->has('status') && session('open_modal') === $modalKey) is-invalid @endif" required>
            @foreach ($statusOptions as $statusOption)
                <option value="{{ $statusOption }}" @selected($selectedStatus === $statusOption)>{{ $statusOption }}</option>
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
        <div class="service-order-location-note border rounded-3 px-3 py-2 bg-light-subtle text-muted small">
            Save the sales order first, then use the listing action to add or update locations for the selected state.
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control @if($errors->has('remarks') && session('open_modal') === $modalKey) is-invalid @endif" rows="2">{{ old('remarks', $serviceOrder?->remarks) }}</textarea>
    </div>
</div>
