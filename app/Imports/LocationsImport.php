<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Location;
use App\Models\OperationArea;
use App\Models\State;
use RuntimeException;

class LocationsImport extends AbstractMasterDataImport
{
    private array $clientsByCode;
    private array $statesByCode;
    private array $areasByCode;

    public function __construct()
    {
        $this->clientsByCode = Client::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
            ->all();

        $this->statesByCode = State::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
            ->all();

        $this->areasByCode = OperationArea::query()
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [$this->normalizeKey((string) $code) => $id])
            ->all();
    }

    protected function modelClass(): string
    {
        return Location::class;
    }

    public function rules(): array
    {
        return [
            'client_code' => ['required', 'string', 'max:20'],
            'state_code' => ['required', 'string', 'max:10'],
            'operation_area_code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable'],
        ];
    }

    protected function preparePayload(array $row): array
    {
        $clientCode = $this->normalizeKey((string) $row['client_code']);
        $stateCode = $this->normalizeKey((string) $row['state_code']);
        $areaCode = $this->normalizeKey((string) $row['operation_area_code']);

        if (! isset($this->clientsByCode[$clientCode])) {
            throw new RuntimeException('Client code not found.');
        }

        if (! isset($this->statesByCode[$stateCode])) {
            throw new RuntimeException('State code not found.');
        }

        if (! isset($this->areasByCode[$areaCode])) {
            throw new RuntimeException('Operation area code not found.');
        }

        return $this->timestamps([
            'client_id' => $this->clientsByCode[$clientCode],
            'state_id' => $this->statesByCode[$stateCode],
            'operation_area_id' => $this->areasByCode[$areaCode],
            'name' => $row['name'],
            'city' => $row['city'] ?? null,
            'address' => $row['address'] ?? null,
            'postal_code' => $row['postal_code'] ?? null,
            'is_active' => $this->booleanValue($row['is_active'] ?? null),
        ]);
    }
}
