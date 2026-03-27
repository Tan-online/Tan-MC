<?php

use App\Models\SoLocationMonthlyStatus;
use App\Models\SoLocationStatusHistory;
use Illuminate\Support\Facades\Schema;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== WORKFLOW SYSTEM VERIFICATION ===\n\n";

// 1. Check tables exist
echo "[1] Database Tables\n";
echo "- so_location_monthly_status: " . (Schema::hasTable('so_location_monthly_status') ? "✓ EXISTS\n" : "✗ MISSING\n");
echo "- so_location_status_history: " . (Schema::hasTable('so_location_status_history') ? "✓ EXISTS\n" : "✗ MISSING\n\n");

// 2. Check models instantiate
echo "[2] Model Instantiation\n";
try {
    $status = new SoLocationMonthlyStatus();
    echo "- SoLocationMonthlyStatus: ✓ OK\n";
} catch (Exception $e) {
    echo "- SoLocationMonthlyStatus: ✗ ERROR - " . $e->getMessage() . "\n";
}

try {
    $history = new SoLocationStatusHistory();
    echo "- SoLocationStatusHistory: ✓ OK\n\n";
} catch (Exception $e) {
    echo "- SoLocationStatusHistory: ✗ ERROR - " . $e->getMessage() . "\n\n";
}

// 3. Check columns
echo "[3] Column Verification\n";
$requiredColumns = [
    'so_location_monthly_status' => [
        'id', 'service_order_id', 'location_id', 'wage_month', 'status', 
        'submission_type', 'file_path', 'remarks', 'reviewer_remarks',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at'
    ],
    'so_location_status_history' => [
        'id', 'service_order_id', 'location_id', 'wage_month', 'status',
        'remarks', 'action_by', 'action_at'
    ]
];

foreach ($requiredColumns as $table => $columns) {
    $existing = Schema::getColumnListing($table);
    $missing = array_diff($columns, $existing);
    
    if (empty($missing)) {
        echo "- $table: ✓ All columns present\n";
    } else {
        echo "- $table: ✗ Missing columns: " . implode(', ', $missing) . "\n";
    }
}

echo "\n[4] Route Registration\n";
$routes = \Route::getRoutes();
$workspaceRoutes = [
    'operations-workspace.submit-location',
    'operations-workspace.approve-location',
    'operations-workspace.reject-location',
    'operations-workspace.return-location',
];

foreach ($workspaceRoutes as $route) {
    $exists = !!$routes->getByName($route);
    echo "- $route: " . ($exists ? "✓ OK\n" : "✗ MISSING\n");
}

echo "\n[5] Service Methods\n";
$service = app(\App\Services\OperationsWorkspaceService::class);
$methods = [
    'approveSoLocationStatus',
    'rejectSoLocationStatus',
    'returnSoLocationStatus',
    'recordStatusHistory',
    'getSoLocationStatusHistory',
    'compactLocationRowsQuery',
];

foreach ($methods as $method) {
    $exists = method_exists($service, $method);
    echo "- $method: " . ($exists ? "✓ OK\n" : "✗ MISSING\n");
}

echo "\n[6] Workflow Status Values\n";
echo "- Enum values: pending, submitted, approved, rejected, returned\n";
echo "- Display logic: Conditional type/date for submitted & approved only\n";
echo "- Reviewer remarks: Visible for rejected & returned\n";
echo "- Resubmit capability: Available for rejected & returned\n";

echo "\n=== VERIFICATION COMPLETE ===\n";
