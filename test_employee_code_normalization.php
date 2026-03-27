<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    \Illuminate\Http\Request::capture()
);

echo "=== EMPLOYEE CODE NORMALIZATION TEST ===\n\n";

$import = new \App\Imports\ServiceOrderLocationsImport();

// Test cases for the normalizeEmployeeCode method
$testCases = [
    '000013' => '000013',      // Already formatted - should stay same
    13 => '000013',             // Integer - should pad to 6 digits
    '13' => '000013',           // String number - should pad to 6 digits
    '13 ' => '000013',          // With whitespace - should trim and pad
    ' 000013' => '000013',      // Leading whitespace - should trim
    'EMP-000013' => '000013',   // With prefix - should extract digits and pad
    'EMP-13' => '000013',       // With prefix and no leading zeros - should extract and pad
    '001' => '000001',          // 3 digits - should pad to 6
    '1' => '000001',            // 1 digit - should pad to 6
    null => null,               // Null - should stay null
    '' => null,                 // Empty string - should return null
    '   ' => null,              // Whitespace only - should return null
];

echo "Testing normalization edge cases:\n";
$passed = 0;
$failed = 0;

foreach ($testCases as $input => $expected) {
    $result = $import->normalizeEmployeeCode($input);
    $status = ($result === $expected) ? '✓' : '✗';
    
    if ($result === $expected) {
        $passed++;
    } else {
        $failed++;
    }
    
    $inputDisplay = json_encode($input);
    $resultDisplay = json_encode($result);
    $expectedDisplay = json_encode($expected);
    
    echo "$status Input: $inputDisplay → Expected: $expectedDisplay, Got: $resultDisplay\n";
}

echo "\n=== RESULTS ===\n";
echo "Passed: $passed/" . count($testCases) . "\n";
echo "Failed: $failed/" . count($testCases) . "\n";

if ($failed == 0) {
    echo "\n✓ ALL TESTS PASSED - CODE IS ROBUST\n";
} else {
    echo "\n✗ Some tests failed - review needed\n";
}
