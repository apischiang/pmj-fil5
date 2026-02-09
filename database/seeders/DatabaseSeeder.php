<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'), // Default password if creating new
            ]
        );

        // Ensure the super_admin role exists
        $superAdminRoleName = config('filament-shield.super_admin.name', 'super_admin');
        $role = Role::firstOrCreate(['name' => $superAdminRoleName, 'guard_name' => 'web']);
        
        // Assign the role to the user
        $user->assignRole($role);

        // Clear permission cache to ensure everything is up to date
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
