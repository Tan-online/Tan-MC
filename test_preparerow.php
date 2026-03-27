<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test the string normalization and padding logic directly
echo "=== Testing Employee Code Normalization ===\n";

// Test case 1: Integer from Excel
$testValue = 13;
$code = (string)$testValue;
$digits = preg_replace('/\D/', '', $code);
$normalized = str_pad($digits, 6, '0', STR_PAD_LEFT);
echo "Input (int): $testValue\n";
echo "Output: $normalized\n\n";

// Test case 2: String with leading zeros
$testValue = "000013";
$code = (string)$testValue;
$digits = preg_replace('/\D/', '', $code);
$normalized = str_pad($digits, 6, '0', STR_PAD_LEFT);
echo "Input (string): $testValue\n";
echo "Output: $normalized\n\n";

// Test case 3: Float that represents integer
$testValue = 123.0;
$code = (string)$testValue;
$digits = preg_replace('/\D/', '', $code);
$normalized = str_pad($digits, 6, '0', STR_PAD_LEFT);
echo "Input (float): $testValue\n";
echo "Output: $normalized\n";
