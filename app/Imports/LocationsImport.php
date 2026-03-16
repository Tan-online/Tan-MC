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
            'code' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable'],
        ];
    }

    protected function preparePayload(array $row): array
    {
        $clientCode = $this->normalizeKey((string) $row['client_code']);
        $stateCode = $this->normalizeKey((string) $row['state_code']);

        if (! isset($this->clientsByCode[$clientCode])) {
            throw new RuntimeException('Client code not found.');
        }

        if (! isset($this->statesByCode[$stateCode])) {
            throw new RuntimeException('State code not found.');
        }

        $stateId = $this->statesByCode[$stateCode];
        $areaId = OperationArea::query()
            ->where('state_id', $stateId)
            ->where('is_active', true)
            ->value('id')
            ?? OperationArea::query()->where('state_id', $stateId)->value('id');

        if (! $areaId) {
            throw new RuntimeException('No operation area found for the given state.');
        }

        return $this->timestamps([
            'client_id' => $this->clientsByCode[$clientCode],
            'state_id' => $stateId,
            'operation_area_id' => $areaId,
            'code' => $this->normalizeKey($row['code'] ?? null),
            'name' => $row['name'],
            'city' => null,
            'address' => $row['address'] ?? null,
            'postal_code' => null,
            'is_active' => $this->booleanValue($row['is_active'] ?? null),
        ]);
    }

    protected function uniqueKey(array $row): ?string
    {
        $code = $this->normalizeKey($row['code'] ?? null);

        if ($code !== null) {
            return 'locations:' . $code;
        }

        return 'locations:' . $this->normalizeKey((string) $row['client_code']) . ':' . $this->normalizeKey((string) $row['state_code']) . ':' . $this->normalizeKey((string) $row['name']);
    }
}
