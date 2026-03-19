<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check database
$users = \App\Models\User::query()->select('id', 'name', 'employee_code', 'status')->limit(15)->get();

echo "=== USERS IN DATABASE ===\n";
echo str_pad("ID", 5) . str_pad("NAME", 20) . str_pad("EMPLOYEE_CODE", 20) . "STATUS\n";
echo str_repeat("-", 65) . "\n";
foreach ($users as $u) {
    echo str_pad($u->id, 5) . str_pad(substr($u->name, 0, 18), 20) . str_pad($u->employee_code, 20) . $u->status . "\n";
}

// Check sales orders
$orders = \App\Models\ServiceOrder::query()->select('id', 'order_no', 'state_id')->limit(5)->get();
echo "\n=== SALES ORDERS ===\n";
foreach ($orders as $o) {
    echo "ID: {$o->id}, Order: {$o->order_no}, State: {$o->state_id}\n";
}

// Check locations
$locations = \App\Models\Location::query()->select('id', 'code', 'client_id', 'state_id')->limit(5)->get();
echo "\n=== LOCATIONS ===\n";
foreach ($locations as $l) {
    echo "ID: {$l->id}, Code: {$l->code}, Client: {$l->client_id}, State: {$l->state_id}\n";
}

// Check recent import batches
$batches = \App\Models\ImportBatch::query()->orderBy('id', 'desc')->limit(5)->get();
echo "\n=== RECENT IMPORT BATCHES ===\n";
foreach ($batches as $b) {
    echo "ID: {$b->id}, Type: {$b->type}, Status: {$b->status}, Inserted: {$b->inserted_rows}, Failed: {$b->failed_rows}\n";
    if ($b->error_message) {
        echo "  Error: {$b->error_message}\n";
    }
    if (!empty($b->failure_report)) {
        $failures = $b->failure_report;
        if (is_array($failures)) {
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "  Row " . $failure['row'] . ": " . implode(', ', $failure['errors']) . "\n";
            }
        }
    }
}

echo "\n=== IMPORT SIMULATION ===\n";
echo "Testing employee code lookup with your data:\n";

$import = new \App\Imports\ServiceOrderLocationsImport();
// Simulate the prepareRow method
$testCode = "13";  // What Excel sends when it converts 000013
echo "Input: '$testCode' (numeric)\n";

// Simulate prepareRow normalization
if (is_numeric($testCode)) {
    $digits = preg_replace('/\D/', '', $testCode);
    if (!empty($digits)) {
        $normalized = str_pad($digits, 6, '0', STR_PAD_LEFT);
        echo "After normalization: '$normalized'\n";
    }
}
