<?php

namespace App\Imports;

use App\Models\Contract;
use App\Models\ServiceOrder;
use App\Models\Team;
use Illuminate\Validation\Rule;
use RuntimeException;

class ServiceOrdersImport extends AbstractMasterDataImport
{
    private array $contractsByNumber;
    private array $teamsByCode;

    public function __construct()
    {
        $this->contractsByNumber = Contract::query()
            ->get(['id', 'location_id', 'contract_no'])
            ->mapWithKeys(function (Contract $contract) {
                return [
                    $this->normalizeKey($contract->contract_no) => [
                        'id' => $contract->id,
                        'location_id' => $contract->location_id,
                    ],
                ];
            })
            ->all();

        $this->teamsByCode = Team::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
            ->all();
    }

    protected function modelClass(): string
    {
        return ServiceOrder::class;
    }

    public function rules(): array
    {
        return [
            'contract_no' => ['required', 'string', 'max:50'],
            'team_code' => ['nullable', 'string', 'max:20'],
            'order_no' => ['required', 'string', 'max:50', Rule::unique('service_orders', 'order_no')],
            'requested_date' => ['required', 'date'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:requested_date'],
            'status' => ['required', Rule::in(['Open', 'Assigned', 'In Progress', 'Completed', 'Cancelled'])],
            'priority' => ['required', Rule::in(['Low', 'Medium', 'High', 'Critical'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareRow(array $row): array
    {
        $row = parent::prepareRow($row);
        $row['requested_date'] = $this->excelDate($row['requested_date'] ?? null);
        $row['scheduled_date'] = $this->excelDate($row['scheduled_date'] ?? null);

        return $row;
    }

    protected function preparePayload(array $row): array
    {
        $contractNo = $this->normalizeKey((string) $row['contract_no']);

        if (! isset($this->contractsByNumber[$contractNo])) {
            throw new RuntimeException('Contract number not found.');
        }

        $teamId = null;

        if (! empty($row['team_code'])) {
            $teamCode = $this->normalizeKey((string) $row['team_code']);

            if (! isset($this->teamsByCode[$teamCode])) {
                throw new RuntimeException('Team code not found.');
            }

            $teamId = $this->teamsByCode[$teamCode];
        }

        return $this->timestamps([
            'contract_id' => $this->contractsByNumber[$contractNo]['id'],
            'location_id' => $this->contractsByNumber[$contractNo]['location_id'],
            'team_id' => $teamId,
            'order_no' => $this->normalizeKey((string) $row['order_no']),
            'requested_date' => $row['requested_date'],
            'scheduled_date' => $row['scheduled_date'] ?? null,
            'status' => $row['status'],
            'priority' => $row['priority'],
            'amount' => $row['amount'] ?? null,
            'remarks' => $row['remarks'] ?? null,
        ]);
    }

    protected function uniqueKey(array $row): ?string
    {
        return 'service-orders:' . $this->normalizeKey((string) $row['order_no']);
    }
}
