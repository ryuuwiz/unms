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
        ->assertSeeInOrder([
            'Pelanggan', 'Data Pelanggan', 'Data Registrasi Billing',
            'Keuangan', 'Tagihan (Invoice)', 'Riwayat Pembayaran', 'Laporan Keuangan',
            'Tiket', 'Daftar Tiket',
            'Jaringan', 'Router', 'IP Pool',
            'Inventaris', 'Data Barang',
            'WhatsApp Blast', 'Antrian Blast',
            'Pengaturan Layanan &amp; Tagihan', 'Paket Layanan', 'Profil Bandwidth', 'Promo &amp; Diskon',
            'Pengaturan Umum', 'Kota', 'Koneksi WhatsApp',
            'Administrasi', 'Pengguna', 'Peran',
        ], escape: false);
});

test('user without permissions only sees dashboard', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    // No role assigned

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee('Data Pelanggan')
        ->assertDontSee('Data Registrasi Billing')
        ->assertDontSee('Profil Bandwidth')
        ->assertDontSee('Tagihan (Invoice)')
        ->assertDontSee('Pengaturan Umum')
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
        ->assertDontSee('Data Pelanggan')
        ->assertDontSee('Peran (Role)');
});

test('super_admin sees contextual module navigation in sidebar when visiting a module page', function () {
    $superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->get(route('pelanggan.index'));

    $response->assertOk()
        ->assertSee('Data Pelanggan')
        ->assertSee('Data Registrasi Billing')
        ->assertSee('Paket Layanan')
        ->assertSee('Profil Bandwidth');
});

test('pengaturan payment gateway ada di grup Pengaturan Layanan & Tagihan, terpisah dari administrasi', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);
    $user->givePermissionTo('payment_gateway.lihat');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Pengaturan Layanan &amp; Tagihan', 'Koneksi Gateway', 'Channel Pembayaran', 'Template Deskripsi Tagihan'], escape: false)
        ->assertDontSee('Administrasi');
});

test('teknisi hanya melihat grup area kerjanya: Pelanggan, Tiket, dan Inventaris', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');

    $this->actingAs($teknisi)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Data Pelanggan', 'Maps Lokasi', 'Estimasi Kabel', 'Daftar Tiket', 'Data Barang'])
        ->assertDontSee('Tagihan (Invoice)')
        ->assertDontSee('Jaringan')
        ->assertDontSee('WhatsApp Blast')
        ->assertDontSee('Pengaturan Layanan')
        ->assertDontSee('Pengaturan Umum')
        ->assertDontSee('Administrasi');
});
