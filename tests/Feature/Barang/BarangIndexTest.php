<?php

use App\Enums\UserStatus;
use App\Livewire\Barang\Index;
use App\Models\Barang;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->adminUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->adminUser->assignRole('admin');
});

test('user with barang.lihat permission can see the barang list', function () {
    $barang = Barang::factory()->create(['nama_barang' => 'Modem ZTE F609']);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->assertOk()
        ->assertSee($barang->kode_barang)
        ->assertSee('Modem ZTE F609');
});

test('search filters barang by nama or kode', function () {
    $modem = Barang::factory()->create(['nama_barang' => 'Modem ZTE F609']);
    $kabel = Barang::factory()->create(['nama_barang' => 'Kabel FO 100m']);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->set('search', 'Modem')
        ->assertSee($modem->nama_barang)
        ->assertDontSee($kabel->nama_barang);
});

test('stok range filter only shows barang within the range', function () {
    $low = Barang::factory()->create(['nama_barang' => 'Barang Stok Rendah', 'stok' => 2]);
    $high = Barang::factory()->create(['nama_barang' => 'Barang Stok Tinggi', 'stok' => 50]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->set('stokMin', '10')
        ->assertSee($high->nama_barang)
        ->assertDontSee($low->nama_barang);
});

test('stok menipis toggle only shows barang below the low-stock threshold', function () {
    $low = Barang::factory()->create(['nama_barang' => 'Barang Stok Rendah', 'stok' => 1]);
    $high = Barang::factory()->create(['nama_barang' => 'Barang Stok Tinggi', 'stok' => 50]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->set('stokMenipis', true)
        ->assertSee($low->nama_barang)
        ->assertDontSee($high->nama_barang);
});

test('user without barang.lihat permission is denied access', function () {
    $userTanpaAkses = User::factory()->create(['status' => UserStatus::Active]);

    Livewire::actingAs($userTanpaAkses)
        ->test(Index::class)
        ->assertForbidden();
});
