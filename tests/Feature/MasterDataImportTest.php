<?php

use App\Imports\ContractsImport;
use App\Imports\LocationsImport;
use App\Jobs\ProcessMasterDataImport;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ImportBatch;
use App\Models\Location;
use App\Models\OperationArea;
use App\Models\State;
use App\Services\MasterDataImportRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

it('treats existing duplicate contract numbers as failures during contract import', function () {
    $state = State::query()->create([
        'name' => 'West Bengal',
        'code' => 'WB',
        'region' => 'East',
        'is_active' => true,
    ]);

    $operationArea = OperationArea::query()->create([
        'state_id' => $state->id,
        'name' => 'Kolkata Area',
        'code' => 'KOL',
        'description' => null,
        'is_active' => true,
    ]);

    $client = Client::query()->create([
        'name' => 'Bidhannagar Client',
        'code' => '100359',
        'is_active' => true,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-100359',
        'name' => 'Bidhannagar Site',
        'address' => 'Sector V',
        'city' => null,
        'postal_code' => null,
        'is_active' => true,
    ]);

    Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => $location->id,
        'contract_no' => 'CC/100359/0324/01349',
        'contract_name' => 'Old Name',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'contract_value' => null,
        'status' => 'Inactive',
        'scope' => 'Old scope',
    ]);

    $import = new ContractsImport();

    $import->collection(new Collection([
        collect([
            'client_code' => 100359,
            'contract_no' => 'CC/100359/0324/01349',
            'contract_name' => 'Bidhannagar',
            'start_date' => '2026-03-01',
            'end_date' => '2027-02-28',
            'status' => 'Active',
            'scope' => 'Updated scope',
        ]),
    ]));

    $contract = Contract::query()->where('contract_no', 'CC/100359/0324/01349')->firstOrFail();

    expect($import->failures())->toHaveCount(1)
        ->and($import->failures()[0]['attribute'])->toBe('contract_no')
        ->and($import->failures()[0]['errors'])->toBe(['Duplicate contract number already exists.'])
        ->and($contract->contract_name)->toBe('Old Name')
        ->and($contract->status)->toBe('Inactive')
        ->and($contract->scope)->toBe('Old scope')
        ->and($contract->start_date?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($contract->end_date?->format('Y-m-d'))->toBe('2026-12-31');
});

it('accepts client_cod alias, trims fields, and logs duplicate contract rows in the same file', function () {
    $state = State::query()->create([
        'name' => 'West Bengal',
        'code' => 'WB',
        'region' => 'East',
        'is_active' => true,
    ]);

    $operationArea = OperationArea::query()->create([
        'state_id' => $state->id,
        'name' => 'Kolkata Area',
        'code' => 'KOL',
        'description' => null,
        'is_active' => true,
    ]);

    $client = Client::query()->create([
        'name' => 'Bidhannagar Client',
        'code' => '100359',
        'is_active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-100359',
        'name' => 'Bidhannagar Site',
        'address' => 'Sector V',
        'city' => null,
        'postal_code' => null,
        'is_active' => true,
    ]);

    $import = new ContractsImport();

    $import->collection(new Collection([
        collect([
            'client_cod' => 100359,
            'contract_no' => ' CNT-001 ',
            'contract_name' => '  Contract Alpha  ',
            'start_date' => '2026-03-01',
            'end_date' => '2027-02-28',
            'status' => ' Active ',
            'scope' => '  Updated scope  ',
        ]),
        collect([
            'client_code' => 100359,
            'contract_no' => 'CNT-001',
            'contract_name' => 'Contract Beta',
            'start_date' => '2026-03-01',
            'end_date' => '2027-02-28',
            'status' => 'Active',
            'scope' => 'Duplicate row',
        ]),
    ]));

    $contract = Contract::query()->where('contract_no', 'CNT-001')->firstOrFail();

    expect($contract->contract_name)->toBe('Contract Alpha')
        ->and($contract->status)->toBe('Active')
        ->and($contract->scope)->toBe('Updated scope')
        ->and($import->failures())->toHaveCount(1)
        ->and($import->failures()[0]['attribute'])->toBe('contract_no')
        ->and($import->failures()[0]['errors'])->toBe(['Duplicate contract number found in the import file.']);
});

