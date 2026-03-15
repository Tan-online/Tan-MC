<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnterpriseRbacSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect(config('erp.roles', []))
            ->map(function (array $role) {
                return Role::query()->updateOrCreate(
                    ['slug' => $role['slug']],
                    [
                        'name' => $role['name'],
                        'description' => $role['description'],
                    ]
                );
            })
            ->keyBy('slug');

        $permissions = collect(config('erp.permissions', []))
            ->map(function (array $permission) {
                return Permission::query()->updateOrCreate(
                    [
                        'module' => $permission['module'],
                        'action' => $permission['action'],
                    ],
                    ['description' => $permission['description']]
                );
            })
            ->keyBy(fn (Permission $permission) => $permission->key());

        foreach (config('erp.role_permissions', []) as $roleSlug => $permissionKeys) {
            $role = $roles->get($roleSlug);

            if (! $role) {
                continue;
            }

            if ($permissionKeys === ['*']) {
                $role->permissions()->sync($permissions->pluck('id')->all());
                continue;
            }

            $role->permissions()->sync(
                collect($permissionKeys)
                    ->map(fn (string $permissionKey) => $permissions->get($permissionKey)?->id)
                    ->filter()
                    ->values()
                    ->all()
            );
        }

        foreach (config('erp.workflows', []) as $workflowConfig) {
            $workflow = Workflow::query()->updateOrCreate(
                ['code' => $workflowConfig['code']],
                [
                    'name' => $workflowConfig['name'],
                    'description' => $workflowConfig['description'],
                    'is_active' => true,
                ]
            );

            $workflow->steps()->delete();

            foreach ($workflowConfig['steps'] as $stepConfig) {
                $role = $roles->get($stepConfig['role']);

                if (! $role) {
                    continue;
                }

                $workflow->steps()->create([
                    'step_order' => $stepConfig['step_order'],
                    'role_id' => $role->id,
                    'action' => $stepConfig['action'],
                ]);
            }
        }

        $legacyRoleMap = [
            'admin' => 'admin',
            'hod' => 'reviewer',
            'manager' => 'reviewer',
            'dispatch' => 'operations',
            'executive' => 'operations',
        ];

        $users = DB::table('users')
            ->leftJoin('roles as legacy_roles', 'legacy_roles.id', '=', 'users.role_id')
            ->select('users.id', 'legacy_roles.slug as legacy_slug')
            ->get();

        foreach ($users as $user) {
            $targetSlug = $legacyRoleMap[$user->legacy_slug] ?? ($user->id === 1 ? 'super_admin' : 'viewer');
            $role = $roles->get($targetSlug);

            if (! $role) {
                continue;
            }

            DB::table('user_roles')->updateOrInsert([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ], []);

            DB::table('users')
                ->where('id', $user->id)
                ->update(['role_id' => $role->id]);
        }

        DB::table('roles')
            ->whereNotIn('slug', collect(config('erp.roles', []))->pluck('slug')->all())
            ->delete();
    }
}
