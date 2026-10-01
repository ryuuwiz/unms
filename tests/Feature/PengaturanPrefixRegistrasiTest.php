<?php

use App\Enums\UserStatus;
use App\Livewire\Settings\PengaturanPrefixRegistrasi;
use App\Models\PengaturanPrefixRegistrasi as PengaturanPrefixRegistrasiModel;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

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

test('admin dapat mengunggah dan menghapus logo brand prefix', function () {
    Storage::fake('public');
    $prefix = PengaturanPrefixRegistrasiModel::factory()->create(['kode' => 'BF']);

    $component = Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->call('openEditModal', $prefix->id)
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('simpan')
        ->assertHasNoErrors();

    expect($prefix->fresh()->logo_base64)->toStartWith('data:image/png;base64,');

    $component->call('openEditModal', $prefix->id)->call('hapusLogo');

    expect($prefix->fresh()->hasMedia('logo'))->toBeFalse();
});

test('admin dapat mengatur identitas Aplikasi Pelanggan prefix: nama pendek, warna utama, dan ikon', function () {
    Storage::fake('public');
    $prefix = PengaturanPrefixRegistrasiModel::factory()->create(['kode' => 'WIFI', 'nama' => 'WIFIGO']);

    Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->call('openEditModal', $prefix->id)
        ->set('nama_pendek', 'WIFIGO')
        ->set('warna_utama', '#FF6600')
        ->set('ikon_aplikasi', UploadedFile::fake()->image('ikon.png', 512, 512))
        ->call('simpan')
        ->assertHasNoErrors();

    $prefix->refresh();
    expect($prefix->nama_pendek)->toBe('WIFIGO')
        ->and($prefix->warna_utama)->toBe('#ff6600')
        ->and($prefix->hasMedia('ikon_aplikasi'))->toBeTrue()
        ->and(Activity::where('subject_id', $prefix->id)->where('subject_type', $prefix->getMorphClass())->latest('id')->first()->attribute_changes['attributes'])
        ->toMatchArray(['nama_pendek' => 'WIFIGO', 'warna_utama' => '#ff6600']);
});

test('identitas Aplikasi Pelanggan prefix divalidasi', function (string $field, mixed $nilai) {
    Storage::fake('public');
    $prefix = PengaturanPrefixRegistrasiModel::factory()->create(['kode' => 'WIFI']);

    Livewire::actingAs($this->admin)
        ->test(PengaturanPrefixRegistrasi::class)
        ->call('openEditModal', $prefix->id)
        ->set($field, $nilai)
        ->call('simpan')
        ->assertHasErrors([$field]);
})->with([
    'nama pendek terlalu panjang' => ['nama_pendek', 'NAMA SANGAT PANJANG'],
    'warna bukan hex' => ['warna_utama', 'orange'],
    'ikon tidak persegi' => fn () => ['ikon_aplikasi', UploadedFile::fake()->image('ikon.png', 800, 400)],
    'ikon terlalu kecil' => fn () => ['ikon_aplikasi', UploadedFile::fake()->image('ikon.png', 256, 256)],
]);