it('records duplicate contracts from csv files as failures', function () {
    $state = State::query()->create([
        'name' => 'West Bengal',
        'code' => 'WB',
        'region' => 'East',
        'is_active' => true,
    ]);

    $operationArea = OperationArea::query()->create([
        'state_id' => $state->id,
        'name' => 'Kolkata Area',
        'code' => 'KOL',
        'description' => null,
        'is_active' => true,
    ]);

    $client = Client::query()->create([
        'name' => 'Bidhannagar Client',
        'code' => '100359',
        'is_active' => true,
    ]);

    $location = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-100359',
        'name' => 'Bidhannagar Site',
        'address' => 'Sector V',
        'city' => null,
        'postal_code' => null,
        'is_active' => true,
    ]);

    Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => $location->id,
        'contract_no' => 'CC/100359/0324/01349',
        'contract_name' => 'Old Name',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'contract_value' => null,
        'status' => 'Inactive',
        'scope' => 'Old scope',
    ]);

    $csvPath = storage_path('app/test-contract-import.csv');

    file_put_contents($csvPath, implode("\n", [
        'client_cod,contract_no,contract_name,start_date,end_date,status,scope',
        '100359,CC/100359/0324/01349,Bidhannagar,2026-03-01,2027-02-28,Active,Updated scope',
    ]));

    $import = new ContractsImport();
    Excel::import($import, $csvPath);
    @unlink($csvPath);

    $contract = Contract::query()->where('contract_no', 'CC/100359/0324/01349')->firstOrFail();

    expect($import->failures())->toHaveCount(1)
        ->and($import->failures()[0]['attribute'])->toBe('contract_no')
        ->and($import->failures()[0]['errors'])->toBe(['Duplicate contract number already exists.'])
        ->and($contract->contract_name)->toBe('Old Name')
        ->and($contract->status)->toBe('Inactive')
        ->and($contract->scope)->toBe('Old scope');
});

it('imports contracts even when the client has no linked location', function () {
    $client = Client::query()->create([
        'name' => 'ICICI BANK LIMITED',
        'code' => '100414',
        'is_active' => true,
    ]);

    $import = new ContractsImport();

    $import->collection(new Collection([
        collect([
            'client_code' => 100414,
            'contract_no' => 'CC/100414/0324/01349',
            'contract_name' => 'Baguiati',
            'start_date' => '2026-03-01',
            'end_date' => '2027-02-28',
            'status' => 'Active',
            'scope' => null,
        ]),
    ]));

    $contract = Contract::query()->where('contract_no', 'CC/100414/0324/01349')->firstOrFail();

    expect($import->failures())->toBe([])
        ->and($contract->client_id)->toBe($client->id)
        ->and($contract->location_id)->toBeNull()
        ->and($contract->contract_name)->toBe('Baguiati');
});

it('updates an existing location during import', function () {
    $client = Client::query()->create([
        'name' => 'Acme Industries',
        'code' => 'CL-001',
        'is_active' => true,
    ]);

    $state = State::query()->create([
        'name' => 'Maharashtra',
        'code' => 'MH',
        'region' => 'West',
        'is_active' => true,
    ]);

    $operationArea = OperationArea::query()->create([
        'state_id' => $state->id,
        'name' => 'Mumbai Area',
        'code' => 'MUM',
        'description' => null,
        'is_active' => true,
    ]);

    Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-001',
        'name' => 'Old Mumbai Plant',
        'address' => 'Old address',
        'city' => null,
        'postal_code' => null,
        'is_active' => false,
    ]);

    $import = new LocationsImport();

    $import->collection(new Collection([
        collect([
            'client_code' => 'CL-001',
            'state_code' => 'MH',
            'code' => 'LOC-001',
            'name' => 'Mumbai Plant 1',
            'address' => 'Plot 10, Industrial Estate',
            'is_active' => '1',
        ]),
    ]));

    $location = Location::query()->where('code', 'LOC-001')->firstOrFail();

    expect($import->failures())->toBe([])
        ->and($location->name)->toBe('Mumbai Plant 1')
        ->and($location->address)->toBe('Plot 10, Industrial Estate')
        ->and($location->is_active)->toBeTrue();
});

