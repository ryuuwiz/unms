<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Users module permissions
        $viewUsers = Permission::firstOrCreate(['name' => 'view_users']);
        $manageUsers = Permission::firstOrCreate(['name' => 'manage_users']);
        $manageRoles = Permission::firstOrCreate(['name' => 'manage_roles']);

        // Customers module permissions
        $viewCustomers = Permission::firstOrCreate(['name' => 'view_customers']);
        $manageCustomers = Permission::firstOrCreate(['name' => 'manage_customers']);

        // Packages module permissions
        $viewPackages = Permission::firstOrCreate(['name' => 'view_packages']);
        $managePackages = Permission::firstOrCreate(['name' => 'manage_packages']);

        // Create the roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'staff']);
        Role::firstOrCreate(['name' => 'sales']);
        Role::firstOrCreate(['name' => 'noc']);
        Role::firstOrCreate(['name' => 'teknisi']);

        // super_admin gets all permissions
        $superAdmin->syncPermissions([
            $viewUsers, $manageUsers, $manageRoles,
            $viewCustomers, $manageCustomers,
            $viewPackages, $managePackages,
        ]);

        // Create a default super_admin user if it doesn't exist yet
        $admin = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
            ]
        );

        $admin->assignRole($superAdmin);
    }
}
