<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$kernel->handle($request = \Illuminate\Http\Request::capture());

use App\Models\User;

echo "=== CHECKING EMPLOYEE CODE LOOKUPS ===\n\n";

// Get all active users
$users = User::where('status', 'Active')->get(['id', 'employee_code']);

echo "Active Users in Database:\n";
foreach ($users as $user) {
    echo "  ID: {$user->id} | Employee Code: '{$user->employee_code}' (type: " . gettype($user->employee_code) . ")\n";
}

echo "\n=== CHECKING WHAT CODES IMPORT IS LOOKING FOR ===\n\n";

// Simulate the normalizeKey function
$normalizeKey = function ($value) {
    return strtolower(preg_replace('/\s+/', '', (string) $value));
};

// Test codes from Excel
$testCodes = ["13", "000013", "0013", "00013"];

echo "Looking for these codes:\n";
foreach ($testCodes as $code) {
    $normalized = $normalizeKey($code);
    echo "  Input: '$code' → Normalized: '$normalized'\n";
}

echo "\n=== BUILDING LOOKUP TABLE (LIKE IMPORT DOES) ===\n\n";

$operationsByEmployeeCode = [];

foreach ($users as $user) {
    $employeeCode = (string) $user->employee_code;
    $normalizedKey = $normalizeKey($employeeCode);
    
    // Primary entry
    $operationsByEmployeeCode[$normalizedKey] = $user->id;
    echo "Added primary: '$normalizedKey' → User ID {$user->id}\n";
    
    // Extract numeric parts
    preg_match_all('/(\d+)/', $employeeCode, $matches);
    
    if (!empty($matches[1])) {
        $numericSequences = array_unique(array_filter(array_map('trim', $matches[1])));
        
        foreach ($numericSequences as $numericPart) {
            $intValue = (int) $numericPart;
            
            // 6-digit padding
            $padded6 = str_pad((string) $intValue, 6, '0', STR_PAD_LEFT);
            $normalizedPadded6 = $normalizeKey($padded6);
            $operationsByEmployeeCode[$normalizedPadded6] = $user->id;
            echo "  Added variation (pad-6): '$normalizedPadded6' → User ID {$user->id}\n";
            
            // Unpadded
            $normalizedUnpadded = $normalizeKey((string) $intValue);
            $operationsByEmployeeCode[$normalizedUnpadded] = $user->id;
            echo "  Added variation (unpadded): '$normalizedUnpadded' → User ID {$user->id}\n";
            
            // Other paddings
            for ($padding = 3; $padding <= 10; $padding++) {
                $padded = str_pad((string) $intValue, $padding, '0', STR_PAD_LEFT);
                $normalizedPadded = $normalizeKey($padded);
                $operationsByEmployeeCode[$normalizedPadded] = $user->id;
                echo "  Added variation (pad-$padding): '$normalizedPadded' → User ID {$user->id}\n";
            }
        }
    }
}

echo "\n=== TESTING LOOKUPS ===\n\n";

foreach ($testCodes as $code) {
    $lookup = $normalizeKey($code);
    $found = isset($operationsByEmployeeCode[$lookup]);
    echo "Code '$code' (normalized: '$lookup') → " . ($found ? "FOUND (User ID: {$operationsByEmployeeCode[$lookup]})" : "NOT FOUND ❌") . "\n";
}

echo "\n=== ALL LOOKUP KEYS ===\n\n";
echo "Available keys in lookup:\n";
foreach (array_keys($operationsByEmployeeCode) as $key) {
    echo "  '$key'\n";
}
