<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$kernel->handle(\Illuminate\Http\Request::capture());

$latest = \App\Models\ImportBatch::where('type', 'service-order-locations')->latest()->first();

echo 'Latest Import Batch:' . PHP_EOL;
echo 'ID: ' . $latest->id . PHP_EOL;
echo 'File: ' . $latest->original_file_name . PHP_EOL;
echo 'Inserted: ' . $latest->inserted_rows . PHP_EOL;
echo 'Errors:' . $latest->failed_rows . PHP_EOL;
echo 'Status: ' . $latest->status . PHP_EOL;
echo 'Completed: ' . $latest->completed_at . PHP_EOL;

if ($latest->failed_rows > 0) {
    echo PHP_EOL . 'Error Details:' . PHP_EOL;
    foreach ($latest->failure_report as $f) {
        echo 'Row ' . $f['row'] . ': ' . implode(', ', $f['errors']) . PHP_EOL;
    }
} else {
    echo PHP_EOL . '✓ Import completed successfully with NO errors!' . PHP_EOL;
}
