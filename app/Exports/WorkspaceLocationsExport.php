<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkspaceLocationsExport implements FromView, WithColumnWidths, WithStyles
{
    private $locationRows;
    private $wageMonth;

    public function __construct($locationRows, $wageMonth)
    {
        $this->locationRows = $locationRows;
        $this->wageMonth = $wageMonth;
    }

    public function view(): View
    {
        return view('exports.workspace-locations', [
            'locationRows' => $this->locationRows,
            'wageMonth' => $this->wageMonth,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Client Name
            'B' => 15, // SO Number
            'C' => 15, // SO Start Date
            'D' => 15, // Location Code
            'E' => 25, // Location Name
            'F' => 18, // Location Start Date
            'G' => 18, // Location End Date
            'H' => 25, // Executive
            'I' => 12, // Status
            'J' => 18, // Type
            'K' => 20, // Action Date
            'L' => 30, // Remarks
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2937']],
            ],
        ];
    }
}
