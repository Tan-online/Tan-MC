<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ComplianceReportExport implements FromArray
{
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
    ) {
    }

    public function array(): array
    {
        return array_merge([$this->headings], $this->rows);
    }
}
