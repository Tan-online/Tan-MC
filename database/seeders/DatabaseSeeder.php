<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(EnterpriseRbacSeeder::class);

        User::factory()->create([
            'name' => 'Test User',
            'employee_code' => '000001',
            'email' => 'test@example.com',
            'role_id' => \App\Models\Role::query()->where('slug', 'super_admin')->value('id'),
        ]);

        $user = User::query()->where('employee_code', '000001')->first();

        if ($user) {
            $user->syncRoles([
                \App\Models\Role::query()->where('slug', 'super_admin')->value('id'),
            ]);
        }
    }
}
