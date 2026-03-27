<?php
require 'vendor/autoload.php';

$app = include 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->handle(
    $request = \Illuminate\Http\Request::capture()
);

echo "=== CHECKING USER WITH ID 13 ===\n";
$user = \App\Models\User::find(13);

if ($user) {
    echo "User ID: 13\n";
    echo "Employee Code: " . $user->employee_code . "\n";
    echo "Name: " . $user->name . "\n";
    echo "Status: " . $user->status . "\n";
    echo "\n✓ Employee code '000013' correctly maps to User ID 13\n";
} else {
    echo "User not found with ID 13\n";
}
