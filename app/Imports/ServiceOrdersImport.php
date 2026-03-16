<?php

namespace App\Imports;

use App\Models\Location;
use App\Models\Contract;
use App\Models\ServiceOrder;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class ServiceOrdersImport extends AbstractMasterDataImport
{
    private array $contractsByNumber;
    private array $teamsByCode;
    private array $locationsByCode;
    private array $operationsByEmployeeCode;

    public function __construct()
    {
        $this->contractsByNumber = Contract::query()
            ->with(['client:id,code', 'locations:id'])
            ->get(['id', 'client_id', 'location_id', 'contract_no'])
            ->mapWithKeys(function (Contract $contract) {
                return [
                    $this->normalizeKey($contract->contract_no) => [
                        'id' => $contract->id,
                        'client_code' => $this->normalizeKey($contract->client?->code),
                        'location_ids' => $contract->locations->pluck('id')->unique()->values()->all(),
                    ],
                ];
            })
            ->all();

        $this->teamsByCode = Team::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
            ->all();

        $this->locationsByCode = Location::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
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
            'client_code' => ['required', 'string', 'max:20'],
            'contract_no' => ['required', 'string', 'max:50'],
            'sales_order_no' => ['required', 'string', 'max:50', Rule::unique('service_orders', 'order_no')],
            'team_code' => ['nullable', 'string', 'max:20'],
            'operation_executive_employee_code' => ['nullable', 'string', 'max:50'],
            'location_codes' => ['nullable', 'string', 'max:500'],
            'location_mapping' => ['nullable', 'string', 'max:4000'],
            'requested_date' => ['required', 'date'],
            'muster_start_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'muster_due_days' => ['nullable', 'integer', 'min:0', 'max:15'],
            'status' => ['required', Rule::in(['Open', 'Assigned', 'In Progress', 'Completed', 'Cancelled'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
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
                $prepared = $this->preparePayload($validated);
                $locationSyncRows = $prepared['location_sync_rows'];
                unset($prepared['location_sync_rows']);

                $serviceOrder = ServiceOrder::query()->create($prepared);

                if ($locationSyncRows !== []) {
                    $serviceOrder->locations()->sync($locationSyncRows);
                }

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
        $row = parent::prepareRow($row);
        $row['requested_date'] = $this->excelDate($row['requested_date'] ?? null);

        return $row;
    }

    protected function preparePayload(array $row): array
    {
        $contractNo = $this->normalizeKey((string) $row['contract_no']);
        $clientCode = $this->normalizeKey((string) $row['client_code']);

        if (! isset($this->contractsByNumber[$contractNo])) {
            throw new RuntimeException('Contract number not found.');
        }

        if ($this->contractsByNumber[$contractNo]['client_code'] !== $clientCode) {
            throw new RuntimeException('Client code does not match the selected contract.');
        }

        $teamId = null;

        if (! empty($row['team_code'])) {
            $teamCode = $this->normalizeKey((string) $row['team_code']);

            if (! isset($this->teamsByCode[$teamCode])) {
                throw new RuntimeException('Team code not found.');
            }

            $teamId = $this->teamsByCode[$teamCode];
        }

        $operationExecutiveId = null;

        if (! empty($row['operation_executive_employee_code'])) {
            $employeeCode = $this->normalizeKey((string) $row['operation_executive_employee_code']);

            if (! isset($this->operationsByEmployeeCode[$employeeCode])) {
                throw new RuntimeException('Operation executive employee code not found.');
            }

            $operationExecutiveId = $this->operationsByEmployeeCode[$employeeCode];
        }

        $musterStartDay = (int) ($row['muster_start_day'] ?? 1);
        [$periodStartDate, $periodEndDate] = $this->resolvePeriodDates($row['requested_date'], $musterStartDay);

        $locationRows = $this->resolveLocationRows(
            $row,
            $this->contractsByNumber[$contractNo]['location_ids'],
            $periodStartDate,
            $periodEndDate,
        );

        $primaryLocationId = array_key_first($locationRows);

        if (! $primaryLocationId) {
            throw new RuntimeException('At least one mapped location is required for the sales order.');
        }

        return $this->timestamps([
            'contract_id' => $this->contractsByNumber[$contractNo]['id'],
            'location_id' => $primaryLocationId,
            'team_id' => $teamId,
            'operation_executive_id' => $operationExecutiveId,
            'order_no' => $this->normalizeKey((string) $row['sales_order_no']),
            'requested_date' => $row['requested_date'],
            'scheduled_date' => null,
            'period_start_date' => $periodStartDate,
            'period_end_date' => $periodEndDate,
            'muster_start_day' => $musterStartDay,
            'muster_cycle_type' => $musterStartDay === 21 ? '21-20' : '1-last',
            'muster_due_days' => (int) ($row['muster_due_days'] ?? 0),
            'auto_generate_muster' => true,
            'status' => $row['status'],
            'priority' => 'Medium',
            'amount' => null,
            'remarks' => $row['remarks'] ?? null,
            'location_sync_rows' => $locationRows,
        ]);
    }

    private function resolvePeriodDates(string $requestedDate, int $musterStartDay): array
    {
        $date = Carbon::parse($requestedDate)->startOfDay();
        $startDay = max(1, min(31, $musterStartDay));
        $anchorMonth = $date->day >= $startDay ? $date->copy() : $date->copy()->subMonth();
        $periodStart = $anchorMonth->copy()->day(min($startDay, $anchorMonth->daysInMonth))->startOfDay();
        $periodEnd = $periodStart->copy()->addMonth()->subDay()->endOfDay();

        return [$periodStart->toDateString(), $periodEnd->toDateString()];
    }

    private function resolveLocationRows(array $row, array $contractLocationIds, string $fallbackStart, string $fallbackEnd): array
    {
        $rows = [];

        $locationMapping = trim((string) ($row['location_mapping'] ?? ''));

        if ($locationMapping !== '') {
            $entries = array_filter(array_map('trim', explode(';', $locationMapping)));

            foreach ($entries as $entry) {
                $parts = array_map('trim', explode('|', $entry));
                $locationCode = $this->normalizeKey((string) ($parts[0] ?? ''));

                if (! isset($this->locationsByCode[$locationCode])) {
                    throw new RuntimeException("Location code {$locationCode} not found in location_mapping.");
                }

                $locationId = (int) $this->locationsByCode[$locationCode];

                if (! in_array($locationId, $contractLocationIds, true)) {
                    throw new RuntimeException("Location code {$locationCode} is not mapped to the selected contract.");
                }

                $rows[$locationId] = [
                    'start_date' => $this->excelDate($parts[1] ?? null) ?? $fallbackStart,
                    'end_date' => $this->excelDate($parts[2] ?? null) ?? $fallbackEnd,
                ];
            }
        }

        $locationCodes = trim((string) ($row['location_codes'] ?? ''));

        if ($locationCodes !== '') {
            $codes = array_filter(array_map(fn ($code) => $this->normalizeKey($code), explode('|', $locationCodes)));

            foreach ($codes as $code) {
                if (! isset($this->locationsByCode[$code])) {
                    throw new RuntimeException("Location code {$code} not found in location_codes.");
                }

                $locationId = (int) $this->locationsByCode[$code];

                if (! in_array($locationId, $contractLocationIds, true)) {
                    throw new RuntimeException("Location code {$code} is not mapped to the selected contract.");
                }

                if (! isset($rows[$locationId])) {
                    $rows[$locationId] = [
                        'start_date' => $fallbackStart,
                        'end_date' => $fallbackEnd,
                    ];
                }
            }
        }

        if ($rows === [] && $contractLocationIds !== []) {
            $rows[(int) $contractLocationIds[0]] = [
                'start_date' => $fallbackStart,
                'end_date' => $fallbackEnd,
            ];
        }

        return $rows;
    }

    protected function uniqueKey(array $row): ?string
    {
        return 'service-orders:' . $this->normalizeKey((string) $row['sales_order_no']);
    }
}
