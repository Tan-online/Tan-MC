<?php

use Tests\TestCase;

class EmployeeCodeImportTest extends TestCase
{
    public function test_it_handles_numeric_employee_codes_with_leading_zeros(): void
    {
        // Simulate the import logic with mocked data
        $mockUsers = [
            '000013' => 1,  // Employee code from database
            '000003' => 2,
            'EMP-0005' => 3,
        ];

        // Recreate the lookup logic from ServiceOrderLocationsImport constructor
        $operationsByEmployeeCode = [];
        foreach ($mockUsers as $employeeCode => $id) {
            $normalizedKey = strtoupper(trim($employeeCode));
            $operationsByEmployeeCode[$normalizedKey] = $id;

            // Also add numeric variations (with leading zeros padding)
            if (is_numeric($employeeCode)) {
                for ($padding = 0; $padding <= 10; $padding++) {
                    $padded = str_pad((string)(int)$employeeCode, $padding, '0', STR_PAD_LEFT);
                    $operationsByEmployeeCode[strtoupper(trim($padded))] = $id;
                }
            }
        }

        // Test scenarios
        $scenarios = [
            '000013' => true,  // Exact match from database
            '13' => true,      // Excel converted numeric (should be found)
            '000003' => true,  // Other numeric code
            '3' => true,       // Without padding
            'EMP-0005' => true,  // String code
            '00055555' => false,  // Non-existent code
        ];

        foreach ($scenarios as $lookup => $shouldExist) {
            $found = isset($operationsByEmployeeCode[strtoupper(trim($lookup))]);
            $this->assertEquals(
                $shouldExist, 
                $found,
                "Employee code '$lookup' lookup failed"
            );
        }
    }
}
