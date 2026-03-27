<?php

namespace App\Imports;

use App\Models\Contract;
use App\Models\ServiceOrder;
use App\Models\State;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use RuntimeException;

class ServiceOrdersImport extends AbstractMasterDataImport implements WithCustomValueBinder, WithMapping
{
    /**
     * Override bindValue to force code columns to be treated as STRING.
     * This prevents PhpSpreadsheet from auto-converting "000013" to numeric 13.
     * Column mapping: E = operation_executive_employee_code
     *
     * @return bool
     */
    public function bindValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell, mixed $value)
    {
        // Force columns B, C, D, E to be read as STRING type
        if (in_array($cell->getColumn(), ['B', 'C', 'D', 'E'], true)) {
            $cell->setValueExplicit($cell->getValue(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    /**
     * Map each row to normalize and preserve leading zeros in code columns.
     * Apple str_pad to employee_code to ensure leading zeros are preserved.
     * 
     * @return array
     */
    public function map($row): array
    {
        // Preserve client_code with leading zeros  
        if (isset($row['client_code']) && $row['client_code'] !== null) {
            $row['client_code'] = trim((string)$row['client_code']);
        }

        // Preserve contract_no with leading zeros
        if (isset($row['contract_no']) && $row['contract_no'] !== null) {
            $row['contract_no'] = trim((string)$row['contract_no']);
        }

        // Preserve state_code with leading zeros
        if (isset($row['state_code']) && $row['state_code'] !== null) {
            $row['state_code'] = trim((string)$row['state_code']);
        }

        // **CRITICAL FIX**: Apply str_pad to operation_executive_employee_code
        // Even if bindValue() caught it as STRING, ensure it's 6-digit format with leading zeros
        if (isset($row['operation_executive_employee_code']) && $row['operation_executive_employee_code'] !== null) {
            $code = (string)$row['operation_executive_employee_code'];
            $row['operation_executive_employee_code'] = str_pad(trim($code), 6, '0', STR_PAD_LEFT);
            \Illuminate\Support\Facades\Log::debug('ServiceOrdersImport.map() - employee_code normalized', [
                'raw' => $code,
                'normalized' => $row['operation_executive_employee_code'],
            ]);
        }

        return $row;
    }

    private $contractsByNumber = array();

    private $statesByCode = array();

    public function __construct()
    {
        $this->contractsByNumber = Contract::query()
            ->with(array('client:id,code'))
            ->get(array('id', 'client_id', 'location_id', 'contract_no'))
            ->mapWithKeys(function (Contract $contract) {
                $client = $contract->client;
                $clientCode = $client ? $client->code : null;

                return array(
                    $this->normalizeKey($contract->contract_no) => array(
                        'id' => $contract->id,
                        'client_code' => $this->normalizeKey($clientCode),
                    ),
                );
            })
            ->all();

        $this->statesByCode = State::query()
            ->pluck('id', 'code')
            ->mapWithKeys(function ($id, $code) {
                return array($this->normalizeKey((string) $code) => $id);
            })
            ->all();
    }

    protected function modelClass(): string
    {
        return ServiceOrder::class;
    }

    public function rules(): array
    {
        return array(
            'client_code' => array('required', 'string', 'max:20'),
            'contract_no' => array('required', 'string', 'max:50'),
            'sales_order_no' => array('nullable', 'string', 'max:50'),
            'sales_order_name' => array('nullable', 'string', 'max:150'),
            'state_code' => array('required', 'string', 'max:20'),
            'requested_date' => array('required', 'date'),
            'muster_start_day' => array('nullable', 'integer', 'min:1', 'max:31'),
            'status' => array('required', Rule::in(ServiceOrder::allowedStatusValues())),
            'remarks' => array('nullable', 'string', 'max:2000')
        );
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            // CRITICAL: Call map() first to apply WithMapping transformations (including str_pad)
            $mapped = $this->map($row->toArray());
            $rowArray = $this->prepareRow($mapped);

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
                $this->addFailure($rowNumber, 'row', array('Duplicate value found in the import file.'), $rowArray);
                continue;
            }

            try {
                $prepared = $this->preparePayload($validated);

                ServiceOrder::query()->updateOrCreate(
                    array('order_no' => $prepared['order_no']),
                    $prepared
                );

                if ($uniqueKey !== null) {
                    $this->seenUniqueValues[$uniqueKey] = true;
                }

                $this->insertedCount++;
            } catch (\Throwable $exception) {
                $this->addFailure($rowNumber, 'row', array($exception->getMessage()), $rowArray);
            }
        }
    }

    protected function prepareRow(array $row): array
    {
        if (! array_key_exists('contract_no', $row) && array_key_exists('contract_code', $row)) {
            $row['contract_no'] = $row['contract_code'];
        }

        if (! array_key_exists('requested_date', $row) && array_key_exists('start_date', $row)) {
            $row['requested_date'] = $row['start_date'];
        }

        $row = parent::prepareRow($row);
        $row['client_code'] = $this->normalize(isset($row['client_code']) ? $row['client_code'] : null);
        $row['contract_no'] = $this->normalize(isset($row['contract_no']) ? $row['contract_no'] : null);
        $row['sales_order_no'] = $this->normalize(isset($row['sales_order_no']) ? $row['sales_order_no'] : null);
        $row['sales_order_name'] = $this->normalize(isset($row['sales_order_name']) ? $row['sales_order_name'] : (isset($row['so_name']) ? $row['so_name'] : null));
        $row['state_code'] = $this->normalize(isset($row['state_code']) ? $row['state_code'] : null);
        $row['requested_date'] = $this->excelDate(isset($row['requested_date']) ? $row['requested_date'] : null);
        $row['status'] = ServiceOrder::normalizeStatus(isset($row['status']) ? $row['status'] : 'Active');

        return $row;
    }

    protected function preparePayload(array $row): array
    {
        $contractNo = $this->normalizeKey((string) $row['contract_no']);

        if (! isset($this->contractsByNumber[$contractNo])) {
            throw new RuntimeException('Contract number not found.');
        }

        $clientCode = $this->normalizeKey((string) $row['client_code']);

        if ($this->contractsByNumber[$contractNo]['client_code'] !== $clientCode) {
            throw new RuntimeException('Client code does not match the selected contract.');
        }

        $musterStartDay = (int) (isset($row['muster_start_day']) ? $row['muster_start_day'] : 1);
        list($periodStartDate, $periodEndDate) = $this->resolvePeriodDates($row['requested_date'], $musterStartDay);

        $payload = array(
            'contract_id' => $this->contractsByNumber[$contractNo]['id'],
            'state_id' => $this->resolveStateId($row),
            'location_id' => null,
            'team_id' => null,
            'operation_executive_id' => null,
            'order_no' => $this->resolvedOrderNumber($row),
            'so_name' => isset($row['sales_order_name']) ? $row['sales_order_name'] : null,
            'requested_date' => $row['requested_date'],
            'scheduled_date' => null,
            'period_start_date' => $periodStartDate,
            'period_end_date' => $periodEndDate,
            'muster_start_day' => $musterStartDay,
            'muster_cycle_type' => $musterStartDay === 21 ? '21-20' : '1-last',
            'muster_due_days' => 0,
            'auto_generate_muster' => true,
            'status' => ServiceOrder::normalizeStatus(isset($row['status']) ? $row['status'] : 'Active'),
            'priority' => 'Medium',
            'amount' => null,
            'remarks' => isset($row['remarks']) ? $row['remarks'] : null
        );

        return $this->timestamps($payload);
    }

    private function resolvePeriodDates($requestedDate, $musterStartDay): array
    {
        $date = Carbon::parse($requestedDate)->startOfDay();
        $startDay = max(1, min(31, $musterStartDay));
        $anchorMonth = $date->day >= $startDay ? $date->copy() : $date->copy()->subMonth();
        $periodStart = $anchorMonth->copy()->day(min($startDay, $anchorMonth->daysInMonth))->startOfDay();
        $periodEnd = $periodStart->copy()->addMonth()->subDay()->endOfDay();

        return array($periodStart->toDateString(), $periodEnd->toDateString());
    }

    private function resolveStateId(array $row): int
    {
        $stateCode = $this->normalizeKey((string) (isset($row['state_code']) ? $row['state_code'] : ''));

        if ($stateCode === '' || ! isset($this->statesByCode[$stateCode])) {
            throw new RuntimeException('State code not found.');
        }

        return (int) $this->statesByCode[$stateCode];
    }

    private function resolvedOrderNumber(array $row): string
    {
        $provided = $this->normalizeKey((string) (isset($row['sales_order_no']) ? $row['sales_order_no'] : ''));

        if ($provided !== '') {
            return $provided;
        }

        $signature = implode('|', array(
            $this->normalizeKey((string) (isset($row['contract_no']) ? $row['contract_no'] : '')),
            $this->normalizeKey((string) (isset($row['state_code']) ? $row['state_code'] : '')),
            (string) (isset($row['requested_date']) ? $row['requested_date'] : ''),
            preg_replace('/\s+/', '', strtolower((string) (isset($row['sales_order_name']) ? $row['sales_order_name'] : '')))
        ));

        return 'SO-' . Carbon::parse((string) $row['requested_date'])->format('Ymd') . '-' . strtoupper(substr(md5($signature), 0, 8));
    }

    protected function uniqueKey(array $row): ?string
    {
        return 'service-orders:' . $this->resolvedOrderNumber($row);
    }
}
