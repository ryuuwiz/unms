<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DevUsersSeeder extends Seeder
{
    /**
     * Run the database seeds for development test accounts.
     */
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $salesRole = Role::firstOrCreate(['name' => 'sales']);
        $nocRole = Role::firstOrCreate(['name' => 'noc']);
        $teknisiRole = Role::firstOrCreate(['name' => 'teknisi']);

        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        $superAdminUser->syncRoles([$superAdmin]);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Staff',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        $adminUser->syncRoles([$adminRole]);

        $salesUser = User::firstOrCreate(
            ['email' => 'sales@example.com'],
            [
                'name' => 'Rian Hidayat (Sales)',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        $salesUser->syncRoles([$salesRole]);

        $teknisiUser = User::firstOrCreate(
            ['email' => 'teknisi@example.com'],
            [
                'name' => 'Bambang Supriyadi (Teknisi)',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        $teknisiUser->syncRoles([$teknisiRole]);

        $nocUser = User::firstOrCreate(
            ['email' => 'noc@example.com'],
            [
                'name' => 'Fajar Pratama (NOC)',
                'password' => bcrypt('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        $nocUser->syncRoles([$nocRole]);
    }
}
