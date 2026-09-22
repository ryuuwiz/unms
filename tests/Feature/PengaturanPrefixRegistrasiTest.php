<?php

use App\Enums\UserStatus;
use App\Livewire\Settings\PengaturanPrefixRegistrasi;
use App\Models\PengaturanPrefixRegistrasi as PengaturanPrefixRegistrasiModel;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');
});

test('admin dapat menambahkan prefix registrasi baru', function () {
    Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->assertOk()
        ->set('kode', 'bf')
        ->set('nama', 'Bestfiber')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(PengaturanPrefixRegistrasiModel::where('kode', 'BF')->where('nama', 'Bestfiber')->exists())->toBeTrue();
});

test('kode prefix harus 2-5 huruf kapital dan unik', function () {
    PengaturanPrefixRegistrasiModel::factory()->create(['kode' => 'BF']);

    Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->set('kode', 'BF')
        ->set('nama', 'Duplikat')
        ->call('simpan')
        ->assertHasErrors(['kode' => 'unique']);

    Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->set('kode', 'B1')
        ->set('nama', 'Tidak Valid')
        ->call('simpan')
        ->assertHasErrors(['kode' => 'regex']);
});

test('admin dapat menonaktifkan dan mengaktifkan kembali prefix tanpa menghapus data', function () {
    $prefix = PengaturanPrefixRegistrasiModel::factory()->create(['kode' => 'ARS', 'is_active' => true]);

    Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->call('toggleStatus', $prefix->id);

    expect($prefix->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->call('toggleStatus', $prefix->id);

    expect($prefix->fresh()->is_active)->toBeTrue();
});

test('user tanpa permission prefix_registrasi.lihat tidak dapat mengakses halaman', function () {
    $teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $teknisi->assignRole('teknisi');

    $this->actingAs($teknisi)
        ->get(route('settings.prefix-registrasi'))
        ->assertForbidden();
});
