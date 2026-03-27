<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Location;
use App\Models\OperationArea;
use App\Models\State;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use RuntimeException;

class LocationsImport extends AbstractMasterDataImport implements WithCustomValueBinder, WithMapping
{
    /**
     * Override bindValue to force code columns to be treated as STRING.
     * This preserves leading zeros in location codes, client codes, and state codes.
     *
     * @return bool
     */
    public function bindValue(Cell $cell, mixed $value)
    {
        // Columns that should be treated as strings: client_code, state_code, location_code
        if (in_array($cell->getColumn(), ['B', 'C', 'D'], true)) {
            $cell->setValueExplicit($cell->getValue(), DataType::TYPE_STRING);
            return true;
        }

        // Use default behavior for other columns
        return parent::bindValue($cell, $value);
    }

    /**
     * Map each row to preserve leading zeros in code columns.
     *
     * @return array
     */
    public function map($row): array
    {
        // Preserve client_code with leading zeros
        if (isset($row['client_code']) && !is_null($row['client_code'])) {
            $row['client_code'] = trim((string)$row['client_code']);
        }

        // Preserve state_code with leading zeros
        if (isset($row['state_code']) && !is_null($row['state_code'])) {
            $row['state_code'] = trim((string)$row['state_code']);
        }

        // Preserve location_code with leading zeros
        if (isset($row['location_code']) && !is_null($row['location_code'])) {
            $row['location_code'] = trim((string)$row['location_code']);
        }

        // DEBUG: Log transformations
        Log::debug('LocationsImport.map()', [
            'client_code' => $row['client_code'] ?? null,
            'state_code' => $row['state_code'] ?? null,
            'location_code' => $row['location_code'] ?? null,
        ]);

        return $row;
    }

    private array $clientsByCode;
    private array $statesByCode;
    private array $operationAreasByState;

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

        // Pre-load operation areas by state for faster lookup
        $this->operationAreasByState = OperationArea::query()
            ->with('state')
            ->get()
            ->groupBy(fn ($area) => $this->normalizeKey((string) $area->state?->code))
            ->map(fn ($areas) => $areas->pluck('id')->toArray())
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

    protected function prepareRow(array $row): array
    {
        $row = parent::prepareRow($row);

        $row['client_code'] = $this->normalize($row['client_code'] ?? null);
        $row['state_code'] = $this->normalize($row['state_code'] ?? null);
        $row['code'] = $this->normalize($row['code'] ?? null);

        return $row;
    }

    protected function preparePayload(array $row): array
    {
        $clientCode = $this->normalizeKey((string) $row['client_code']);
        $stateCode = $this->normalizeKey((string) $row['state_code']);

        if (! isset($this->clientsByCode[$clientCode])) {
            throw new RuntimeException("Client code '{$row['client_code']}' not found in system. Please add this client first.");
        }

        if (! isset($this->statesByCode[$stateCode])) {
            throw new RuntimeException("State code '{$row['state_code']}' not found in system. Available states must be added first.");
        }

        $stateId = $this->statesByCode[$stateCode];
        
        // Try to find an operation area for this state
        $areaIds = $this->operationAreasByState[$stateCode] ?? [];
        
        if (empty($areaIds)) {
            throw new RuntimeException("No operation area configured for state '{$row['state_code']}'. Please create at least one operation area for this state.");
        }

        // Use the first operation area for this state
        $areaId = $areaIds[0];

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

    protected function databaseUniqueBy(): array
    {
        return ['code'];
    }

    protected function upsertColumns(): ?array
    {
        return ['client_id', 'state_id', 'operation_area_id', 'name', 'city', 'address', 'postal_code', 'is_active', 'updated_at'];
    }
}
