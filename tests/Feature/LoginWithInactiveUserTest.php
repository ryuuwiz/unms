<?php

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('inactive user cannot login and receives specific error message', function () {
    $user = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => bcrypt('password'),
        'status' => UserStatus::Inactive,
    ]);

    $this->post('/login', [
        'email' => 'inactive@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors(['email']);

    $errors = session('errors');
    expect($errors->first('email'))->toContain('dinonaktifkan');
});

test('active user can login successfully', function () {
    $user = User::factory()->create([
        'email' => 'active@example.com',
        'password' => bcrypt('password'),
        'status' => UserStatus::Active,
    ]);

    $this->post('/login', [
        'email' => 'active@example.com',
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('last_login_at is recorded on successful login', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'status' => UserStatus::Active,
        'last_login_at' => null,
    ]);

    $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ]);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});
