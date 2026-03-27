<?php

require 'vendor/autoload.php';

// Bootstrap Laravel
$app = require 'bootstrap/app.php';

// Get first 5 users
$users = \App\Models\User::select('id', 'name', 'employee_code')->limit(5)->get();

echo "=== Users in Database ===\n";
foreach ($users as $u) {
    echo "ID: " . $u->id . ", Name: " . $u->name . ", Employee Code: '" . $u->employee_code . "' (Length: " . strlen($u->employee_code ?? '') . ", Type: " . gettype($u->employee_code) . ")\n";
}

// Check specifically for code 13 or 000013
echo "\n=== Search for '13' or '000013' ===\n";
$code13 = \App\Models\User::where('employee_code', '13')->orWhere('employee_code', '000013')->get(['id', 'name', 'employee_code']);

foreach ($code13 as $u) {
    echo "Found: '" . $u->employee_code . "' (Length: " . strlen($u->employee_code) . ")\n";
}

if ($code13->isEmpty()) {
    echo "No users found with employee_code '13' or '000013'\n";
}

// Check what's the actual data
echo "\n=== All unique employee codes ===\n";
$allCodes = \App\Models\User::select('employee_code')->distinct()->limit(10)->get();
foreach ($allCodes as $u) {
    echo "'" . $u->employee_code . "'\n";
}
