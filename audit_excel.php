<?php

require 'vendor/autoload.php';

$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$spreadsheet = $reader->load('C:/Users/tandr/Downloads/service-order-locations-import-template (3).xlsx');
$sheet = $spreadsheet->getActiveSheet();

echo "=== EXCEL FILE AUDIT ===\n\n";
echo "File: service-order-locations-import-template (3).xlsx\n";
echo "Sheet Name: " . $sheet->getTitle() . "\n\n";

echo "ROW 1 (Headers):\n";
for ($col = 'A'; $col <= 'J'; $col++) {
    $cell = $sheet->getCell($col . '1');
    echo $col . "1: " . $cell->getValue() . " (Type: " . $cell->getDataType() . ")\n";
}

echo "\n\nROW 2 (First Data Row):\n";
for ($col = 'A'; $col <= 'J'; $col++) {
    $cell = $sheet->getCell($col . '2');
    $value = $cell->getValue();
    $type = $cell->getDataType();
    echo $col . "2: " . var_export($value, true) . " (Type: " . $type . ")\n";
}

echo "\n\nROW 3 (Second Data Row):\n";
for ($col = 'A'; $col <= 'J'; $col++) {
    $cell = $sheet->getCell($col . '3');
    $value = $cell->getValue();
    $type = $cell->getDataType();
    echo $col . "3: " . var_export($value, true) . " (Type: " . $type . ")\n";
}
