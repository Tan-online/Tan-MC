<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function assignRole(User $user, string $slug): void
{
    $role = Role::query()->where('slug', $slug)->firstOrFail();
    $user->syncRoles([$role->id]);
}

it('restricts user management to super admins', function () {
    $admin = User::factory()->create();
    assignRole($admin, 'admin');

    test()->actingAs($admin)
        ->get(route('users.index'))
        ->assertForbidden();
});

it('scopes client visibility for operations users', function () {
    $operationsUser = User::factory()->create([
        'designation' => 'Executive',
    ]);
    assignRole($operationsUser, 'operations');

    $otherOperationsUser = User::factory()->create([
        'designation' => 'Executive',
    ]);
    assignRole($otherOperationsUser, 'operations');

    $stateId = DB::table('states')->insertGetId([
        'name' => 'Tamil Nadu',
        'code' => 'TN',
        'region' => 'South',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $operationAreaId = DB::table('operation_areas')->insertGetId([
        'name' => 'Chennai Zone',
        'code' => 'CHN',
        'state_id' => $stateId,
        'description' => 'Test operation area',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $visibleClientId = DB::table('clients')->insertGetId([
        'name' => 'Visible Client',
        'code' => 'VC001',
        'contact_person' => null,
        'email' => null,
        'phone' => null,
        'industry' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $hiddenClientId = DB::table('clients')->insertGetId([
        'name' => 'Hidden Client',
        'code' => 'HC001',
        'contact_person' => null,
        'email' => null,
        'phone' => null,
        'industry' => null,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('executive_mappings')->insert([
        [
            'client_id' => $visibleClientId,
            'contract_id' => null,
            'location_id' => null,
            'operation_area_id' => $operationAreaId,
            'executive_user_id' => $operationsUser->id,
            'executive_name' => $operationsUser->name,
            'designation' => 'Executive',
            'email' => $operationsUser->email,
            'phone' => $operationsUser->phone,
            'is_primary' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'client_id' => $hiddenClientId,
            'contract_id' => null,
            'location_id' => null,
            'operation_area_id' => $operationAreaId,
            'executive_user_id' => $otherOperationsUser->id,
            'executive_name' => $otherOperationsUser->name,
            'designation' => 'Executive',
            'email' => $otherOperationsUser->email,
            'phone' => $otherOperationsUser->phone,
            'is_primary' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    test()->actingAs($operationsUser)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Visible Client')
        ->assertDontSee('Hidden Client');
});