<?php

use App\Enums\UserStatus;
use App\Livewire\Users\Create;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super_admin can create a new user with a role', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('name', 'Budi Santoso')
        ->set('email', 'budi@example.com')
        ->set('phone', '081234567890')
        ->set('role', 'noc')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'budi@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Budi Santoso')
        ->and($user->phone)->toBe('081234567890')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->hasRole('noc'))->toBeTrue();
});

test('create user validates required fields', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->call('save')
        ->assertHasErrors(['name', 'email', 'role', 'password']);
});

test('create user validates unique email', function () {
    $admin = User::factory()->create(['status' => UserStatus::Active]);
    $admin->assignRole('super_admin');

    User::factory()->create(['email' => 'existing@example.com']);

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('name', 'Test')
        ->set('email', 'existing@example.com')
        ->set('role', 'noc')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['email']);
});
