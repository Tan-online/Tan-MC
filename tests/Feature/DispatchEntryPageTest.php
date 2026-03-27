<?php

use App\Models\Client;
use App\Models\Contract;
use App\Models\Location;
use App\Models\MusterCycle;
use App\Models\MusterExpected;
use App\Models\MusterReceived;
use App\Models\OperationArea;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLocation;
use App\Models\State;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

function createDispatchEntryFixture(): array
{
    $currentMonth = now()->startOfMonth();
    $previousMonth = $currentMonth->copy()->subMonth();
    $nextMonth = $currentMonth->copy()->addMonth();

    $adminRole = Role::query()->firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Admin']
    );

    $operationsRole = Role::query()->firstOrCreate(
        ['slug' => 'operations'],
        ['name' => 'Operations']
    );

    $admin = User::factory()->create([
        'status' => 'Active',
        'role_id' => $adminRole->id,
    ]);
    $admin->syncRoles([$adminRole->id]);

    $executive = User::factory()->create([
        'status' => 'Active',
        'role_id' => $operationsRole->id,
        'name' => 'Riya Executive',
    ]);
    $executive->syncRoles([$operationsRole->id]);

    $executiveTwo = User::factory()->create([
        'status' => 'Active',
        'role_id' => $operationsRole->id,
        'name' => 'Amit Executive',
    ]);
    $executiveTwo->syncRoles([$operationsRole->id]);

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
        'name' => 'Acme Industries',
        'code' => 'ACME001',
        'is_active' => true,
    ]);

    $locationOne = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-001',
        'name' => 'Salt Lake',
        'address' => 'Salt Lake',
        'city' => 'Kolkata',
        'postal_code' => null,
        'is_active' => true,
    ]);

    $locationTwo = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-002',
        'name' => 'Howrah',
        'address' => 'Howrah',
        'city' => 'Howrah',
        'postal_code' => null,
        'is_active' => true,
    ]);

    $locationThree = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-003',
        'name' => 'Durgapur',
        'address' => 'Durgapur',
        'city' => 'Durgapur',
        'postal_code' => null,
        'is_active' => true,
    ]);

    $contract = Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => $locationOne->id,
        'contract_no' => 'CONT-001',
        'contract_name' => 'Security Services',
        'start_date' => '2026-01-01',
        'end_date' => null,
        'contract_value' => null,
        'status' => 'Active',
        'scope' => 'Guards',
    ]);

    $serviceOrderMarch = ServiceOrder::query()->create([
        'contract_id' => $contract->id,
        'state_id' => $state->id,
        'location_id' => $locationOne->id,
        'team_id' => null,
        'operation_executive_id' => $executive->id,
        'order_no' => 'SO-CURRENT',
        'so_name' => 'Current Coverage',
        'requested_date' => $currentMonth->toDateString(),
        'scheduled_date' => null,
        'period_start_date' => $currentMonth->toDateString(),
        'period_end_date' => $currentMonth->copy()->endOfMonth()->toDateString(),
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 3,
        'auto_generate_muster' => true,
        'status' => 'Active',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $serviceOrderApril = ServiceOrder::query()->create([
        'contract_id' => $contract->id,
        'state_id' => $state->id,
        'location_id' => $locationThree->id,
        'team_id' => null,
        'operation_executive_id' => $executive->id,
        'order_no' => 'SO-NEXT',
        'so_name' => 'Next Coverage',
        'requested_date' => $nextMonth->toDateString(),
        'scheduled_date' => null,
        'period_start_date' => $nextMonth->toDateString(),
        'period_end_date' => $nextMonth->copy()->endOfMonth()->toDateString(),
        'muster_start_day' => 1,
        'muster_cycle_type' => '1-last',
        'muster_due_days' => 3,
        'auto_generate_muster' => true,
        'status' => 'Active',
        'priority' => 'Medium',
        'amount' => null,
        'remarks' => null,
    ]);

    $serviceOrderMarch->locations()->attach($locationOne->id, [
        'start_date' => $currentMonth->toDateString(),
        'end_date' => null,
        'operation_executive_id' => $executive->id,
        'muster_due_days' => 3,
        'wage_month' => $currentMonth->toDateString(),
    ]);
    $serviceOrderMarch->locations()->attach($locationTwo->id, [
        'start_date' => $currentMonth->toDateString(),
        'end_date' => null,
        'operation_executive_id' => $executiveTwo->id,
        'muster_due_days' => 3,
        'wage_month' => $previousMonth->toDateString(),
    ]);
    $serviceOrderApril->locations()->attach($locationThree->id, [
        'start_date' => $nextMonth->toDateString(),
        'end_date' => null,
        'operation_executive_id' => $executive->id,
        'muster_due_days' => 3,
        'wage_month' => $nextMonth->toDateString(),
    ]);

    $cycle = MusterCycle::query()->create([
        'contract_id' => $contract->id,
        'service_order_id' => $serviceOrderMarch->id,
        'month' => (int) $currentMonth->format('n'),
        'year' => (int) $currentMonth->format('Y'),
        'cycle_type' => '1-last',
        'cycle_label' => 'Cycle 1-Last',
        'cycle_start_date' => $currentMonth->toDateString(),
        'cycle_end_date' => $currentMonth->copy()->endOfMonth()->toDateString(),
        'due_date' => $currentMonth->copy()->endOfMonth()->addDays(3)->toDateString(),
        'generated_at' => now(),
    ]);

    $expected = MusterExpected::query()->create([
        'muster_cycle_id' => $cycle->id,
        'contract_id' => $contract->id,
        'location_id' => $locationOne->id,
        'executive_mapping_id' => null,
        'acted_by_user_id' => $admin->id,
        'status' => 'Received',
        'received_via' => 'Email',
        'received_at' => now(),
        'last_action_at' => now(),
    ]);

    MusterReceived::query()->create([
        'muster_expected_id' => $expected->id,
        'action_by_user_id' => $admin->id,
        'status' => 'Received',
        'receive_mode' => 'Email',
        'received_at' => now(),
    ]);

    MusterExpected::query()->create([
        'muster_cycle_id' => $cycle->id,
        'contract_id' => $contract->id,
        'location_id' => $locationTwo->id,
        'executive_mapping_id' => null,
        'acted_by_user_id' => $admin->id,
        'status' => 'Received',
        'received_via' => 'Hard Copy',
        'received_at' => now(),
        'last_action_at' => now(),
    ]);

    return compact(
        'admin',
        'executive',
        'executiveTwo',
        'client',
        'locationOne',
        'locationTwo',
        'locationThree',
        'serviceOrderMarch',
        'serviceOrderApril',
        'currentMonth'
    );
}

it('renders one dispatch row per sales-order and location for the selected wage month', function () {
    $fixture = createDispatchEntryFixture();

    actingAs($fixture['admin']);

    get(route('dispatch-entry.index', [
        'month' => $fixture['currentMonth']->format('Y-m'),
        'search' => 'Acme',
    ]))
        ->assertOk()
        ->assertSee('SO-CURRENT')
        ->assertSee('Salt Lake')
        ->assertSee('Howrah')
        ->assertDontSee('Durgapur')
        ->assertSee('Rows: 2')
        ->assertSee('Received By')
        ->assertSee('Despatched Type')
        ->assertSee($fixture['admin']->name)
        ->assertSee('Mail')
        ->assertSee('Hard Copy')
        ->assertSee('Set Month')
        ->assertDontSee('<th>Wage Month</th>', false)
        ->assertDontSee('<th class="text-end">Download</th>', false);
});

it('filters dispatch rows by operation executive', function () {
    $fixture = createDispatchEntryFixture();

    actingAs($fixture['admin']);

    get(route('dispatch-entry.index', [
        'month' => $fixture['currentMonth']->format('Y-m'),
        'executive_id' => $fixture['executive']->id,
    ]))
        ->assertOk()
        ->assertSee('Salt Lake')
        ->assertDontSee('Howrah');
});

it('records dispatch per service-order location row and streams the row download', function () {
    $fixture = createDispatchEntryFixture();

    actingAs($fixture['admin']);

    $assignment = ServiceOrderLocation::query()
        ->whereHas('serviceOrder', fn ($query) => $query->where('order_no', 'SO-CURRENT'))
        ->whereHas('location', fn ($query) => $query->where('code', 'LOC-001'))
        ->firstOrFail();

    patch(route('dispatch-entry.dispatch', $assignment))
        ->assertRedirect();

    $assignment->refresh();

    expect($assignment->dispatched_at)->not->toBeNull()
        ->and($assignment->dispatched_by_user_id)->toBe($fixture['admin']->id);

    $response = get(route('dispatch-entry.download', $assignment))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('SO Number')
        ->toContain('SO-CURRENT')
        ->toContain('Salt Lake');
});
