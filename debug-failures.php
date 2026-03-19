<?php
require 'vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the last failed import
$batch = \App\Models\ImportBatch::query()
    ->where('type', 'service-order-locations')
    ->orderBy('id', 'desc')
    ->first();

if ($batch && !empty($batch->failure_report)) {
    echo "=== LAST FAILED IMPORT (ID: {$batch->id}) ===\n";
    echo "Status: {$batch->status}\n";
    echo "Inserted rows: {$batch->inserted_rows}\n";
    echo "Failed rows: {$batch->failed_rows}\n";
    echo "File: {$batch->original_file_name}\n";
    
    echo "\n=== FAILURES ===\n";
    $failures = $batch->failure_report;
    
    if (is_array($failures)) {
        foreach ($failures as $failure) {
            echo "\nRow {$failure['row']}:\n";
            echo "  Attribute: {$failure['attribute']}\n";
            echo "  Errors: " . implode(', ', $failure['errors']) . "\n";
            
            if (!empty($failure['values'])) {
                echo "  Input values:\n";
                foreach ($failure['values'] as $k => $v) {
                    echo "    $k = '$v' (type: " . gettype($v) . ")\n";
                }
            }
        }
    }
} else {
    echo "No failed imports found\n";
}

// Now simulate importing the actual file to see what happens
echo "\n\n=== SIMULATING FILE READ ===\n";

// Get the actual file from the last import
if ($batch) {
    $filePath = \Illuminate\Support\Facades\Storage::disk($batch->disk)->path($batch->stored_path);
    
    if (file_exists($filePath)) {
        echo "Reading file: $filePath\n";
        
        // Use PhpSpreadsheet to read the file as it was imported
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        
        echo "\nFile content (first 5 rows):\n";
        foreach (array_slice($rows, 0, 5) as $idx => $row) {
            echo "Row $idx: ";
            print_r($row);
        }
    }
}
