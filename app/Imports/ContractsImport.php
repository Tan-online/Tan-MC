<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use Illuminate\Validation\Rule;
use RuntimeException;

class ContractsImport extends AbstractMasterDataImport
{
    private array $clientsByCode;
    private array $primaryLocationByClient;

    public function __construct()
    {
        $this->clientsByCode = Client::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
            ->all();

        $this->primaryLocationByClient = Location::query()
            ->select('id', 'client_id', 'is_active')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get()
            ->groupBy('client_id')
            ->map(fn ($rows) => (int) $rows->first()->id)
            ->all();
    }

    protected function modelClass(): string
    {
        return Contract::class;
    }

    public function rules(): array
    {
        return [
            'client_code' => ['required', 'string', 'max:20'],
            'contract_no' => ['required', 'string', 'max:50', Rule::unique('contracts', 'contract_no')],
            'contract_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'scope' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareRow(array $row): array
    {
        $row = parent::prepareRow($row);
        $row['start_date'] = $this->excelDate($row['start_date'] ?? null);
        $row['end_date'] = $this->excelDate($row['end_date'] ?? null);

        return $row;
    }

    protected function preparePayload(array $row): array
    {
        $clientCode = $this->normalizeKey((string) $row['client_code']);

        if (! isset($this->clientsByCode[$clientCode])) {
            throw new RuntimeException('Client code not found.');
        }

        $clientId = $this->clientsByCode[$clientCode];

        if (! isset($this->primaryLocationByClient[$clientId])) {
            throw new RuntimeException('No location found for the given client.');
        }

        $locationId = $this->primaryLocationByClient[$clientId];

        return $this->timestamps([
            'client_id' => $clientId,
            'location_id' => $locationId,
            'contract_no' => $this->normalizeKey((string) $row['contract_no']),
            'contract_name' => trim((string) $row['contract_name']),
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'] ?? null,
            'contract_value' => null,
            'status' => $row['status'],
            'scope' => $row['scope'] ?? null,
        ]);
    }

    protected function uniqueKey(array $row): ?string
    {
        return 'contracts:' . $this->normalizeKey((string) $row['contract_no']);
    }
}
