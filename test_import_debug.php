<?php

require 'vendor/autoload.php';

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ServiceOrderLocationsImport;

// Bootstrap
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->handle(
    $request = \Illuminate\Http\Request::capture()
);

$excel = 'C:/Users/tandr/Downloads/service-order-locations-import-template (3).xlsx';

echo "=== TEST IMPORT ===\n\n";
echo "File: " . $excel . "\n\n";

try {
    $import = new ServiceOrderLocationsImport();
    
    // Get collection directly 
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($excel);
    $sheet = $spreadsheet->getActiveSheet();
    
    echo "Direct PhpSpreadsheet read:\n";
    echo "E2 raw value: " . var_export($sheet->getCell('E2')->getValue(), true) . "\n";
    echo "E2 data type: " . $sheet->getCell('E2')->getDataType() . "\n";
    echo "E3 raw value: " . var_export($sheet->getCell('E3')->getValue(), true) . "\n";
    echo "E3 data type: " . $sheet->getCell('E3')->getDataType() . "\n";
    
    echo "\nTesting actual import flow...\n";
    Excel::import($import, $excel);
    
    echo "\nImport completed!\n";
    echo "Inserted: " . $import->insertedCount() . " rows\n";
    
    if ($import->failures()) {
        echo "\nFailures:\n";
        foreach ($import->failures() as $failure) {
            echo "Row " . $failure['row'] . ": " . $failure['attribute'] . " - " . implode(", ", $failure['errors']) . "\n";
            echo "Values: " . json_encode($failure['values']) . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
