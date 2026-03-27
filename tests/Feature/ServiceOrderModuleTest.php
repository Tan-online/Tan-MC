<?php

use App\Imports\ServiceOrderLocationsImport;
use App\Imports\ServiceOrdersImport;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\OperationArea;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Collection;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

function createSalesOrderFixture(): array
{
    $westBengal = State::query()->create([
        'name' => 'West Bengal',
        'code' => 'WB',
        'region' => 'East',
        'is_active' => true,
    ]);

    $bihar = State::query()->create([
        'name' => 'Bihar',
        'code' => 'BR',
        'region' => 'East',
        'is_active' => true,
    ]);

    $kolkataArea = OperationArea::query()->create([
        'state_id' => $westBengal->id,
        'name' => 'Kolkata Area',
        'code' => 'KOL',
        'description' => null,
        'is_active' => true,
    ]);

    $patnaArea = OperationArea::query()->create([
        'state_id' => $bihar->id,
        'name' => 'Patna Area',
        'code' => 'PAT',
        'description' => null,
        'is_active' => true,
    ]);

    $client = Client::query()->create([
        'name' => 'ICICI BANK LIMITED',
        'code' => '100414',
        'is_active' => true,
    ]);

    $kolkataOne = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $westBengal->id,
        'operation_area_id' => $kolkataArea->id,
        'code' => 'LOC-KOL-1',
        'name' => 'Laketown',
        'address' => 'Laketown',
        'city' => 'Kolkata',
        'postal_code' => null,
        'is_active' => true,
    ]);

    $kolkataTwo = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $westBengal->id,
        'operation_area_id' => $kolkataArea->id,
        'code' => 'LOC-KOL-2',
        'name' => 'Serampore',
        'address' => 'Serampore',
        'city' => 'Hooghly',
        'postal_code' => null,
        'is_active' => true,
    ]);

    $patnaOne = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $bihar->id,
        'operation_area_id' => $patnaArea->id,
        'code' => 'LOC-PAT-1',
        'name' => 'Patna Branch',
        'address' => 'Patna',
        'city' => 'Patna',
        'postal_code' => null,
        'is_active' => true,
    ]);

    $contract = Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => $kolkataOne->id,
        'contract_no' => 'CONT001',
        'contract_name' => 'Banking Services',
        'start_date' => '2025-01-01',
        'end_date' => null,
        'contract_value' => null,
        'status' => 'Active',
        'scope' => 'Coverage',
    ]);

    $contract->locations()->syncWithoutDetaching([$kolkataOne->id, $kolkataTwo->id]);

    $role = Role::query()->firstOrCreate(
        ['slug' => 'super_admin'],
        ['name' => 'Super Admin', 'description' => 'Test role']
    );

    $user = User::factory()->create([
        'status' => 'Active',
        'role_id' => $role->id,
    ]);

    $operationsRole = Role::query()->firstOrCreate(
        ['slug' => 'operations'],
        ['name' => 'Operations', 'description' => 'Operations role']
    );

    $executive = User::factory()->create([
        'status' => 'Active',
        'role_id' => $operationsRole->id,
        'name' => 'Biswajit',
        'employee_code' => 'EMP-0005',
    ]);

    return compact('westBengal', 'bihar', 'client', 'kolkataOne', 'kolkataTwo', 'patnaOne', 'contract', 'user', 'executive');
}

