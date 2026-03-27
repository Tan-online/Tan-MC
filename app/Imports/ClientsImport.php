<?php

namespace App\Imports;

use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class ClientsImport extends AbstractMasterDataImport implements WithCustomValueBinder, WithMapping
{
    /**
     * Override bindValue to force code columns to be treated as STRING.
     * This preserves leading zeros in client codes.
     *
     * @return bool
     */
    public function bindValue(Cell $cell, mixed $value)
    {
        // Column B: code - must be STRING to preserve leading zeros
        if ($cell->getColumn() === 'B') {
            $cell->setValueExplicit($cell->getValue(), DataType::TYPE_STRING);
            return true;
        }

        // Use default behavior for other columns
        return parent::bindValue($cell, $value);
    }

    /**
     * Map each row to preserve leading zeros in code column.
     *
     * @return array
     */
    public function map($row): array
    {
        // Preserve code with leading zeros
        if (isset($row['code']) && !is_null($row['code'])) {
            $code = trim((string)$row['code']);
            $row['code'] = $code;
            
            Log::debug('ClientsImport.map()', [
                'client_code' => $code,
            ]);
        }

        return $row;
    }

    protected function modelClass(): string
    {
        return Client::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable'],
        ];
    }

    protected function prepareRow(array $row): array
    {
        $row = parent::prepareRow($row);
        $row['code'] = $this->normalize($row['code'] ?? null);

        return $row;
    }

    protected function preparePayload(array $row): array
    {
        return $this->timestamps([
            'name' => $row['name'],
            'code' => $this->normalizeKey($row['code']),
            'contact_person' => null,
            'email' => null,
            'phone' => null,
            'industry' => null,
            'is_active' => $this->booleanValue($row['is_active'] ?? null),
        ]);
    }

    protected function uniqueKey(array $row): ?string
    {
        return isset($row['code']) ? 'clients:' . $this->normalizeKey((string) $row['code']) : null;
    }

    protected function databaseUniqueBy(): array
    {
        return ['code'];
    }

    protected function upsertColumns(): ?array
    {
        return ['name', 'contact_person', 'email', 'phone', 'industry', 'is_active', 'updated_at'];
    }
}
