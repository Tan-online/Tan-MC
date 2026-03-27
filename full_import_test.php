<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    \Illuminate\Http\Request::capture()
);

echo "=== FULL IMPORT TEST - END TO END ===\n\n";

$file = 'c:\\Users\\tandr\\Downloads\\service-order-locations-import-template (3).xlsx';

echo "[1/5] Verifying file exists and structure\n";
if (!file_exists($file)) {
    echo "ERROR: File not found\n";
    exit(1);
}
echo "✓ File found\n";

echo "\n[2/5] Reading raw Excel data\n";
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setReadDataOnly(false);
$spreadsheet = $reader->load($file);
$worksheet = $spreadsheet->getActiveSheet();

// Read first 5 rows
for ($row = 1; $row <= 5; $row++) {
    $values = [];
    for ($col = 'A'; $col <= 'F'; $col++) {
        $cell = $worksheet->getCell($col . $row);
        $values[] = $cell->getValue();
    }
    echo "Row $row: " . json_encode($values) . "\n";
}

echo "\n[3/5] Instantiating import class and checking bindings\n";
$import = new \App\Imports\ServiceOrderLocationsImport();
echo "✓ Import class instantiated\n";

// Check what users exist with employee code including '13'
echo "\n[4/5] Checking available users with employee codes\n";
$users = \App\Models\User::query()
    ->where('status', 'Active')
    ->whereRaw("CAST(employee_code AS CHAR) LIKE '%13%'")
    ->get(['id', 'employee_code', 'name'])
    ->take(5);

echo "Users with employee code containing '13':\n";
foreach ($users as $user) {
    echo "  ID: {$user->id}, Code: {$user->employee_code}, Name: {$user->name}\n";
}

echo "\n[5/5] Running actual import\n";
try {
    \Maatwebsite\Excel\Facades\Excel::import($import, $file);
    
    echo "Import completed:\n";
    echo "  Inserted: " . $import->insertedCount() . "\n";
    echo "  Failures: " . count($import->failures()) . "\n";
    
    if (count($import->failures()) > 0) {
        echo "\nFAILURES:\n";
        foreach ($import->failures() as $failure) {
            echo "  Row " . (isset($failure['row']) ? $failure['row'] : '?') . ": ";
            if (isset($failure['errors'])) {
                echo implode(', ', array_merge(...array_values($failure['errors'])));
            }
            echo "\n";
        }
    } else {
        echo "\n✓ NO FAILURES - IMPORT SUCCESSFUL\n";
    }
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n✓ Test complete\n";
