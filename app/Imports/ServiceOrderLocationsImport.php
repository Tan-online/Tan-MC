<?php

namespace App\Imports;

use App\Models\Location;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ServiceOrderLocationsImport extends AbstractMasterDataImport
{
    private array $serviceOrdersByNumber;
    private array $locationsByCode;
    private array $operationsByEmployeeCode;

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

        $this->operationsByEmployeeCode = User::query()
            ->where('status', 'Active')
            ->pluck('id', 'employee_code')
            ->mapWithKeys(fn ($id, $employeeCode) => [$this->normalizeKey((string) $employeeCode) => $id])
            ->all();
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
        foreach ($rows as $index => $row) {
            $rowArray = $this->prepareRow($row->toArray());

            if ($this->rowIsEmpty($rowArray)) {
                continue;
            }

            $rowNumber = $this->headingRow() + $this->getChunkOffset() + $index + 1;
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

                $serviceOrder->locations()->syncWithoutDetaching([
                    $payload['location_id'] => [
                        'start_date' => $payload['start_date'],
                        'end_date' => $payload['end_date'],
                        'operation_executive_id' => $payload['operation_executive_id'],
                        'muster_due_days' => $payload['muster_due_days'],
                    ],
                ]);

                $serviceOrder->locations()->updateExistingPivot($payload['location_id'], [
                    'start_date' => $payload['start_date'],
                    'end_date' => $payload['end_date'],
                    'operation_executive_id' => $payload['operation_executive_id'],
                    'muster_due_days' => $payload['muster_due_days'],
                ]);

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

    protected function prepareRow(array $row): array
    {
        if (! array_key_exists('location_code', $row) && array_key_exists('location_codes', $row)) {
            $row['location_code'] = $row['location_codes'];
        }

        $row = parent::prepareRow($row);
        $row['sales_order_no'] = $this->normalize($row['sales_order_no'] ?? null);
        $row['location_code'] = $this->normalize($row['location_code'] ?? null);
        $row['operation_executive_employee_code'] = $this->normalize($row['operation_executive_employee_code'] ?? null);
        $row['start_date'] = $this->excelDate($row['start_date'] ?? null);
        $row['end_date'] = $this->excelDate($row['end_date'] ?? null);

        return $row;
    }

    protected function preparePayload(array $row): array
    {
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
                throw new RuntimeException('Operation executive employee code not found.');
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