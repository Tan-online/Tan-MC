<?php

namespace App\Services;

use App\Imports\ClientsImport;
use App\Imports\ContractsImport;
use App\Imports\LocationsImport;
use App\Imports\ServiceOrderLocationsImport;
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
                    ['client_code', 'contract_code', 'sales_order_no', 'sales_order_name', 'state_code', 'start_date', 'muster_start_day', 'status', 'remarks'],
                    ['CL-001', 'CNT-24001', 'SO-24001', 'East Cluster April', 'WB', '2026-03-10', '1', 'Active', 'Primary April rollout'],
                    ['# FORMAT NOTE', '# contract_code maps to contract_no', '# optional SO number', '# optional SO name', '# required state code', '# SO start date', '# optional, default 1', '# Active or Terminate', '# optional remarks'],
                ],
                'heading_sets' => [
                    ['client_code', 'contract_code', 'sales_order_no', 'sales_order_name', 'state_code', 'start_date', 'muster_start_day', 'status', 'remarks'],
                    ['client_code', 'contract_no', 'sales_order_no', 'sales_order_name', 'state_code', 'requested_date', 'muster_start_day', 'status', 'remarks'],
                ],
            ],
            'service-order-locations' => [
                'label' => 'Sales Order Locations',
                'route' => 'service-orders.index',
                'modal_id' => 'serviceOrderLocationsImportModal',
                'permission' => 'service_orders.import',
                'import' => ServiceOrderLocationsImport::class,
                'file_name' => 'service-order-locations-import-template.xlsx',
                'template_rows' => [
                    ['sales_order_no', 'location_code', 'start_date', 'end_date', 'operation_executive_employee_code', 'muster_due_days'],
                    ['SO-24001', 'LOC-001', '2026-03-10', '', 'EMP-0005', '3'],
                    ['SO-24001', 'LOC-007', '2026-03-10', '', 'EMP-0005', '3'],
                ],
                'heading_sets' => [
                    ['sales_order_no', 'location_code', 'start_date', 'end_date', 'operation_executive_employee_code', 'muster_due_days'],
                    ['sales_order_no', 'location_codes', 'start_date', 'end_date', 'operation_executive_employee_code', 'muster_due_days'],
                ],
            ],
        ];
    }

    public function detectTypeFromHeadings(array $headings): ?string
    {
        $normalizedHeadings = array_values(array_filter(array_map(
            fn ($heading) => $this->normalizeHeading($heading),
            $headings
        )));

        if ($normalizedHeadings === []) {
            return null;
        }

        foreach ($this->configs() as $type => $config) {
            if ($this->headingsMatchType($normalizedHeadings, $type, $config)) {
                return $type;
            }
        }

        return null;
    }

    private function headingsMatchType(array $headings, string $type, array $config): bool
    {
        $headingSets = $config['heading_sets'] ?? [$config['template_rows'][0] ?? []];

        foreach ($headingSets as $headingSet) {
            $expected = array_values(array_filter(array_map(
                fn ($heading) => $this->normalizeHeading($heading),
                $headingSet
            )));

            if (count($headings) !== count($expected)) {
                continue;
            }

            $matches = true;

            foreach ($expected as $index => $expectedHeading) {
                $actualHeading = $headings[$index] ?? null;

                if ($actualHeading === $expectedHeading) {
                    continue;
                }

                if (! in_array($actualHeading, $this->headingAliases($type, $expectedHeading), true)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    private function headingAliases(string $type, string $heading): array
    {
        return match ([$type, $heading]) {
            ['contracts', 'client_code'] => ['client_cod'],
            ['service-orders', 'contract_no'] => ['contract_code'],
            ['service-orders', 'sales_order_name'] => ['so_name'],
            ['service-orders', 'requested_date'] => ['start_date'],
            ['service-order-locations', 'location_code'] => ['location_codes'],
            default => [],
        };
    }

    private function normalizeHeading(mixed $heading): ?string
    {
        $heading = trim(strtolower((string) $heading));

        return $heading === '' ? null : $heading;
    }
}