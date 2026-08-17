<?php

use App\Enums\UserStatus;
use App\Livewire\Wilayah\Kota\Create;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
use App\Models\Perumahan;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');
});

test('super admin can create kota', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(Create::class)
        ->set('nama_kota', 'Kota Bandung')
        ->set('keterangan', 'Ibu kota Provinsi Jawa Barat')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('wilayah.kota.index'));

    expect(Kota::where('nama_kota', 'Kota Bandung')->exists())->toBeTrue();
});

test('hierarchical relationship between kota, kecamatan, kelurahan, and perumahan works', function () {
    $kota = Kota::create(['nama_kota' => 'Kota Jakarta Selatan']);
    $kecamatan = Kecamatan::create(['kota_id' => $kota->id, 'nama_kecamatan' => 'Kebayoran Baru']);
    $kelurahan = Kelurahan::create(['kecamatan_id' => $kecamatan->id, 'nama_kelurahan' => 'Senayan']);
    $perumahan = Perumahan::create(['kelurahan_id' => $kelurahan->id, 'nama_perumahan' => 'Komp. Senayan Residence']);

    expect($perumahan->kelurahan->kecamatan->kota->nama_kota)->toBe('Kota Jakarta Selatan')
        ->and($kota->kecamatans)->toHaveCount(1)
        ->and($kecamatan->kelurahans)->toHaveCount(1)
        ->and($kelurahan->perumahans)->toHaveCount(1);
});
