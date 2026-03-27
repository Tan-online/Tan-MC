<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = \Illuminate\Http\Request::capture()
);

echo "=== VERIFICATION: Check if employee codes are preserved correctly ===\n\n";

// Check app log for the import debug messages
$logFile = storage_path('logs/laravel.log');

if (file_exists($logFile)) {
    echo "[1/2] Reading logs from: $logFile\n\n";
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $relevantLines = [];
    
    foreach ($lines as $line) {
        if (strpos($line, 'normalizeEmployeeCode') !== false) {
            $relevantLines[] = $line;
        }
    }
    
    if (!empty($relevantLines)) {
        echo "Found " . count($relevantLines) . " employee code normalization logs:\n";
        foreach (array_slice($relevantLines, -5) as $line) {
            echo "  $line\n";
        }
    } else {
        echo "No employee code normalization logs found.\n";
    }
} else {
    echo "Log file not found: $logFile\n";
}

echo "\n[2/2] Checking database for service orders with operation executives...\n";

// Find service orders that were recently updated
$serviceOrders = \App\Models\ServiceOrder::query()
    ->with('locations')
    ->orderBy('updated_at', 'desc')
    ->limit(5)
    ->get();

if ($serviceOrders->count() > 0) {
    echo "Found " . $serviceOrders->count() . " recent service orders:\n\n";
    foreach ($serviceOrders as $so) {
        echo "Service Order: {$so->order_no}\n";
        if ($so->locations()->count() > 0) {
            foreach ($so->locations as $location) {
                $executive = $location->operationExecutive ?? $location->pivot->operation_executive_id;
                if ($executive) {
                    echo "  Location: {$location->code}\n";
                    if ($location->operationExecutive) {
                        echo "    Operation Executive: {$location->operationExecutive->employee_code}\n";
                    } else {
                        echo "    Operation Executive ID: {$location->pivot->operation_executive_id}\n";
                    }
                }
            }
        }
    }
} else {
    echo "No recent service orders found.\n";
}

echo "\n✓ Verification complete!\n";
