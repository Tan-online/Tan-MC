<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    \Illuminate\Http\Request::capture()
);

$file = 'c:\\Users\\tandr\\Downloads\\service-order-locations-import-template (3).xlsx';

echo "=== TESTING IMPORT WITH DIAGNOSTICS ===\n\n";

// Read with PhpOffice
echo "[1/2] PhpOffice Raw Read:\n";
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setReadDataOnly(false);
$spreadsheet = $reader->load($file);
$worksheet = $spreadsheet->getActiveSheet();

for ($row = 1; $row <= 3; $row++) {
    echo "Row $row: ";
    for ($col = 'A'; $col <= 'F'; $col++) {
        $cell = $worksheet->getCell($col . $row);
        $value = $cell->getValue();
        $type = $cell->getDataType();
        echo "[$col($type)=$value] ";
    }
    echo "\n";
}

// Read with Maatwebsite Excel (through import class)
echo "\n[2/2] Testing ServiceOrderLocationsImport Collection Processing:\n\n";

try {
    $import = new \App\Imports\ServiceOrderLocationsImport();
    
    // Create a mock collection to see what the import receives
    $collection = collect([
        ['SO/01349/005', 'L03', '2026-03-10', null, '000013', 1],
        ['SO/100359/01', 'L03', '2026-03-10', null, '000013', 2],
    ]);
    
    echo "Mock data being processed:\n";
    foreach ($collection as $index => $row) {
        echo "  Row $index (array input): " . json_encode($row) . "\n";
    }
    
    echo "\nCalling normalizeEmployeeCode on test values:\n";
    $testCodes = ['000013', '13', 13, '13 ', ' 000013'];
    foreach ($testCodes as $code) {
        $normalized = $import->normalizeEmployeeCode($code);
        echo "  Input: " . json_encode($code) . " → Output: " . json_encode($normalized) . "\n";
    }
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n✓ Diagnostics complete\n";
