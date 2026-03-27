<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

abstract class AbstractMasterDataImport extends DefaultValueBinder implements ToCollection, WithChunkReading, WithHeadingRow, SkipsEmptyRows
{
    use RemembersChunkOffset;

    protected array $failures = [];
    protected int $insertedCount = 0;
    protected array $seenUniqueValues = [];

    public function collection(Collection $rows): void
    {
        $batch = [];

        foreach ($rows as $index => $row) {
            $rowArray = $this->prepareRow($row->toArray());

            if ($this->rowIsEmpty($rowArray)) {
                continue;
            }

            $rowNumber = $this->headingRow() + $this->getChunkOffset() + $index + 1;
            $validator = Validator::make($rowArray, $this->rules());

            if ($validator->fails()) {
                $this->recordValidatorFailures($rowNumber, $validator->errors()->toArray(), $rowArray);
                continue;
            }

            if (! $this->beforePreparePayload($rowNumber, $validator->validated(), $rowArray)) {
                continue;
            }

            $uniqueKey = $this->uniqueKey($validator->validated());

            if ($uniqueKey !== null && isset($this->seenUniqueValues[$uniqueKey])) {
                $this->addFailure($rowNumber, 'row', ['Duplicate value found in the import file.'], $rowArray);
                continue;
            }

            try {
                $batch[] = $this->preparePayload($validator->validated());

                if ($uniqueKey !== null) {
                    $this->seenUniqueValues[$uniqueKey] = true;
                }
            } catch (Throwable $exception) {
                $this->addFailure($rowNumber, 'row', [$exception->getMessage()], $rowArray);
            }
        }

        if ($batch !== []) {
            $uniqueBy = $this->databaseUniqueBy();

            if ($uniqueBy !== []) {
                $this->modelClass()::query()->upsert($batch, $uniqueBy, $this->upsertColumns());
            } else {
                $this->modelClass()::query()->insert($batch);
            }

            $this->insertedCount += count($batch);
        }
    }

    public function chunkSize(): int
    {
        return 250;
    }



    public function headingRow(): int
    {
        return 1;
    }

    public function insertedCount(): int
    {
        return $this->insertedCount;
    }

    public function failures(): array
    {
        return $this->failures;
    }

    public function rules(): array
    {
        return [];
    }

    abstract protected function modelClass(): string;

    abstract protected function preparePayload(array $row): array;

    protected function prepareRow(array $row): array
    {
        return array_map(function ($value) {
            if (is_int($value)) {
                return (string) $value;
            }

            if (is_float($value)) {
                $normalized = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');

                return $normalized === '-0' ? '0' : $normalized;
            }

            if (is_string($value)) {
                $value = str_replace(["\xC2\xA0", "\u{00A0}"], ' ', $value);
                $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
                $value = trim($value);
            }

            return $value === '' ? null : $value;
        }, $row);
    }

    protected function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    protected function uniqueKey(array $row): ?string
    {
        return null;
    }

    protected function beforePreparePayload(int $rowNumber, array $validated, array $values): bool
    {
        return true;
    }

    protected function booleanValue(mixed $value, bool $default = true): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'active'], true);
    }

    protected function excelDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    protected function normalizeKey(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtoupper(trim($value));
    }

    protected function timestamps(array $payload): array
    {
        $timestamp = now();

        return $payload + [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    protected function databaseUniqueBy(): array
    {
        return [];
    }

    protected function upsertColumns(): ?array
    {
        return null;
    }

    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    protected function recordValidatorFailures(int $rowNumber, array $errors, array $values): void
    {
        foreach ($errors as $attribute => $messages) {
            $this->addFailure($rowNumber, $attribute, $messages, $values);
        }
    }

    protected function addFailure(int $rowNumber, string $attribute, array $errors, array $values): void
    {
        $this->failures[] = [
            'row' => $rowNumber,
            'attribute' => $attribute,
            'errors' => array_values($errors),
            'values' => $values,
        ];
    }
}
