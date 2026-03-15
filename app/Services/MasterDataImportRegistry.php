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
                    ['name', 'code', 'contact_person', 'email', 'phone', 'industry', 'is_active'],
                    ['Acme Industries', 'CL-001', 'Ravi Kumar', 'ravi@acme.com', '9876543210', 'Manufacturing', '1'],
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
                    ['client_code', 'state_code', 'operation_area_code', 'name', 'city', 'address', 'postal_code', 'is_active'],
                    ['CL-001', 'MH', 'MUM-WEST', 'Mumbai Plant 1', 'Mumbai', 'Plot 10, Industrial Estate', '400001', '1'],
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
                    ['client_code', 'location_name', 'location_city', 'contract_no', 'start_date', 'end_date', 'contract_value', 'status', 'scope'],
                    ['CL-001', 'Mumbai Plant 1', 'Mumbai', 'CNT-24001', '2026-03-01', '2027-02-28', '2500000', 'Active', 'Facility staffing and compliance support'],
                ],
            ],
            'service-orders' => [
                'label' => 'Service Orders',
                'route' => 'service-orders.index',
                'modal_id' => 'serviceOrdersImportModal',
                'permission' => 'service_orders.import',
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