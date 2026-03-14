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
    private array $locationsByComposite;
    private array $locationsByName;

    public function __construct()
    {
        $this->clientsByCode = Client::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
            ->all();

        $this->locationsByComposite = [];
        $this->locationsByName = [];

        Location::query()
            ->select('id', 'client_id', 'name', 'city')
            ->get()
            ->each(function (Location $location): void {
                $nameKey = $this->normalizeKey($location->name);
                $cityKey = $this->normalizeKey($location->city);

                $this->locationsByComposite[$location->client_id . '|' . $nameKey . '|' . $cityKey] = $location->id;
                $this->locationsByName[$location->client_id . '|' . $nameKey][] = $location->id;
            });
    }

    protected function modelClass(): string
    {
        return Contract::class;
    }

    public function rules(): array
    {
        return [
            'client_code' => ['required', 'string', 'max:20'],
            'location_name' => ['required', 'string', 'max:255'],
            'location_city' => ['nullable', 'string', 'max:255'],
            'contract_no' => ['required', 'string', 'max:50', Rule::unique('contracts', 'contract_no')],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Draft', 'Active', 'Expired', 'Closed'])],
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
        $locationId = $this->resolveLocationId($clientId, (string) $row['location_name'], $row['location_city'] ?? null);

        return $this->timestamps([
            'client_id' => $clientId,
            'location_id' => $locationId,
            'contract_no' => $this->normalizeKey((string) $row['contract_no']),
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'] ?? null,
            'contract_value' => $row['contract_value'] ?? null,
            'status' => $row['status'],
            'scope' => $row['scope'] ?? null,
        ]);
    }

    protected function uniqueKey(array $row): ?string
    {
        return 'contracts:' . $this->normalizeKey((string) $row['contract_no']);
    }

    private function resolveLocationId(int $clientId, string $locationName, ?string $city): int
    {
        $nameKey = $this->normalizeKey($locationName);
        $cityKey = $this->normalizeKey($city);
        $compositeKey = $clientId . '|' . $nameKey . '|' . $cityKey;

        if ($cityKey !== null && isset($this->locationsByComposite[$compositeKey])) {
            return $this->locationsByComposite[$compositeKey];
        }

        $nameKeyLookup = $clientId . '|' . $nameKey;

        if (! isset($this->locationsByName[$nameKeyLookup])) {
            throw new RuntimeException('Location not found for the given client.');
        }

        if (count($this->locationsByName[$nameKeyLookup]) > 1 && $cityKey === null) {
            throw new RuntimeException('Multiple locations matched. Provide location_city to identify the site.');
        }

        return $this->locationsByName[$nameKeyLookup][0];
    }
}
