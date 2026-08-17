<?php

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super_admin sees all navigation groups and items in indonesian without repo or docs links', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Menu Utama')
        ->assertSee('Dashboard')
        ->assertSee('Pelanggan')
        ->assertSee('Paket Internet')
        ->assertSee('Pengguna')
        ->assertSee('Administrasi')
        ->assertSee('Peran')
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation');
});

test('regular user without permissions only sees menu utama and dashboard', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->assignRole('staff');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Menu Utama')
        ->assertSee('Dashboard')
        ->assertDontSee('Pengguna')
        ->assertDontSee('Administrasi')
        ->assertDontSee('Peran')
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation');
});

test('user with manage_users permission sees pengguna menu item', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->givePermissionTo('manage_users');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Menu Utama')
        ->assertSee('Dashboard')
        ->assertSee('Pengguna')
        ->assertDontSee('Administrasi')
        ->assertDontSee('Peran');
});
