<?php

namespace App\Services;

use App\Imports\ClientsImport;
use App\Imports\ContractsImport;
use App\Imports\LocationsImport;
use App\Imports\ServiceOrdersImport;

class MasterDataImportRegistry
{
    public function config(string $type): array
    {
        return $this->configs()[$type] ?? abort(404);
    }

    public function configs(): array
    {
        return [
            'clients' => [
                'label' => 'Clients',
                'route' => 'clients.index',
                'modal_id' => 'clientsImportModal',
                'permission' => 'clients.import',
                'import' => ClientsImport::class,
                'file_name' => 'clients-import-template.xlsx',
                'template_rows' => [
                    ['name', 'code', 'is_active'],
                    ['Acme Industries', 'CL-001', '1'],
                ],
            ],
            'locations' => [
                'label' => 'Locations',
                'route' => 'locations.index',
                'modal_id' => 'locationsImportModal',
                'permission' => 'locations.import',
                'import' => LocationsImport::class,
                'file_name' => 'locations-import-template.xlsx',
                'template_rows' => [
                    ['client_code', 'state_code', 'code', 'name', 'address', 'is_active'],
                    ['CL-001', 'MH', 'LOC-001', 'Mumbai Plant 1', 'Plot 10, Industrial Estate', '1'],
                ],
            ],
            'contracts' => [
                'label' => 'Contracts',
                'route' => 'contracts.index',
                'modal_id' => 'contractsImportModal',
                'permission' => 'contracts.import',
                'import' => ContractsImport::class,
                'file_name' => 'contracts-import-template.xlsx',
                'template_rows' => [
                    ['client_code', 'contract_no', 'contract_name', 'start_date', 'end_date', 'status', 'scope'],
                    ['CL-001', 'CNT-24001', 'Facility Staffing FY26', '2026-03-01', '2027-02-28', 'Active', 'Facility staffing and compliance support'],
                ],
            ],
            'service-orders' => [
                'label' => 'Sales Orders',
                'route' => 'service-orders.index',
                'modal_id' => 'serviceOrdersImportModal',
                'permission' => 'service_orders.import',
                'import' => ServiceOrdersImport::class,
                'file_name' => 'service-orders-import-template.xlsx',
                'template_rows' => [
                    ['client_code', 'contract_no', 'sales_order_no', 'requested_date', 'muster_start_day', 'muster_due_days', 'status', 'operation_executive_employee_code', 'team_code', 'location_codes', 'location_mapping', 'remarks'],
                    ['CL-001', 'CNT-24001', 'SO-24001', '2026-03-10', '21', '3', 'Open', 'EMP-0192', 'TM-001', 'LOC-001|LOC-007', 'LOC-001|2026-03-21|2026-04-20;LOC-007|2026-03-25|2026-04-20', 'Initial deployment'],
                    ['# FORMAT NOTE', '# Keep client_code and contract_no aligned', '# sales_order_no must be unique', '# YYYY-MM-DD', '# 1-31', '# 0-15', '# Open/Assigned/In Progress/Completed/Cancelled', '# optional employee_code', '# optional team code', '# optional pipe list of location codes', '# optional semicolon list: LOCATION_CODE|START_DATE|END_DATE', '# mapping dates optional'],
                ],
            ],
        ];
    }
}