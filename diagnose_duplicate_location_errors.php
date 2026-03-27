<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$kernel->handle($request = \Illuminate\Http\Request::capture());

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

echo "=== CHECKING FAILED IMPORT BATCHES ===\n\n";

// Get failed imports from the last few days
$failedImports = ImportBatch::query()
    ->where('type', 'service-order-locations')
    ->where('failed_rows', '>', 0)
    ->latest()
    ->limit(5)
    ->get();

echo "Found " . $failedImports->count() . " failed/partial import batches:\n\n";

foreach ($failedImports as $import) {
    echo "─── Import Batch ID: {$import->id} ───\n";
    echo "File: {$import->original_file_name}\n";
    echo "Time: {$import->completed_at}\n";
    echo "Inserted: {$import->inserted_rows} | Errors: {$import->failed_rows}\n";
    
    if (!empty($import->failure_report)) {
        $failures = $import->failure_report;
        
        echo "\nError Details:\n";
        foreach ($failures as $i => $failure) {
            echo "  Row {$failure['row']}: ";
            echo implode(', ', $failure['errors']) . "\n";
            
            if (isset($failure['values'])) {
                echo "    Values: " . json_encode($failure['values']) . "\n";
            }
        }
    }
    
    echo "\n";
}

echo "\n=== CHECKING FOR DUPLICATE LOCATIONS IN SERVICE_ORDER_LOCATION TABLE ===\n\n";

// Check for duplicate location assignments to same service order
$duplicates = DB::table('service_order_location')
    ->select('service_order_id', 'location_id', DB::raw('COUNT(*) as count'))
    ->groupBy('service_order_id', 'location_id')
    ->having('count', '>', 1)
    ->get();

if ($duplicates->count() > 0) {
    echo "Found {$duplicates->count()} duplicate location assignments:\n\n";
    foreach ($duplicates as $dup) {
        $so = DB::table('service_orders')->find($dup->service_order_id)?->order_no ?? 'UNKNOWN';
        $location = DB::table('locations')->find($dup->location_id)?->code ?? 'UNKNOWN';
        echo "  Service Order: $so (ID: {$dup->service_order_id})\n";
        echo "  Location: $location (ID: {$dup->location_id})\n";
        echo "  Count: {$dup->count} times\n";
        echo "  → This is THE PROBLEM! Should be 1 only\n\n";
    }
} else {
    echo "✓ No duplicate location assignments found\n";
}

echo "\n=== CHECKING EXCEL FILE FOR DUPLICATES ===\n\n";

$excelFile = 'c:\\Users\\tandr\\Downloads\\service-order-locations-import-template (3).xlsx';

if (file_exists($excelFile)) {
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($excelFile);
        $sheet = $spreadsheet->getActiveSheet();
        
        $rows = [];
        $duplicateRows = [];
        
        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $data = [];
            
            // Get first 6 columns
            $colIndex = 0;
            foreach ($cellIterator as $cell) {
                if ($colIndex >= 6) break;
                $data[] = $cell->getValue();
                $colIndex++;
            }
            
            if (empty(array_filter($data))) {
                break; // Empty row
            }
            
            $key = trim($data[0] ?? '') . '|' . trim($data[1] ?? ''); // SO|Location
            
            if (isset($rows[$key])) {
                $duplicateRows[] = [
                    'key' => $key,
                    'firstRow' => $rows[$key],
                    'duplicateRow' => $row->getRowIndex(),
                ];
            } else {
                $rows[$key] = $row->getRowIndex();
            }
        }
        
        if (count($duplicateRows) > 0) {
            echo "Found {count($duplicateRows)} DUPLICATE rows in Excel:\n\n";
            foreach ($duplicateRows as $dup) {
                echo "  Key (SO|Location): {$dup['key']}\n";
                echo "  First appears in row: {$dup['firstRow']}\n";
                echo "  Duplicate in row: {$dup['duplicateRow']}\n\n";
            }
        } else {
            echo "✓ No duplicate entries in Excel file\n";
        }
        
    } catch (\Throwable $e) {
        echo "Error reading Excel: " . $e->getMessage() . "\n";
    }
} else {
    echo "Excel file not found at: $excelFile\n";
}
