<?php

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super_admin sees all navigation groups and items in indonesian', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Menu Utama')
        ->assertSee('Dashboard')
        ->assertSee('Pelanggan')
        ->assertSee('Layanan Pelanggan')
        ->assertSee('Paket Layanan')
        ->assertSee('Profil Bandwidth')
        ->assertSee('Jaringan & Infrastruktur')
        ->assertSee('Router')
        ->assertSee('IP Pool')
        ->assertSee('Area & Wilayah')
        ->assertSee('Kota')
        ->assertSee('Administrasi')
        ->assertSee('Pengguna')
        ->assertSee('Peran');
});

test('user without permissions only sees menu utama and dashboard', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    // No role assigned

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Menu Utama')
        ->assertSee('Dashboard')
        ->assertDontSee('Layanan Pelanggan')
        ->assertDontSee('Profil Bandwidth')
        ->assertDontSee('Administrasi');
});

test('user with pengguna.lihat permission sees pengguna menu item', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->givePermissionTo('pengguna.lihat');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Menu Utama')
        ->assertSee('Dashboard')
        ->assertSee('Pengguna')
        ->assertDontSee('Peran');
});
