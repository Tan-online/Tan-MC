<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check users
// Test the fuzzy matching logic
echo "\n\n=== Testing Fuzzy Matching Logic ===\n";
$testEmployeeCode = "000013";
echo "Testing with employee_code: $testEmployeeCode\n";

// Simulate the fuzzy matching creation
$operationsByEmployeeCode = [];
$employeeCode = (string) $testEmployeeCode;
$normalizedKey = strtoupper(trim($employeeCode));

echo "1. Primary entry: '$normalizedKey' => 13\n";
$operationsByEmployeeCode[$normalizedKey] = 13;

// Extract numeric parts for fuzzy matching
preg_match_all('/(\d+)/', $employeeCode, $matches);
echo "2. preg_match_all matched: " . json_encode($matches[1]) . "\n";

if (!empty($matches[1])) {
    $numericSequences = array_unique(array_filter(array_map('trim', $matches[1])));
    echo "3. Numeric sequences: " . json_encode($numericSequences) . "\n";
    
    foreach ($numericSequences as $numericPart) {
        echo "   Processing: '$numericPart'\n";
        $intValue = (int) $numericPart;
        echo "     intValue: $intValue\n";
        
        $padded6 = str_pad((string) $intValue, 6, '0', STR_PAD_LEFT);
        echo "     padded6: '$padded6'\n";
        $operationsByEmployeeCode[strtoupper(trim($padded6))] = 13;
        
        $unpadded = strtoupper(trim((string) $intValue));
        echo "     unpadded: '$unpadded'\n";
        $operationsByEmployeeCode[$unpadded] = 13;
        
        for ($padding = 3; $padding <= 10; $padding++) {
            $padded = str_pad((string) $intValue, $padding, '0', STR_PAD_LEFT);
            $operationsByEmployeeCode[strtoupper(trim($padded))] = 13;
        }
    }
}

echo "\n4. Final fuzzy matching dictionary:\n";
foreach ($operationsByEmployeeCode as $key => $value) {
    echo "   '$key' => $value\n";
}

// Now test the lookup
echo "\n5. Lookup test:\n";
$lookupCode = "13";
$normalizedLookup = strtoupper(trim($lookupCode));
echo "   Looking for: '$normalizedLookup'\n";
echo "   Found: " . (isset($operationsByEmployeeCode[$normalizedLookup]) ? "YES" : "NO") . "\n";

// Check service_order_location structure
echo "\nService Order Location table columns:\n";
$columns = \Illuminate\Support\Facades\DB::getSchemaBuilder()->getColumnListing('service_order_location');
echo "  " . implode(", ", $columns) . "\n";

// Check recent import batches
echo "\nRecent import batches:\n";
$batches = \App\Models\ImportBatch::latest()->limit(3)->get();
foreach ($batches as $batch) {
    echo "  - [{$batch->id}] {$batch->type}: {$batch->status} (inserted: {$batch->inserted_rows}, failed: {$batch->failed_rows}) at {$batch->created_at}\n";
    if ($batch->failed_rows > 0 && $batch->failure_report) {
        echo "      Failures:\n";
        foreach ($batch->failure_report as $row => $errors) {
            echo "        Row {$row}: " . json_encode($errors) . "\n";
        }
    }
}
if ($batches->isEmpty()) {
    echo "  (No import batches found)\n";
}
