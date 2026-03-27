<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
<table>
    <thead>
        <tr>
            <th>Client Name</th>
            <th>SO Number</th>
            <th>SO Start Date</th>
            <th>Location Code</th>
            <th>Location Name</th>
            <th>Location Start Date</th>
            <th>Location End Date</th>
            <th>Executive</th>
            <th>Status</th>
            <th>Type</th>
            <th>Action Date &amp; Time</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach($locationRows as $row)
            @php
                $statusLabel = match($row->status) {
                    'pending' => 'Pending',
                    'submitted' => 'Submitted',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'returned' => 'Returned',
                    default => ucfirst($row->status)
                };
                
                $typeLabel = $row->submission_type ? match($row->submission_type) {
                    'hard_copy' => 'Hard Copy',
                    'email' => 'Email',
                    'courier' => 'Courier',
                    'soft_copy_upload' => 'Soft Copy Upload',
                    default => ucfirst(str_replace('_', ' ', $row->submission_type))
                } : '';
            @endphp
            <tr>
                <td>{{ $row->client_name }}</td>
                <td>{{ $row->so_number }}</td>
                <td>{{ $row->so_start_date ? \Carbon\Carbon::parse($row->so_start_date)->format('d-m-Y') : '' }}</td>
                <td>{{ $row->location_code ?? 'N/A' }}</td>
                <td>{{ $row->location_name }}</td>
                <td>{{ $row->location_start_date ? \Carbon\Carbon::parse($row->location_start_date)->format('d-m-Y') : '' }}</td>
                <td>{{ $row->location_end_date ? \Carbon\Carbon::parse($row->location_end_date)->format('d-m-Y') : '' }}</td>
                <td>{{ $row->executive_name ?? 'Unassigned' }}</td>
                <td>{{ $statusLabel }}</td>
                <td>{{ $typeLabel }}</td>
                <td>{{ $row->submitted_at ? \Carbon\Carbon::parse($row->submitted_at)->format('d-m-Y H:i') : '' }}</td>
                <td>{{ $row->remarks ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
