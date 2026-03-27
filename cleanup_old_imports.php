<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
$kernel->handle(\Illuminate\Http\Request::capture());

use App\Models\ImportBatch;

echo "=== CLEANING UP OLD FAILED IMPORTS ===\n\n";

// Delete old failed imports with employee code errors
$oldFailed = ImportBatch::where('type', 'service-order-locations')
    ->where('failed_rows', '>', 0)
    ->where('created_at', '<', now()->subDays(1))
    ->get();

echo "Found " . $oldFailed->count() . " old failed imports to delete\n\n";

foreach ($oldFailed as $batch) {
    echo "Deleting: ID {$batch->id} ({$batch->original_file_name}) - {$batch->failed_rows} errors\n";
    
    // Delete associated file if exists
    if ($batch->disk && $batch->stored_path) {
        try {
            \Illuminate\Support\Facades\Storage::disk($batch->disk)->delete($batch->stored_path);
        } catch (\Throwable $e) {
            // File may not exist
        }
    }
    
    $batch->delete();
}

echo "\n✓ Cleanup complete!\n\n";

echo "=== REMAINING SERVICE ORDER LOCATIONS IMPORTS ===\n\n";

$remaining = ImportBatch::where('type', 'service-order-locations')
    ->latest()
    ->get();

echo "Total: " . $remaining->count() . " batches\n\n";

foreach ($remaining as $batch) {
    $status = $batch->failed_rows === 0 ? '✓' : '✗';
    echo "$status [{$batch->id}] {$batch->original_file_name}\n";
    echo "   Inserted: {$batch->inserted_rows} | Errors: {$batch->failed_rows} | {$batch->completed_at}\n";
}