it('returns client locations filtered by state and search', function () {
    $fixture = createSalesOrderFixture();

    actingAs($fixture['user']);

    $response = getJson(route('api.locations.index', [
        'client_id' => $fixture['client']->id,
        'state_id' => $fixture['westBengal']->id,
        'search' => 'lake',
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Laketown');
});

it('stores state-aware sales orders without requiring inline locations', function () {
    $fixture = createSalesOrderFixture();

    actingAs($fixture['user']);

    $response = post(route('service-orders.store'), [
        'client_id' => $fixture['client']->id,
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['westBengal']->id,
        'order_no' => 'SO-0001',
        'so_name' => 'East Zone January',
        'requested_date' => '2025-01-01',
        'muster_start_day' => 1,
        'status' => 'Active',
        'auto_generate_muster' => '1',
    ]);

    $response->assertRedirect(route('service-orders.index'));

    $serviceOrder = ServiceOrder::query()->where('order_no', 'SO-0001')->firstOrFail();

    expect($serviceOrder->state_id)->toBe($fixture['westBengal']->id)
        ->and($serviceOrder->location_id)->toBeNull()
        ->and($serviceOrder->so_name)->toBe('East Zone January')
        ->and($serviceOrder->status)->toBe('Active')
        ->and($serviceOrder->locations()->exists())->toBeFalse();
});

it('allows state locations that are not contract mapped when client matches', function () {
    $fixture = createSalesOrderFixture();

    actingAs($fixture['user']);

    $response = post(route('service-orders.store'), [
        'client_id' => $fixture['client']->id,
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['bihar']->id,
        'location_ids' => [$fixture['patnaOne']->id],
        'location_start_dates' => [
            $fixture['patnaOne']->id => '2025-02-01',
        ],
        'location_operation_executive_ids' => [
            $fixture['patnaOne']->id => $fixture['executive']->id,
        ],
        'location_muster_due_days' => [
            $fixture['patnaOne']->id => 2,
        ],
        'order_no' => 'SO-0001-BR',
        'so_name' => 'Bihar Pilot Run',
        'requested_date' => '2025-02-01',
        'muster_start_day' => 1,
        'status' => 'Active',
        'auto_generate_muster' => '1',
    ]);

    $response->assertRedirect(route('service-orders.index'));

    $serviceOrder = ServiceOrder::query()->where('order_no', 'SO-0001-BR')->firstOrFail();

    expect($serviceOrder->state_id)->toBe($fixture['bihar']->id)
        ->and($serviceOrder->locations()->where('locations.id', $fixture['patnaOne']->id)->exists())->toBeTrue();
});

it('manages sales order locations from the separate action route', function () {
    $fixture = createSalesOrderFixture();

    actingAs($fixture['user']);

    $serviceOrder = ServiceOrder::query()->create([
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['westBengal']->id,
        'location_id' => $fixture['kolkataOne']->id,
        'team_id' => null,
        'operation_executive_id' => null,
        'order_no' => 'SO-0002',
        'so_name' => 'Kolkata Renewal',
        'requested_date' => '2025-01-01',
        'scheduled_date' => null,
        'period_start_date' => '2025-01-01',
        'period_end_date' => '2025-01-31',
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 3,
        'auto_generate_muster' => true,
        'status' => 'Open',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $serviceOrder->locations()->sync([
        $fixture['kolkataOne']->id => ['start_date' => '2025-01-01', 'end_date' => null, 'operation_executive_id' => $fixture['executive']->id, 'muster_due_days' => 3],
        $fixture['kolkataTwo']->id => ['start_date' => '2025-01-01', 'end_date' => null, 'operation_executive_id' => $fixture['executive']->id, 'muster_due_days' => 3],
    ]);
    $serviceOrder->syncSummaryFromLocationAssignments();

    $response = patch(route('service-orders.locations.update', $serviceOrder), [
        'location_sync_submitted' => '1',
        'location_ids' => [$fixture['kolkataOne']->id],
        'location_start_dates' => [
            $fixture['kolkataOne']->id => '2025-01-01',
        ],
        'location_operation_executive_ids' => [
            $fixture['kolkataOne']->id => $fixture['executive']->id,
        ],
        'location_muster_due_days' => [
            $fixture['kolkataOne']->id => 4,
        ],
        'removed_location_end_dates' => [
            $fixture['kolkataTwo']->id => '2025-02-01',
        ],
    ]);

    $response->assertRedirect(route('service-orders.index'));

    $serviceOrder->refresh();
    $activePivot = $serviceOrder->locations()->where('locations.id', $fixture['kolkataOne']->id)->firstOrFail()->pivot;
    $removedPivot = $serviceOrder->locations()->where('locations.id', $fixture['kolkataTwo']->id)->firstOrFail()->pivot;

    expect($serviceOrder->location_id)->toBe($fixture['kolkataOne']->id)
        ->and($activePivot->start_date)->toBe('2025-01-01')
        ->and($activePivot->operation_executive_id)->toBe($fixture['executive']->id)
        ->and($activePivot->muster_due_days)->toBe(4)
        ->and($removedPivot->end_date)->toBe('2025-02-01');
});

it('reactivates a previously removed location when it is selected again', function () {
    $fixture = createSalesOrderFixture();

    actingAs($fixture['user']);

    $serviceOrder = ServiceOrder::query()->create([
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['westBengal']->id,
        'location_id' => null,
        'team_id' => null,
        'operation_executive_id' => null,
        'order_no' => 'SO-0002-REOPEN',
        'so_name' => 'Kolkata Reopen',
        'requested_date' => '2025-01-01',
        'scheduled_date' => null,
        'period_start_date' => '2025-01-01',
        'period_end_date' => '2025-01-31',
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 0,
        'auto_generate_muster' => true,
        'status' => 'Active',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $serviceOrder->locations()->sync([
        $fixture['kolkataOne']->id => [
            'start_date' => '2025-01-01',
            'end_date' => '2025-02-01',
            'operation_executive_id' => $fixture['executive']->id,
            'muster_due_days' => 2,
        ],
    ]);

    $response = patch(route('service-orders.locations.update', $serviceOrder), [
        'location_sync_submitted' => '1',
        'location_ids' => [$fixture['kolkataOne']->id],
        'location_start_dates' => [
            $fixture['kolkataOne']->id => '2025-03-01',
        ],
        'location_end_dates' => [
            $fixture['kolkataOne']->id => '',
        ],
        'location_operation_executive_ids' => [
            $fixture['kolkataOne']->id => $fixture['executive']->id,
        ],
        'location_muster_due_days' => [
            $fixture['kolkataOne']->id => 6,
        ],
    ]);

    $response->assertRedirect(route('service-orders.index'));

    $serviceOrder->refresh();
    $pivot = $serviceOrder->locations()->where('locations.id', $fixture['kolkataOne']->id)->firstOrFail()->pivot;

    expect($pivot->start_date)->toBe('2025-03-01')
        ->and($pivot->end_date)->toBeNull()
        ->and($pivot->operation_executive_id)->toBe($fixture['executive']->id)
        ->and($pivot->muster_due_days)->toBe(6)
        ->and($serviceOrder->location_id)->toBe($fixture['kolkataOne']->id);
});

it('treats only non-ended assignments as active locations', function () {
    $fixture = createSalesOrderFixture();

    $serviceOrder = ServiceOrder::query()->create([
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['westBengal']->id,
        'location_id' => null,
        'team_id' => null,
        'operation_executive_id' => null,
        'order_no' => 'SO-0002-ACTIVE',
        'so_name' => 'Kolkata Active Only',
        'requested_date' => '2025-01-01',
        'scheduled_date' => null,
        'period_start_date' => '2025-01-01',
        'period_end_date' => '2025-01-31',
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 0,
        'auto_generate_muster' => true,
        'status' => 'Active',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $serviceOrder->locations()->sync([
        $fixture['kolkataOne']->id => [
            'start_date' => '2025-01-01',
            'end_date' => null,
            'operation_executive_id' => $fixture['executive']->id,
            'muster_due_days' => 2,
        ],
        $fixture['kolkataTwo']->id => [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-15',
            'operation_executive_id' => $fixture['executive']->id,
            'muster_due_days' => 2,
        ],
    ]);

    $serviceOrder->syncSummaryFromLocationAssignments();
    $activeLocationIds = $serviceOrder->activeLocations()->pluck('locations.id')->all();

    expect($activeLocationIds)->toBe([$fixture['kolkataOne']->id])
        ->and($serviceOrder->fresh()->location_id)->toBe($fixture['kolkataOne']->id);
});

it('treats future-start assignments as inactive locations', function () {
    $fixture = createSalesOrderFixture();

    $serviceOrder = ServiceOrder::query()->create([
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['westBengal']->id,
        'location_id' => null,
        'team_id' => null,
        'operation_executive_id' => null,
        'order_no' => 'SO-0002-FUTURE',
        'so_name' => 'Future Start',
        'requested_date' => now()->toDateString(),
        'scheduled_date' => null,
        'period_start_date' => now()->toDateString(),
        'period_end_date' => now()->addMonth()->toDateString(),
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 0,
        'auto_generate_muster' => true,
        'status' => 'Active',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $serviceOrder->locations()->sync([
        $fixture['kolkataOne']->id => [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
            'operation_executive_id' => $fixture['executive']->id,
            'muster_due_days' => 2,
        ],
        $fixture['kolkataTwo']->id => [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => null,
            'operation_executive_id' => $fixture['executive']->id,
            'muster_due_days' => 2,
        ],
    ]);

    $serviceOrder->syncSummaryFromLocationAssignments();
    $activeLocationIds = $serviceOrder->activeLocations()->pluck('locations.id')->all();

    expect($activeLocationIds)->toBe([$fixture['kolkataOne']->id])
        ->and($serviceOrder->fresh()->location_id)->toBe($fixture['kolkataOne']->id);
});

it('imports service orders from the compact sales-order format', function () {
    $fixture = createSalesOrderFixture();

    $import = new ServiceOrdersImport();

    $import->collection(new Collection([
        collect([
            'client_code' => '100414',
            'contract_code' => 'CONT001',
            'sales_order_no' => 'SO-24001',
            'sales_order_name' => 'Laketown Cluster',
            'state_code' => 'WB',
            'start_date' => '2025-01-01',
            'muster_start_day' => 1,
            'status' => 'Active',
        ]),
    ]));

    expect($import->failures())->toBe([])
        ->and(ServiceOrder::query()->count())->toBe(1);

    $serviceOrder = ServiceOrder::query()->firstOrFail();

    expect($serviceOrder->state_id)->toBe($fixture['westBengal']->id)
        ->and($serviceOrder->locations()->count())->toBe(0)
        ->and($serviceOrder->so_name)->toBe('Laketown Cluster')
        ->and($serviceOrder->status)->toBe('Active')
        ->and($serviceOrder->order_no)->toBe('SO-24001');
});

it('imports service order location assignments from the separate format', function () {
    $fixture = createSalesOrderFixture();

    $serviceOrder = ServiceOrder::query()->create([
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['bihar']->id,
        'location_id' => null,
        'team_id' => null,
        'operation_executive_id' => null,
        'order_no' => 'SO-24002',
        'so_name' => 'Patna Exit',
        'requested_date' => '2025-03-01',
        'scheduled_date' => null,
        'period_start_date' => '2025-03-01',
        'period_end_date' => '2025-03-31',
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 0,
        'auto_generate_muster' => true,
        'status' => 'Active',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $import = new ServiceOrderLocationsImport();

    $import->collection(new Collection([
        collect([
            'sales_order_no' => 'SO-24002',
            'location_code' => 'LOC-PAT-1',
            'start_date' => '2025-03-01',
            'end_date' => '2027-03-31',
            'operation_executive_employee_code' => 'EMP-0005',
            'muster_due_days' => 5,
        ]),
    ]));

    expect($import->failures())->toBe([]);

    $serviceOrder->refresh();
    $pivot = $serviceOrder->locations()->where('locations.id', $fixture['patnaOne']->id)->firstOrFail()->pivot;

    expect($serviceOrder->state_id)->toBe($fixture['bihar']->id)
        ->and($serviceOrder->so_name)->toBe('Patna Exit')
        ->and($serviceOrder->location_id)->toBe($fixture['patnaOne']->id)
        ->and($serviceOrder->operation_executive_id)->toBe($fixture['executive']->id)
        ->and($serviceOrder->muster_due_days)->toBe(5)
        ->and($pivot->end_date)->toBe('2027-03-31')
        ->and($pivot->muster_due_days)->toBe(5);
});

it('terminates sales orders from the listing action', function () {
    $fixture = createSalesOrderFixture();

    actingAs($fixture['user']);

    $serviceOrder = ServiceOrder::query()->create([
        'contract_id' => $fixture['contract']->id,
        'state_id' => $fixture['westBengal']->id,
        'location_id' => $fixture['kolkataOne']->id,
        'team_id' => null,
        'operation_executive_id' => null,
        'order_no' => 'SO-0003',
        'so_name' => 'Terminate Me',
        'requested_date' => '2025-01-01',
        'scheduled_date' => null,
        'period_start_date' => '2025-01-01',
        'period_end_date' => '2025-01-31',
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 3,
        'auto_generate_muster' => true,
        'status' => 'Active',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $serviceOrder->locations()->sync([
        $fixture['kolkataOne']->id => ['start_date' => '2025-01-01', 'end_date' => null, 'operation_executive_id' => $fixture['executive']->id, 'muster_due_days' => 3],
    ]);
    $serviceOrder->syncSummaryFromLocationAssignments();

    $response = post(route('service-orders.terminate', $serviceOrder), [
        '_method' => 'PATCH',
    ]);

    $response->assertRedirect(route('service-orders.index'));

    $serviceOrder->refresh();

    expect($serviceOrder->display_status)->toBe('Terminate')
        ->and($serviceOrder->locations()->where('locations.id', $fixture['kolkataOne']->id)->firstOrFail()->pivot->end_date)->not->toBeNull();
});
