<?php

use App\Models\Client;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Location;
use App\Models\MusterCycle;
use App\Models\MusterExpected;
use App\Models\OperationArea;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 24, 10, 0, 0));
});

afterEach(function () {
    Carbon::setTestNow();
});

function createOperationsWorkspaceFixture(): array
{
    $operationsRole = Role::query()->firstOrCreate(
        ['slug' => 'operations'],
        ['name' => 'Operations']
    );

    $state = State::query()->create([
        'name' => 'West Bengal',
        'code' => 'WB',
        'region' => 'East',
        'is_active' => true,
    ]);

    $department = Department::query()->create([
        'name' => 'Operations',
        'code' => 'OPS',
        'description' => null,
        'is_active' => true,
    ]);

    $operationArea = OperationArea::query()->create([
        'state_id' => $state->id,
        'name' => 'Kolkata Area',
        'code' => 'KOL',
        'description' => null,
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'name' => 'Manager User',
        'employee_code' => 'MGR001',
        'designation' => 'Manager',
        'role_id' => $operationsRole->id,
        'status' => 'Active',
    ]);
    $manager->syncRoles([$operationsRole->id]);

    $employee = User::factory()->create([
        'name' => 'Employee User',
        'employee_code' => 'EMP001',
        'designation' => 'Executive',
        'role_id' => $operationsRole->id,
        'manager_id' => $manager->id,
        'status' => 'Active',
    ]);
    $employee->syncRoles([$operationsRole->id]);

    $outsider = User::factory()->create([
        'name' => 'Outside User',
        'employee_code' => 'OUT001',
        'designation' => 'Executive',
        'role_id' => $operationsRole->id,
        'status' => 'Active',
    ]);
    $outsider->syncRoles([$operationsRole->id]);

    $team = Team::query()->create([
        'name' => 'North Team',
        'code' => 'TEAM-N',
        'department_id' => $department->id,
        'operation_area_id' => $operationArea->id,
        'operation_executive_id' => $employee->id,
        'manager_id' => $manager->id,
        'hod_id' => null,
        'lead_name' => null,
        'members_count' => 1,
        'is_active' => true,
    ]);
    $team->executives()->sync([
        $employee->id => ['is_primary' => true],
    ]);

    $client = Client::query()->create([
        'name' => 'Visible Client',
        'code' => 'CLI001',
        'is_active' => true,
    ]);

    $visibleLocation = Location::query()->create([
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

    $outsideLocation = Location::query()->create([
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

    $visibleContract = Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => $visibleLocation->id,
        'contract_no' => 'CONT-001',
        'contract_name' => 'Visible Contract',
        'start_date' => '2026-01-01',
        'end_date' => null,
        'contract_value' => null,
        'status' => 'Active',
        'scope' => 'Security',
    ]);

    $outsideContract = Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => $outsideLocation->id,
        'contract_no' => 'CONT-002',
        'contract_name' => 'Outside Contract',
        'start_date' => '2026-01-01',
        'end_date' => null,
        'contract_value' => null,
        'status' => 'Active',
        'scope' => 'Security',
    ]);

    $currentMonth = now()->startOfMonth();

    $visibleOrder = ServiceOrder::query()->create([
        'contract_id' => $visibleContract->id,
        'state_id' => $state->id,
        'location_id' => $visibleLocation->id,
        'team_id' => $team->id,
        'operation_executive_id' => $employee->id,
        'order_no' => 'SO-VISIBLE',
        'so_name' => 'Visible Order',
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

    $outsideOrder = ServiceOrder::query()->create([
        'contract_id' => $outsideContract->id,
        'state_id' => $state->id,
        'location_id' => $outsideLocation->id,
        'team_id' => null,
        'operation_executive_id' => $outsider->id,
        'order_no' => 'SO-OUTSIDE',
        'so_name' => 'Outside Order',
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

    $visibleOrder->locations()->attach($visibleLocation->id, [
        'start_date' => $currentMonth->toDateString(),
        'end_date' => null,
        'operation_executive_id' => $employee->id,
        'muster_due_days' => 3,
        'wage_month' => $currentMonth->toDateString(),
    ]);

    $outsideOrder->locations()->attach($outsideLocation->id, [
        'start_date' => $currentMonth->toDateString(),
        'end_date' => null,
        'operation_executive_id' => $outsider->id,
        'muster_due_days' => 3,
        'wage_month' => $currentMonth->toDateString(),
    ]);

    $visibleCycle = MusterCycle::query()->create([
        'contract_id' => $visibleContract->id,
        'service_order_id' => $visibleOrder->id,
        'month' => (int) $currentMonth->format('n'),
        'year' => (int) $currentMonth->format('Y'),
        'cycle_type' => '1-last',
        'cycle_label' => 'Cycle Mar 2026',
        'cycle_start_date' => $currentMonth->toDateString(),
        'cycle_end_date' => $currentMonth->copy()->endOfMonth()->toDateString(),
        'due_date' => $currentMonth->copy()->endOfMonth()->addDays(3)->toDateString(),
        'generated_at' => now(),
    ]);

    MusterExpected::query()->create([
        'muster_cycle_id' => $visibleCycle->id,
        'contract_id' => $visibleContract->id,
        'location_id' => $visibleLocation->id,
        'executive_mapping_id' => null,
        'acted_by_user_id' => $manager->id,
        'status' => 'Returned',
        'received_via' => 'Email',
        'received_at' => now(),
        'last_action_at' => now(),
    ]);

    return compact('manager', 'employee', 'outsider', 'team');
}

it('shows employee dashboard with only own locations and hides supervisor team navigation', function () {
    $fixture = createOperationsWorkspaceFixture();

    actingAs($fixture['employee']);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee('Operations Workspace')
        ->assertSee('Active Wage Month')
        ->assertSee('March 2026')
        ->assertSee('Salt Lake')
        ->assertDontSee('Howrah')
        ->assertDontSee('Team Performance')
        ->assertDontSee(route('operations-workspace.teams', absolute: false))
        ->assertSee(route('operations-workspace.locations', absolute: false));
});

it('shows manager dashboard with team aggregation and scoped workspace pages', function () {
    $fixture = createOperationsWorkspaceFixture();

    actingAs($fixture['manager']);

    get(route('dashboard'))
        ->assertOk()
        ->assertSee('Team Performance')
        ->assertSee('Employee User')
        ->assertSee('Team summary')
        ->assertSee('Operations')
        ->assertSee('Kolkata Area')
        ->assertSee('Salt Lake')
        ->assertDontSee('Howrah')
        ->assertSee(route('operations-workspace.teams', absolute: false));

    get(route('operations-workspace.teams'))
        ->assertOk()
        ->assertSee('North Team');

    get(route('operations-workspace.locations'))
        ->assertOk()
        ->assertSee('Salt Lake')
        ->assertDontSee('Howrah');
});

it('shows a compact workspace locations page and supports executive filtering for managers', function () {
    $fixture = createOperationsWorkspaceFixture();
    $manager = $fixture['manager'];
    $team = $fixture['team'];
    $operationsRole = Role::query()->where('slug', 'operations')->firstOrFail();
    $client = Client::query()->firstOrFail();
    $state = State::query()->firstOrFail();
    $operationArea = OperationArea::query()->firstOrFail();
    $currentMonth = now()->startOfMonth();

    $secondExecutive = User::factory()->create([
        'name' => 'Second Executive',
        'employee_code' => 'EMP002',
        'designation' => 'Executive',
        'role_id' => $operationsRole->id,
        'manager_id' => $manager->id,
        'status' => 'Active',
    ]);
    $secondExecutive->syncRoles([$operationsRole->id]);

    $team->executives()->syncWithoutDetaching([
        $secondExecutive->id => ['is_primary' => false],
    ]);

    $secondLocation = Location::query()->create([
        'client_id' => $client->id,
        'state_id' => $state->id,
        'operation_area_id' => $operationArea->id,
        'code' => 'LOC-003',
        'name' => 'Park Street',
        'address' => 'Park Street',
        'city' => 'Kolkata',
        'postal_code' => null,
        'is_active' => true,
    ]);

    $secondContract = Contract::query()->create([
        'client_id' => $client->id,
        'location_id' => $secondLocation->id,
        'contract_no' => 'CONT-003',
        'contract_name' => 'Second Visible Contract',
        'start_date' => '2026-01-01',
        'end_date' => null,
        'contract_value' => null,
        'status' => 'Active',
        'scope' => 'Security',
    ]);

    $secondOrder = ServiceOrder::query()->create([
        'contract_id' => $secondContract->id,
        'state_id' => $state->id,
        'location_id' => $secondLocation->id,
        'team_id' => $team->id,
        'operation_executive_id' => $secondExecutive->id,
        'order_no' => 'SO-SECOND',
        'so_name' => 'Second Visible Order',
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

    $secondOrder->locations()->attach($secondLocation->id, [
        'start_date' => $currentMonth->toDateString(),
        'end_date' => null,
        'operation_executive_id' => $secondExecutive->id,
        'muster_due_days' => 3,
        'wage_month' => $currentMonth->toDateString(),
    ]);

    actingAs($manager);

    get(route('operations-workspace.locations'))
        ->assertOk()
        ->assertSeeText('Executive Name')
        ->assertSeeText('All executives')
        ->assertSeeText('Salt Lake')
        ->assertSeeText('Park Street')
        ->assertDontSeeText('Active Wage Month')
        ->assertDontSeeText('Received By')
        ->assertDontSeeText('Action Taken')
        ->assertDontSeeText('All statuses');

    get(route('operations-workspace.locations', ['executive_id' => $fixture['employee']->id]))
        ->assertOk()
        ->assertSee('Salt Lake')
        ->assertDontSee('Park Street');
});

it('shows selected team details when a workspace team is clicked', function () {
    $fixture = createOperationsWorkspaceFixture();

    actingAs($fixture['manager']);

    get(route('operations-workspace.teams', ['team_id' => $fixture['team']->id]))
        ->assertOk()
        ->assertSee('Team Details')
        ->assertSee('North Team')
        ->assertSee('Manager User')
        ->assertSee('Employee User')
        ->assertSee('Visible Service Orders')
        ->assertSee('Visible Locations');
});
