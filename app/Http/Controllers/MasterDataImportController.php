<?php

namespace App\Http\Controllers;

use App\Exports\MasterDataTemplateExport;
use App\Imports\ClientsImport;
use App\Imports\ContractsImport;
use App\Imports\LocationsImport;
use App\Imports\ServiceOrdersImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class MasterDataImportController extends Controller
{
    public function store(Request $request, string $type): RedirectResponse
    {
        $config = $this->config($type);

        $validator = validator($request->all(), [
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'type' => ['required', Rule::in(array_keys($this->configs()))],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route($config['route'])
                ->withErrors($validator)
                ->withInput()
                ->with('open_modal', $config['modal_id']);
        }

        /** @var \App\Imports\AbstractMasterDataImport $import */
        $import = app($config['import']);

        try {
            Excel::import($import, $request->file('import_file'));
        } catch (Throwable $exception) {
            return redirect()
                ->route($config['route'])
                ->with('error', 'Import failed: ' . $exception->getMessage())
                ->with('open_modal', $config['modal_id']);
        }

        return redirect()
            ->route($config['route'])
            ->with('status', sprintf('%s import completed. %d rows inserted.', $config['label'], $import->insertedCount()))
            ->with('import_report', [
                'type' => $type,
                'label' => $config['label'],
                'inserted' => $import->insertedCount(),
                'failed' => count($import->failures()),
                'failures' => $import->failures(),
            ]);
    }

    public function template(string $type)
    {
        $config = $this->config($type);

        return Excel::download(
            new MasterDataTemplateExport($config['template_rows']),
            $config['file_name']
        );
    }

    private function config(string $type): array
    {
        return $this->configs()[$type] ?? abort(404);
    }

    private function configs(): array
    {
        return [
            'clients' => [
                'label' => 'Clients',
                'route' => 'clients.index',
                'modal_id' => 'clientsImportModal',
                'import' => ClientsImport::class,
                'file_name' => 'clients-import-template.xlsx',
                'template_rows' => [
                    ['name', 'code', 'contact_person', 'email', 'phone', 'industry', 'is_active'],
                    ['Acme Industries', 'CL-001', 'Ravi Kumar', 'ravi@acme.com', '9876543210', 'Manufacturing', '1'],
                ],
            ],
            'locations' => [
                'label' => 'Locations',
                'route' => 'locations.index',
                'modal_id' => 'locationsImportModal',
                'import' => LocationsImport::class,
                'file_name' => 'locations-import-template.xlsx',
                'template_rows' => [
                    ['client_code', 'state_code', 'operation_area_code', 'name', 'city', 'address', 'postal_code', 'is_active'],
                    ['CL-001', 'MH', 'MUM-WEST', 'Mumbai Plant 1', 'Mumbai', 'Plot 10, Industrial Estate', '400001', '1'],
                ],
            ],
            'contracts' => [
                'label' => 'Contracts',
                'route' => 'contracts.index',
                'modal_id' => 'contractsImportModal',
                'import' => ContractsImport::class,
                'file_name' => 'contracts-import-template.xlsx',
                'template_rows' => [
                    ['client_code', 'location_name', 'location_city', 'contract_no', 'start_date', 'end_date', 'contract_value', 'status', 'scope'],
                    ['CL-001', 'Mumbai Plant 1', 'Mumbai', 'CNT-24001', '2026-03-01', '2027-02-28', '2500000', 'Active', 'Facility staffing and compliance support'],
                ],
            ],
            'service-orders' => [
                'label' => 'Service Orders',
                'route' => 'service-orders.index',
                'modal_id' => 'serviceOrdersImportModal',
                'import' => ServiceOrdersImport::class,
                'file_name' => 'service-orders-import-template.xlsx',
                'template_rows' => [
                    ['contract_no', 'team_code', 'order_no', 'requested_date', 'scheduled_date', 'status', 'priority', 'amount', 'remarks'],
                    ['CNT-24001', 'TM-001', 'SO-24001', '2026-03-10', '2026-03-12', 'Open', 'Medium', '150000', 'Initial deployment'],
                ],
            ],
        ];
    }
}
