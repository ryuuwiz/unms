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

        // Create or retrieve roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $salesRole = Role::firstOrCreate(['name' => 'sales']);
        $nocRole = Role::firstOrCreate(['name' => 'noc']);
        $teknisiRole = Role::firstOrCreate(['name' => 'teknisi']);

        // super_admin gets all permissions
        $superAdmin->syncPermissions([
            $viewUsers, $manageUsers, $manageRoles,
            $viewCustomers, $manageCustomers,
            $viewPackages, $managePackages,
        ]);

        // admin gets user and customer management permissions
        $adminRole->syncPermissions([
            $viewUsers, $manageUsers,
            $viewCustomers, $manageCustomers,
            $viewPackages, $managePackages,
        ]);

        // sales can create and view customers
        $salesRole->syncPermissions([
            $viewCustomers, $manageCustomers,
        ]);

        // noc & teknisi are view-only for customers
        $nocRole->syncPermissions([
            $viewCustomers, $viewPackages,
        ]);
        $teknisiRole->syncPermissions([
            $viewCustomers, $viewPackages,
        ]);
        $staffRole->syncPermissions([
            $viewCustomers,
        ]);

        // Create default users for each role if they don't exist yet
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
            ]
        );
        $superAdminUser->syncRoles([$superAdmin]);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Staff',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
            ]
        );
        $adminUser->syncRoles([$adminRole]);

        $salesUser = User::firstOrCreate(
            ['email' => 'sales@example.com'],
            [
                'name' => 'Rian Hidayat (Sales)',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
            ]
        );
        $salesUser->syncRoles([$salesRole]);

        $teknisiUser = User::firstOrCreate(
            ['email' => 'teknisi@example.com'],
            [
                'name' => 'Bambang Supriyadi (Teknisi)',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
            ]
        );
        $teknisiUser->syncRoles([$teknisiRole]);

        $nocUser = User::firstOrCreate(
            ['email' => 'noc@example.com'],
            [
                'name' => 'Fajar Pratama (NOC)',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
            ]
        );
        $nocUser->syncRoles([$nocRole]);
    }
}
