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
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'industry' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable'],
        ];
    }

    protected function preparePayload(array $row): array
    {
        return $this->timestamps([
            'name' => $row['name'],
            'code' => $this->normalizeKey($row['code']),
            'contact_person' => $row['contact_person'] ?? null,
            'email' => isset($row['email']) ? strtolower((string) $row['email']) : null,
            'phone' => $row['phone'] ?? null,
            'industry' => $row['industry'] ?? null,
            'is_active' => $this->booleanValue($row['is_active'] ?? null),
        ]);
    }

    protected function uniqueKey(array $row): ?string
    {
        return isset($row['code']) ? 'clients:' . $this->normalizeKey((string) $row['code']) : null;
    }
}
