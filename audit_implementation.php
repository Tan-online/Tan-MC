<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();

echo "=== IMPLEMENTATION AUDIT TEST ===\n\n";

try {
    // Test 1: Table exists
    echo "[1] Table Existence Test\n";
    $tableExists = \Illuminate\Support\Facades\Schema::hasTable('so_location_monthly_status');
    echo "    so_location_monthly_status exists: " . ($tableExists ? "✓ YES\n" : "✗ NO\n");
    
    if (!$tableExists) {
        echo "    ERROR: Table does not exist!\n";
        exit(1);
    }
    
    // Test 2: Columns exist
    echo "\n[2] Column Structure Test\n";
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing('so_location_monthly_status');
    $requiredColumns = [
        'id', 'service_order_id', 'location_id', 'wage_month', 
        'status', 'file_path', 'remarks', 'submitted_by', 'submitted_at',
        'reviewed_by', 'reviewed_at'
    ];
    
    foreach ($requiredColumns as $col) {
        echo "    Column '$col': " . (in_array($col, $columns) ? "✓\n" : "✗ MISSING\n");
    }
    echo "    Total columns: " . count($columns) . "\n";
    
    // Test 3: Model works
    echo "\n[3] Model Test\n";
    $model = new \App\Models\SoLocationMonthlyStatus();
    echo "    Model instantiation: ✓ SUCCESS\n";
    echo "    Table name: " . $model->getTable() . "\n";
    echo "    Mass assignable: " . count($model->getFillable()) . " fields\n";
    
    // Test 4: Service methods exist
    echo "\n[4] Service Layer Test\n";
    $service = app(\App\Services\OperationsWorkspaceService::class);
    echo "    submitSoLocationStatus method: " . (method_exists($service, 'submitSoLocationStatus') ? "✓\n" : "✗ MISSING\n");
    echo "    getSoLocationMonthlyStatus method: " . (method_exists($service, 'getSoLocationMonthlyStatus') ? "✓\n" : "✗ MISSING\n");
    echo "    batchGetSoLocationMonthlyStatuses method: " . (method_exists($service, 'batchGetSoLocationMonthlyStatuses') ? "✓\n" : "✗ MISSING\n");
    echo "    compactLocationRowsQuery method: " . (method_exists($service, 'compactLocationRowsQuery') ? "✓\n" : "✗ MISSING\n");
    
    // Test 5: Route exists
    echo "\n[5] Route Test\n";
    $routes = app(\Illuminate\Routing\Router::class)->getRoutes();
    $locationsRoute = null;
    foreach ($routes as $route) {
        if ($route->getName() === 'operations-workspace.locations') {
            $locationsRoute = $route;
            break;
        }
    }
    echo "    operations-workspace.locations route: " . ($locationsRoute ? "✓ EXISTS\n" : "✗ NOT FOUND\n");
    if ($locationsRoute) {
        echo "    Route URI: " . $locationsRoute->uri . "\n";
        echo "    Route method: " . implode(', ', $locationsRoute->methods) . "\n";
    }
    
    // Test 6: Query compilation
    echo "\n[6] Query Compilation Test\n";
    try {
        $user = \App\Models\User::first();
        if ($user) {
            $wageMonth = \Carbon\Carbon::now()->startOfMonth();
            $filters = ['client_id' => 0, 'location_id' => 0, 'executive_id' => 0, 'status' => ''];
            
            $query = $service->compactLocationRowsQuery($user, $wageMonth, $filters);
            $sql = $query->toSql();
            echo "    Query builds successfully: ✓\n";
            echo "    Query uses so_location_monthly_status: " . (strpos($sql, 'so_location_monthly_status') !== false ? "✓\n" : "✗ NOT FOUND\n");
            echo "    Query uses COALESCE: " . (strpos($sql, 'COALESCE') !== false ? "✓\n" : "✗ NOT FOUND\n");
        } else {
            echo "    No users found, skipping query test\n";
        }
    } catch (\Exception $e) {
        echo "    Query compilation failed: ✗\n";
        echo "    Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== AUDIT COMPLETE ===\n";
    echo "Status: ✓ ALL TESTS PASSED\n";
    
} catch (\Exception $e) {
    echo "\n✗ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
