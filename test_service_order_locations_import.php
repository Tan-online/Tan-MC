<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
/** @var \Illuminate\Contracts\Foundation\Application $app */
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = \Illuminate\Http\Request::capture()
);

use App\Imports\ServiceOrderLocationsImport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

echo "=== SERVICE ORDER LOCATIONS IMPORT TEST ===\n\n";

$excelFile = 'c:\\Users\\tandr\\Downloads\\service-order-locations-import-template (3).xlsx';

if (!file_exists($excelFile)) {
    echo "ERROR: Excel file not found: $excelFile\n";
    exit(1);
}

echo "Testing import from: $excelFile\n\n";

try {
    $import = new ServiceOrderLocationsImport();
    
    echo "[1/3] Starting Excel import...\n";
    Excel::import($import, $excelFile);
    
    echo "[2/3] Import completed!\n";
    echo "  - Inserted: " . $import->insertedCount() . "\n";
    
    $failureCount = count($import->failures());
    echo "  - Failures: " . $failureCount . "\n";
    
    if ($failureCount > 0) {
        echo "\n*** FAILURES DETECTED ***\n";
        foreach ($import->failures() as $failure) {
            echo "  Row " . $failure['row'] . ": " . implode(', ', $failure['errors']) . "\n";
            if (isset($failure['values'])) {
                echo "    Values: " . json_encode($failure['values']) . "\n";
            }
        }
    }
    
    echo "\n[3/3] Verifying database entries...\n";
    
    // Check the database for the imported data
    $conn = app('db')->connection();
    
    // Look for service_order_location with operation_executive_id for the test data
    $results = DB::table('service_order_location')
        ->where('operation_executive_id', '!=', null)
        ->where('updated_at', '>=', now()->subHours(1))
        ->get();
    
    if ($results->count() > 0) {
        echo "\nDatabase verification:\n";
        foreach ($results as $record) {
            echo "  Service Order ID: {$record->service_order_id}\n";
            echo "  Location ID: {$record->location_id}\n";
            echo "  Operation Executive ID: {$record->operation_executive_id}\n";
            
            // Get the employee code from the user
            $user = \App\Models\User::find($record->operation_executive_id);
            if ($user) {
                echo "  Employee (from executive_id): {$user->employee_code}\n";
            }
        }
    } else {
        echo "\nNo recent database entries found.\n";
    }
    
    echo "\n✓ Import test completed successfully!\n";
    
} catch (\Throwable $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
