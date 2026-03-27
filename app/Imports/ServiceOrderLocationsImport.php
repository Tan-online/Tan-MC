<?php

namespace App\Imports;

use App\Models\Location;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use RuntimeException;

class ServiceOrderLocationsImport extends AbstractMasterDataImport implements ToCollection
{
    private array $serviceOrdersByNumber;
    private array $locationsByCode;
    private array $operationsByEmployeeCode;

    /**
     * Override bindValue to force column E (operation_executive_employee_code) to be read as STRING.
     * This prevents PhpSpreadsheet from auto-converting "000013" to numeric 13.
     *
     * @return bool
     */
    public function bindValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell, mixed $value)
    {
        // Column E: operation_executive_employee_code - MUST be read as TEXT/STRING
        if ($cell->getColumn() === 'E') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        
        return parent::bindValue($cell, $value);
    }

    public function __construct()
    {
        $this->serviceOrdersByNumber = ServiceOrder::query()
            ->with(['contract:id,client_id'])
            ->get(['id', 'contract_id', 'state_id', 'order_no', 'requested_date'])
            ->mapWithKeys(fn (ServiceOrder $serviceOrder) => [
                $this->normalizeKey((string) $serviceOrder->order_no) => $serviceOrder,
            ])
            ->all();

        $this->locationsByCode = Location::query()
            ->get(['id', 'client_id', 'state_id', 'code'])
            ->mapWithKeys(fn (Location $location) => [
                $this->normalizeKey((string) $location->code) => [
                    'id' => $location->id,
                    'client_id' => $location->client_id,
                    'state_id' => $location->state_id,
                ],
            ])
            ->all();

        $users = User::query()
            ->where('status', 'Active')
            ->get(['id', 'employee_code'])
            ->all();

        $this->operationsByEmployeeCode = [];
        
        foreach ($users as $user) {
            $employeeCode = (string) $user->employee_code;
            $normalizedKey = $this->normalizeKey($employeeCode);
            
            // Primary entry - with original format
            $this->operationsByEmployeeCode[$normalizedKey] = $user->id;

            // Extract numeric parts for fuzzy matching
            preg_match_all('/(\d+)/', $employeeCode, $matches);
            
            if (!empty($matches[1])) {
                // Use the longest numeric sequence (usually the employee number)
                $numericSequences = array_unique(array_filter(array_map('trim', $matches[1])));
                
                foreach ($numericSequences as $numericPart) {
                    $intValue = (int) $numericPart;
                    
                    // Add the numeric value padded to 6 digits (standard employee code format)
                    $padded6 = str_pad((string) $intValue, 6, '0', STR_PAD_LEFT);
                    $this->operationsByEmployeeCode[$this->normalizeKey($padded6)] = $user->id;
                    
                    // Also add un-padded numeric value
                    $this->operationsByEmployeeCode[$this->normalizeKey((string) $intValue)] = $user->id;
                    
                    // Add other common padding lengths
                    for ($padding = 3; $padding <= 10; $padding++) {
                        $padded = str_pad((string) $intValue, $padding, '0', STR_PAD_LEFT);
                        $this->operationsByEmployeeCode[$this->normalizeKey($padded)] = $user->id;
                    }
                }
            }
        }
    }

    protected function modelClass(): string
    {
        return ServiceOrder::class;
    }

    public function rules(): array
    {
        return [
            'sales_order_no' => ['required', 'string', 'max:50'],
            'location_code' => ['required', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'operation_executive_employee_code' => ['nullable', 'string', 'max:50'],
            'muster_due_days' => ['nullable', 'integer', 'min:0', 'max:15'],
        ];
    }

    public function collection(Collection $rows): void
    {
        // WithHeadingRow automatically skips the header and provides keyed arrays
        // Each $row is already: ['sales_order_no' => '...', 'location_code' => '...', 'operation_executive_employee_code' => '...', etc]
        
        foreach ($rows as $index => $row) {
            // Convert Collection item to array and build our keyed row array
            $rawRow = $row instanceof \Illuminate\Support\Collection ? $row->all() : (array)$row;
            
            $rowArray = [
                'sales_order_no' => isset($rawRow['sales_order_no']) ? trim((string)$rawRow['sales_order_no']) : null,
                'location_code' => isset($rawRow['location_code']) ? trim((string)$rawRow['location_code']) : null,
                'start_date' => isset($rawRow['start_date']) ? trim((string)$rawRow['start_date']) : null,
                'end_date' => isset($rawRow['end_date']) ? trim((string)$rawRow['end_date']) : null,
                // CRITICAL: Apply str_pad to preserve leading zeros in employee code
                // This handles the case where "000013" gets converted to numeric 13 by PhpSpreadsheet
                'operation_executive_employee_code' => $this->normalizeEmployeeCode(isset($rawRow['operation_executive_employee_code']) ? $rawRow['operation_executive_employee_code'] : null),
                'muster_due_days' => isset($rawRow['muster_due_days']) ? ((int)$rawRow['muster_due_days']) : null,
            ];
            
            $rowArray = $this->prepareRow($rowArray);

            if ($this->rowIsEmpty($rowArray)) {
                continue;
            }

            $rowNumber = $this->getChunkOffset() + $index + 1;
            $validator = Validator::make($rowArray, $this->rules());

            if ($validator->fails()) {
                $this->recordValidatorFailures($rowNumber, $validator->errors()->toArray(), $rowArray);
                continue;
            }

            $validated = $validator->validated();
            $uniqueKey = $this->uniqueKey($validated);

            if ($uniqueKey !== null && isset($this->seenUniqueValues[$uniqueKey])) {
                $this->addFailure($rowNumber, 'row', ['Duplicate value found in the import file.'], $rowArray);
                continue;
            }

            try {
                $payload = $this->preparePayload($validated);
                /** @var ServiceOrder $serviceOrder */
                $serviceOrder = $payload['service_order'];
                $assignment = ServiceOrderLocation::query()
                    ->where('service_order_id', $serviceOrder->id)
                    ->where('location_id', $payload['location_id'])
                    ->first();

                $attributes = [
                    'start_date' => $payload['start_date'],
                    'end_date' => $payload['end_date'],
                    'operation_executive_id' => $payload['operation_executive_id'],
                    'muster_due_days' => $payload['muster_due_days'],
                    'wage_month' => $payload['wage_month'],
                ];

                if ($assignment) {
                    if ($assignment->wage_month?->toDateString() !== $payload['wage_month']) {
                        $attributes['dispatched_at'] = null;
                        $attributes['dispatched_by_user_id'] = null;
                    }

                    $serviceOrder->locations()->updateExistingPivot($payload['location_id'], $attributes);
                } else {
                    $serviceOrder->locations()->attach($payload['location_id'], array_merge($attributes, [
                        'dispatched_at' => null,
                        'dispatched_by_user_id' => null,
                    ]));
                }

                $serviceOrder->syncSummaryFromLocationAssignments();

                if ($uniqueKey !== null) {
                    $this->seenUniqueValues[$uniqueKey] = true;
                }

                $this->insertedCount++;
            } catch (\Throwable $exception) {
                $this->addFailure($rowNumber, 'row', [$exception->getMessage()], $rowArray);
            }
        }
    }

    /**
     * Normalize employee code to preserve leading zeros.
     * Ensures codes like "000013" stay as "000013" and "13" become "000013".
     * 
     * This method:
     * 1. Converts any type to string
     * 2. Trims whitespace
     * 3. Extracts only digit characters
     * 4. Pads to 6 digits with leading zeros
     * 
     * Examples:
     * - "000013" → "000013"
     * - 13 → "000013"
     * - "13" → "000013"
     * - null → null
     */
    public function normalizeEmployeeCode($value): ?string
    {
        // Handle null/empty values
        if ($value === null || $value === '') {
            return null;
        }
        
        // Convert to string and trim
        $string = trim((string)$value);
        if (empty($string)) {
            return null;
        }
        
        // Extract ONLY digits (removes any non-numeric characters)
        $digitsOnly = preg_replace('/[^\d]/', '', $string);
        
        // If no digits found, return original trimmed string
        if (empty($digitsOnly)) {
            return $string;
        }
        
        // Pad to 6 digits with leading zeros
        $normalized = str_pad($digitsOnly, 6, '0', STR_PAD_LEFT);
        
        // Log for debugging (DEBUG level - won't clutter logs in production)
        if ($string !== $normalized) {
            Log::debug('ServiceOrderLocationsImport::normalizeEmployeeCode - normalized', [
                'original' => $string,
                'normalized' => $normalized,
            ]);
        }
        
        return $normalized;
    }

    protected function prepareRow(array $row): array
    {
        $row = parent::prepareRow($row);
        $row['sales_order_no'] = $this->normalize($row['sales_order_no'] ?? null);
        $row['location_code'] = $this->normalize($row['location_code'] ?? null);
        // Employee code is already normalized in collection() via normalizeEmployeeCode()
        // Just ensure it's normalized again if needed
        $row['operation_executive_employee_code'] = $this->normalize($row['operation_executive_employee_code'] ?? null);
        
        $row['start_date'] = $this->excelDate($row['start_date'] ?? null);
        $row['end_date'] = $this->excelDate($row['end_date'] ?? null);

        return $row;
    }

    protected function preparePayload(array $row): array
    {
        // DEBUG: Log incoming values
        if (isset($row['operation_executive_employee_code']) && $row['operation_executive_employee_code'] !== null) {
            Log::debug('ServiceOrderLocationsImport::preparePayload', [
                'operation_executive_employee_code' => $row['operation_executive_employee_code'],
                'type' => gettype($row['operation_executive_employee_code']),
            ]);
        }

        $orderNo = $this->normalizeKey((string) $row['sales_order_no']);
        $locationCode = $this->normalizeKey((string) $row['location_code']);

        if (! isset($this->serviceOrdersByNumber[$orderNo])) {
            throw new RuntimeException('Sales order number not found.');
        }

        if (! isset($this->locationsByCode[$locationCode])) {
            throw new RuntimeException('Location code not found.');
        }

        /** @var ServiceOrder $serviceOrder */
        $serviceOrder = $this->serviceOrdersByNumber[$orderNo];
        $location = $this->locationsByCode[$locationCode];

        if ((int) $serviceOrder->contract?->client_id !== (int) $location['client_id']) {
            throw new RuntimeException('Location code does not belong to the sales order client.');
        }

        if ((int) $serviceOrder->state_id !== (int) $location['state_id']) {
            throw new RuntimeException('Location code does not belong to the sales order state.');
        }

        $operationExecutiveId = null;

        if (! empty($row['operation_executive_employee_code'])) {
            $employeeCode = $this->normalizeKey((string) $row['operation_executive_employee_code']);

            if (! isset($this->operationsByEmployeeCode[$employeeCode])) {
                // Try to find similar matches for better error reporting
                $availableCodes = implode(', ', array_slice(array_keys($this->operationsByEmployeeCode), 0, 5));
                throw new RuntimeException(
                    "Operation executive not found for code: {$row['operation_executive_employee_code']} "
                    . "(searched: {$employeeCode}). Available codes: {$availableCodes}"
                );
            }

            $operationExecutiveId = (int) $this->operationsByEmployeeCode[$employeeCode];
        }

        return [
            'service_order' => $serviceOrder,
            'location_id' => (int) $location['id'],
            'start_date' => $row['start_date'] ?: optional($serviceOrder->requested_date)->format('Y-m-d'),
            'end_date' => $row['end_date'] ?: null,
            'operation_executive_id' => $operationExecutiveId,
            'muster_due_days' => max(0, (int) ($row['muster_due_days'] ?? 0)),
            'wage_month' => $serviceOrder->wageMonth(),
        ];
    }

    protected function uniqueKey(array $row): ?string
    {
        return 'service-order-locations:'
            . $this->normalizeKey((string) ($row['sales_order_no'] ?? ''))
            . '|'
            . $this->normalizeKey((string) ($row['location_code'] ?? ''));
    }
}
