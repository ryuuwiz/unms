<?php

use App\Enums\UserStatus;
use App\Livewire\Users\Edit;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super_admin can edit a user profile', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole('noc');

    Livewire::actingAs($admin)
        ->test(Edit::class, ['user' => $user])
        ->set('name', 'Updated Name')
        ->set('phone', '089999999')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($user->fresh()->name)->toBe('Updated Name')
        ->and($user->fresh()->phone)->toBe('089999999');
});

test('role change is recorded in audit log', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole('noc');

    Livewire::actingAs($admin)
        ->test(Edit::class, ['user' => $user])
        ->set('role', 'sales')
        ->call('save');

    expect($user->fresh()->hasRole('sales'))->toBeTrue();
    expect(AuditLog::where('action', 'role_changed')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('super_admin cannot deactivate the last active super_admin', function () {
    // Deactivate the default super_admin created by the seeder so our test actor is the only one
    User::where('email', 'superadmin@example.com')->update(['status' => UserStatus::Inactive]);

    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    // admin is now the only active super_admin — cannot deactivate
    Livewire::actingAs($admin)
        ->test(Edit::class, ['user' => $admin])
        ->call('toggleStatus')
        ->assertOk();

    expect($admin->fresh()->isActive())->toBeTrue();
});

test('super_admin can deactivate another super_admin when one remains', function () {
    $admin1 = User::factory()->create(['status' => UserStatus::Active]);
    $admin1->assignRole('super_admin');

    $admin2 = User::factory()->create(['status' => UserStatus::Active]);
    $admin2->assignRole('super_admin');

    Livewire::actingAs($admin1)
        ->test(Edit::class, ['user' => $admin2])
        ->call('toggleStatus');

    expect($admin2->fresh()->status)->toBe(UserStatus::Inactive);
});

test('super_admin can deactivate a non-super_admin user', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole('noc');

    Livewire::actingAs($admin)
        ->test(Edit::class, ['user' => $user])
        ->call('toggleStatus');

    expect($user->fresh()->status)->toBe(UserStatus::Inactive);
});
