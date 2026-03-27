<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Input Value Handling ===\n\n";

// Get the actual employee codes from the database
$users = \App\Models\User::query()->where('id', 13)->first();  // Debraj

echo "User ID 13 (Debraj):\n";
echo "  Employee Code: " . json_encode($users?->employee_code) . "\n";
echo "  Type: " . gettype($users?->employee_code) . "\n\n";

// Now simulate what happens in the import constructor
echo "=== Creating Fuzzy Matching Dictionary ===\n\n";

$operationsByEmployeeCode = [];

$employeeCode = (string) $users->employee_code;  // "000013"
$normalizedKey = strtoupper(trim($employeeCode));    // "000013"
            
echo "1. Primary entry: '$normalizedKey' => {$users->id}\n";
$operationsByEmployeeCode[$normalizedKey] = $users->id;

// Extract numeric parts for fuzzy matching
preg_match_all('/(\d+)/', $employeeCode, $matches);
if (!empty($matches[1])) {
    $numericSequences = array_unique(array_filter(array_map('trim', $matches[1])));
    
    foreach ($numericSequences as $numericPart) {
        $intValue = (int) $numericPart;
        
        // Add the numeric value padded to 6 digits
        $padded6 = str_pad((string) $intValue, 6, '0', STR_PAD_LEFT);
        $operationsByEmployeeCode[strtoupper(trim($padded6))] = $users->id;
        
        // Also add un-padded numeric value
        $operationsByEmployeeCode[strtoupper(trim((string) $intValue))] = $users->id;
        
        // Add other common padding lengths
        for ($padding = 3; $padding <= 10; $padding++) {
            $padded = str_pad((string) $intValue, $padding, '0', STR_PAD_LEFT);
            $operationsByEmployeeCode[strtoupper(trim($padded))] = $users->id;
        }
    }
}

echo "\n2. Final dictionary:\n";
foreach ($operationsByEmployeeCode as $key => $value) {
    echo "   '$key' => $value\n";
}

// Now test the LOOKUP with value "13"
echo "\n\n=== Testing Lookup with Value 13 ===\n\n";

$inputValue = 13;  // Numeric from Excel
echo "Input value from Excel (numeric): " . json_encode($inputValue) . "\n";
echo "Type: " . gettype($inputValue) . "\n\n";

// Step 1: prepareRow would convert "13" to "000013"
$code = $inputValue;          // 13
$digits = preg_replace('/\D/', '', (string)$code);  // "13"
if (! empty($digits)) {
    $code = str_pad($digits, 6, '0', STR_PAD_LEFT);  // "000013"
}
echo "Step 1 - prepareRow conversion:\n";
echo"  Input: " . json_encode($inputValue) . "\n";
echo "  Output: " . json_encode($code) . "\n\n";

// Step 2: normalizeKey for lookup
$lookupKey = strtoupper(trim((string)$code));  // "000013"
echo "Step 2 - normalizeKey for lookup:\n";
echo "  Input: " . json_encode($code) . "\n";
echo "  Output: " . json_encode($lookupKey) . "\n\n";

// Step 3: Lookup in dictionary
echo "Step 3 - Dictionary lookup:\n";
echo "  Looking for: '$lookupKey'\n";
echo "  Found: " . (isset($operationsByEmployeeCode[$lookupKey]) ? "YES (ID: {$operationsByEmployeeCode[$lookupKey]})" : "NO") . "\n";
