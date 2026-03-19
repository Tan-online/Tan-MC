<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create a test import and check the lookup table
$import = new \App\Imports\ServiceOrderLocationsImport();

// Use reflection to check what's in the operationsByEmployeeCode array
$reflection = new ReflectionClass($import);
$property = $reflection->getProperty('operationsByEmployeeCode');
$property->setAccessible(true);
$lookupTable = $property->getValue($import);

echo "=== OPERATION BY EMPLOYEE CODE LOOKUP TABLE ===\n";
echo "Total entries: " . count($lookupTable) . "\n\n";

// Show entries related to 000013 or 13
echo "Entries containing '13':\n";
foreach ($lookupTable as $key => $userId) {
    if (strpos($key, '13') !== false) {
        $user = \App\Models\User::find($userId);
        echo "  Key: '$key' => User ID: $userId (Name: {$user->name}, Code: {$user->employee_code})\n";
    }
}

echo "\n\nAll lookup keys:\n";
$keys = array_keys($lookupTable);
sort($keys);
foreach ($keys as $key) {
    echo "  '$key'\n";
}

// Now test the import with this specific data
echo "\n\n=== TESTING PREPAREROW ===\n";

// Test the prepareRow method
$testRow = [
    'sales_order_no' => 'SO/01349/005',
    'location_code' => 'L03',
    'start_date' => '2026-03-10',
    'end_date' => null,
    'operation_executive_employee_code' => 13,  // Excel sends this as integer
    'muster_due_days' => 1,
];

$reflection2 = new ReflectionMethod('\App\Imports\ServiceOrderLocationsImport', 'prepareRow');
$reflection2->setAccessible(true);

$prepared = $reflection2->invoke($import, $testRow);
echo "After prepareRow:\n";
echo "  operation_executive_employee_code: '" . $prepared['operation_executive_employee_code'] . "'\n";

// Test the lookup
$employeeCodeToFind = strtoupper(trim($prepared['operation_executive_employee_code']));
echo "\nLooking up: '$employeeCodeToFind'\n";
echo "Found in lookup: " . (isset($lookupTable[$employeeCodeToFind]) ? 'YES (User ID: ' . $lookupTable[$employeeCodeToFind] . ')' : 'NO') . "\n";
