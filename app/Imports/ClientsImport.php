<?php

namespace App\Imports;

use App\Models\Client;
use Illuminate\Validation\Rule;

class ClientsImport extends AbstractMasterDataImport
{
    protected function modelClass(): string
    {
        return Client::class;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('clients', 'code')],
            'is_active' => ['nullable'],
        ];
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
}
