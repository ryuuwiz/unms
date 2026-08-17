<?php

use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Show;
use App\Models\Pelanggan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->pelanggan = Pelanggan::factory()->create([
        'nama_depan' => 'Budi',
        'nama_belakang' => 'Santoso',
        'no_hp' => '628123456789',
        'alamat_lengkap' => 'Jl. Merdeka No. 1, Jakarta',
    ]);
});

test('can render pelanggan detail page with info and tabs', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('628123456789')
        ->assertSee('Jl. Merdeka No. 1, Jakarta');
});

test('renders leaflet map and coordinate details when coordinates exist', function () {
    $pelangganWithCoords = Pelanggan::factory()->create([
        'latitude' => -6.2088000,
        'longitude' => 106.8456000,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $pelangganWithCoords])
        ->assertOk()
        ->assertSee('Titik Koordinat Lokasi')
        ->assertSee('Terpetakan')
        ->assertSee('-6.2088000')
        ->assertSee('106.8456000')
        ->assertSee('Buka di Google Maps')
        ->assertSee('Buka di OpenStreetMap');
});

test('renders empty state when coordinates are not set', function () {
    $pelangganWithoutCoords = Pelanggan::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $pelangganWithoutCoords])
        ->assertOk()
        ->assertSee('Titik Koordinat Lokasi')
        ->assertSee('Koordinat belum ditentukan')
        ->assertSee('Atur Titik Koordinat');
});

test('can switch to audit tab and render activity logs', function () {
    activity()
        ->performedOn($this->pelanggan)
        ->causedBy($this->superAdmin)
        ->log('Memperbarui data pelanggan');

    Livewire::actingAs($this->superAdmin)
        ->test(Show::class, ['pelanggan' => $this->pelanggan])
        ->call('setTab', 'audit')
        ->assertSet('activeTab', 'audit')
        ->assertSee('Log Aktivitas Data Pelanggan')
        ->assertSee('Memperbarui data pelanggan');
});