it('detects contracts import files from legacy client_cod headings', function () {
    $registry = app(MasterDataImportRegistry::class);

    expect($registry->detectTypeFromHeadings([
        'client_cod',
        'contract_no',
        'contract_name',
        'start_date',
        'end_date',
        'status',
        'scope',
    ]))->toBe('contracts');
});

it('stores failed import count as total error entries', function () {
    Storage::disk('local')->put('imports/test/contracts-failures.csv', implode("\n", [
        'client_code,contract_no,contract_name,start_date,end_date,status,scope',
        ',CC/100359/0324/01349,,2026-03-01,2027-02-28,Active,',
        ',CC/100414/0324/01349,Baguiati,2026-03-01,2027-02-28,Active,',
    ]));

    $batch = ImportBatch::query()->create([
        'user_id' => null,
        'type' => 'contracts',
        'status' => 'pending',
        'disk' => 'local',
        'stored_path' => 'imports/test/contracts-failures.csv',
        'original_file_name' => 'contracts-failures.csv',
    ]);

    $job = new ProcessMasterDataImport($batch->id);
    $job->handle(
        app(MasterDataImportRegistry::class),
        app(App\Services\ActivityLogService::class),
        app(App\Services\DashboardStatsService::class),
    );

    $batch->refresh();

    expect($batch->status)->toBe('completed')
        ->and($batch->inserted_rows)->toBe(0)
        ->and($batch->failed_rows)->toBe(3)
        ->and($batch->failure_report)->toHaveCount(3);
});

it('stores duplicate contract rows as failed batch entries', function () {
    $client = Client::query()->create([
        'name' => 'ICICI BANK LIMITED',
        'code' => '100414',
        'is_active' => true,
    ]);

    Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => null,
        'contract_no' => 'CC/100414/0324/01349',
        'contract_name' => 'Existing Contract',
        'start_date' => '2026-03-01',
        'end_date' => '2027-02-28',
        'contract_value' => null,
        'status' => 'Active',
        'scope' => 'Existing scope',
    ]);

    Storage::disk('local')->put('imports/test/contracts-duplicates.csv', implode("\n", [
        'client_code,contract_no,contract_name,start_date,end_date,status,scope',
        '100414,CC/100414/0324/01349,Baguiati,2026-03-01,2027-02-28,Active,Scope 1',
        '100414,CC/100414/0324/01349,Baguiati-2,2026-03-01,2027-02-28,Active,Scope 2',
    ]));

    $batch = ImportBatch::query()->create([
        'user_id' => null,
        'type' => 'contracts',
        'status' => 'pending',
        'disk' => 'local',
        'stored_path' => 'imports/test/contracts-duplicates.csv',
        'original_file_name' => 'contracts-duplicates.csv',
    ]);

    $job = new ProcessMasterDataImport($batch->id);
    $job->handle(
        app(MasterDataImportRegistry::class),
        app(App\Services\ActivityLogService::class),
        app(App\Services\DashboardStatsService::class),
    );

    $batch->refresh();

    expect($batch->status)->toBe('completed')
        ->and($batch->inserted_rows)->toBe(0)
        ->and($batch->failed_rows)->toBe(2)
        ->and($batch->failure_report)->toHaveCount(2)
        ->and($batch->failure_report[0]['attribute'])->toBe('contract_no')
        ->and($batch->failure_report[0]['errors'])->toBe(['Duplicate contract number already exists.'])
        ->and(Contract::query()->where('contract_no', 'CC/100414/0324/01349')->count())->toBe(1);
});