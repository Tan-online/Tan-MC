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
    private array $existingContractNumbers;

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

        $this->existingContractNumbers = Contract::query()
            ->pluck('contract_no')
            ->mapWithKeys(fn ($contractNo) => [$this->normalizeKey((string) $contractNo) => true])
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
            'contract_no' => ['required', 'string', 'max:50'],
            'contract_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'scope' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareRow(array $row): array
    {
        if (! array_key_exists('client_code', $row) && array_key_exists('client_cod', $row)) {
            $row['client_code'] = $row['client_cod'];
        }

        $row = parent::prepareRow($row);
        $row['client_code'] = $this->normalize($row['client_code'] ?? null);
        $row['contract_no'] = $this->normalize($row['contract_no'] ?? null);
        $row['contract_name'] = $row['contract_name'] !== null ? trim((string) $row['contract_name']) : null;
        $row['status'] = $row['status'] !== null ? trim((string) $row['status']) : null;
        $row['scope'] = $row['scope'] !== null ? trim((string) $row['scope']) : null;
        $row['start_date'] = $this->excelDate($row['start_date'] ?? null);
        $row['end_date'] = $this->excelDate($row['end_date'] ?? null);

        return $row;
    }

    protected function recordValidatorFailures(int $rowNumber, array $errors, array $values): void
    {
        $messages = $errors;

        if (isset($messages['contract_no']) && Contract::query()->where('contract_no', $this->normalizeKey($values['contract_no'] ?? null))->exists()) {
            unset($messages['contract_no']);
        }

        if ($messages !== []) {
            parent::recordValidatorFailures($rowNumber, $messages, $values);
        }
    }

    protected function beforePreparePayload(int $rowNumber, array $validated, array $values): bool
    {
        $contractNumber = $this->normalizeKey($validated['contract_no'] ?? null);

        if ($contractNumber !== null && isset($this->existingContractNumbers[$contractNumber])) {
            $this->addFailure($rowNumber, 'contract_no', ['Duplicate contract number already exists.'], $values);

            return false;
        }

        return true;
    }

    protected function preparePayload(array $row): array
    {
        $clientCode = $this->normalizeKey((string) $row['client_code']);

        if (! isset($this->clientsByCode[$clientCode])) {
            throw new RuntimeException('Client code not found.');
        }

        $clientId = $this->clientsByCode[$clientCode];

        $locationId = $this->primaryLocationByClient[$clientId] ?? null;

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

    protected function addFailure(int $rowNumber, string $attribute, array $errors, array $values): void
    {
        if ($attribute === 'row' && isset($values['contract_no'])) {
            $contractNumber = $this->normalizeKey((string) $values['contract_no']);

            if ($contractNumber !== null && isset($this->seenUniqueValues['contracts:' . $contractNumber])) {
                $attribute = 'contract_no';
                $errors = ['Duplicate contract number found in the import file.'];
            }
        }

        if ($attribute === 'row') {
            $normalizedErrors = strtolower(implode(' ', $errors));

            if (str_contains($normalizedErrors, 'duplicate contract number')) {
                $attribute = 'contract_no';
            }
        }

        parent::addFailure($rowNumber, $attribute, $errors, $values);
    }
}
