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
        // 1. Run Shield Seeder first to generate roles and permissions
        $this->call(ShieldSeeder::class);

        // 2. Create or retrieve the Super Admin User
        $user = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'), // Change this in production
                'email_verified_at' => now(),
            ]
        );

        // 3. Assign the super_admin role
        $superAdminRoleName = config('filament-shield.super_admin.name', 'super_admin');
        
        // The role should have been created by ShieldSeeder, but we can ensure it exists
        $role = Role::firstOrCreate(['name' => $superAdminRoleName, 'guard_name' => 'web']);
        
        $user->assignRole($role);

        // Optional: Keep the test user if needed
        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );
        // $testUser->assignRole($role); // Uncomment if test user should also be super admin
    }
}
