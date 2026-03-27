<?php

require 'vendor/autoload.php';

echo "=== TRACING VALUE CONVERSION ===\n\n";

// Read the Excel first
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load('C:/Users/tandr/Downloads/service-order-locations-import-template (3).xlsx');
$sheet = $spreadsheet->getActiveSheet();

$value_e2 = $sheet->getCell('E2')->getValue();
echo "1. Excel E2 raw value: " . var_export($value_e2, true) . " (type: " . gettype($value_e2) . ")\n";

// Test what normalize() does
$testValue = $value_e2;
$trimmed = trim((string) $testValue);
echo "2. After trim((string)value): " . var_export($trimmed, true) . " (type: " . gettype($trimmed) . ")\n";

// Test what normalizeKey() does
$upper = strtoupper(trim($testValue));
echo "3. After strtoupper(trim(value)): " . var_export($upper, true) . " (type: " . gettype($upper) . ")\n";

// Now test with Maatwebsite Excel library
echo "\n\n=== TESTING WITH MAATWEBSITE ===\n\n";

class TestImport implements \Maatwebsite\Excel\Concerns\WithHeadingRow, \Maatwebsite\Excel\Concerns\ToCollection
{
    public function collection(\Illuminate\Support\Collection $collection)
    {
        echo "Collection has " . count($collection) . " rows\n\n";
        
        foreach ($collection as $index => $row) {
            echo "Row " . ($index + 1) . ":\n";
            echo "  Raw row type: " . gettype($row) . "\n";
            
            if ($row instanceof \Illuminate\Support\Collection) {
                $rowArray = $row->toArray();
            } else {
                $rowArray = (array)$row;
            }
            
            echo "  operation_executive_employee_code exists: " . (isset($rowArray['operation_executive_employee_code']) ? 'yes' : 'no') . "\n";
            if (isset($rowArray['operation_executive_employee_code'])) {
                $val = $rowArray['operation_executive_employee_code'];
                echo "  Value: " . var_export($val, true) . " (type: " . gettype($val) . ", length: " . strlen($val) . ")\n";
            }
            
            if ($index >= 1) break; // Only first row
        }
    }
}

try {
    \Maatwebsite\Excel\Facades\Excel::import(new TestImport(), 'C:/Users/tandr/Downloads/service-order-locations-import-template (3).xlsx');
} catch (\Exception $e) {
    echo "Exception during import: " . $e->getMessage() . "\n";
}
