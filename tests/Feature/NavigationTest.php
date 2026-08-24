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
        ->assertSee('Dashboard')
        ->assertSee('Pelanggan & Layanan')
        ->assertSee('Pelanggan')
        ->assertSee('Data Registrasi Billing')
        ->assertSee('Paket Layanan')
        ->assertSee('Profil Bandwidth')
        ->assertSee('Keuangan & Billing')
        ->assertSee('Tagihan (Invoice)')
        ->assertSee('Riwayat Pembayaran')
        ->assertSee('Promo & Diskon')
        ->assertSee('Laporan Keuangan')
        ->assertSee('Jaringan & Infrastruktur')
        ->assertSee('Router')
        ->assertSee('IP Pool')
        ->assertSee('Area & Wilayah')
        ->assertSee('Kota')
        ->assertSee('Administrasi')
        ->assertSee('Pengguna')
        ->assertSee('Peran');
});

test('user without permissions only sees dashboard', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    // No role assigned

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee('Pelanggan & Layanan')
        ->assertDontSee('Data Registrasi Billing')
        ->assertDontSee('Profil Bandwidth')
        ->assertDontSee('Keuangan & Billing')
        ->assertDontSee('Tagihan (Invoice)')
        ->assertDontSee('Jaringan & Infrastruktur')
        ->assertDontSee('Area & Wilayah')
        ->assertDontSee('Administrasi');
});

test('user with pengguna.lihat permission sees pengguna menu item', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->givePermissionTo('pengguna.lihat');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Administrasi')
        ->assertSee('Pengguna')
        ->assertDontSee('Pelanggan & Layanan')
        ->assertDontSee('Peran (Role)');
});

test('super_admin sees contextual module navigation in sidebar when visiting a module page', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->get(route('pelanggan.index'));

    $response->assertOk()
        ->assertSee('Pelanggan')
        ->assertSee('Data Registrasi Billing')
        ->assertSee('Paket Layanan')
        ->assertSee('Profil Bandwidth');
});
