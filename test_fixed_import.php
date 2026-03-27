<?php

require 'vendor/autoload.php';

// Bootstrap Laravel properly
$app = require 'bootstrap/app.php';

// Register all service providers
$app->register(\App\Providers\AppServiceProvider::class);

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ServiceOrderLocationsImport;

$file = 'C:/Users/tandr/Downloads/service-order-locations-import-template (3).xlsx';

echo "=== TESTING FIXED IMPORT ===\n\n";
echo "File: " . $file . "\n\n";

try {
    $import = new ServiceOrderLocationsImport();
    Excel::import($import, $file);
    
    echo "✓ Import completed successfully!\n";
    echo "Inserted: " . $import->insertedCount() . " rows\n";
    
    if ($import->failures()) {
        echo "\n✗ Import had failures:\n";
        foreach ($import->failures() as $failure) {
            echo "\nRow " . $failure['row'] . ": " . $failure['attribute'] . "\n";
            echo "Errors: " . implode(", ", $failure['errors']) . "\n";
            echo "Values: " . json_encode($failure['values'], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "\n✓ No failures - all rows imported successfully!\n";
    }
    
} catch (\Throwable $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nStacktrace:\n" . $e->getTraceAsString() . "\n";
}
