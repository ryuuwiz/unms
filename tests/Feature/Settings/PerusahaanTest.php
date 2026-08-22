<?php

use App\Enums\UserStatus;
use App\Livewire\Settings\Perusahaan as PerusahaanComponent;
use App\Models\Perusahaan;
use App\Models\User;
use Database\Seeders\PerusahaanSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        PerusahaanSeeder::class,
    ]);

    $this->superAdmin = User::factory()->create(['status' => UserStatus::Active]);
    $this->superAdmin->assignRole('super_admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisi->assignRole('teknisi');
});

test('tamu tidak dapat mengakses halaman pengaturan perusahaan', function () {
    $this->get(route('settings.perusahaan'))
        ->assertRedirect(route('login'));
});

test('non-super_admin dilarang mengakses halaman pengaturan perusahaan', function () {
    $this->actingAs($this->teknisi)
        ->get(route('settings.perusahaan'))
        ->assertForbidden();
});

test('super_admin dapat melihat data profil perusahaan yang telah tersimpan', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(PerusahaanComponent::class)
        ->assertOk()
        ->assertSet('nama_perusahaan', 'PT GOBILLING NUSANTARA TEKNOLOGI')
        ->assertSet('nama_brand', 'GOBILLING')
        ->assertSee('PT GOBILLING NUSANTARA TEKNOLOGI');
});

test('super_admin dapat memperbarui informasi profil perusahaan', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(PerusahaanComponent::class)
        ->set('nama_perusahaan', 'PT GOBILLING DIGITAL INDONESIA')
        ->set('nama_brand', 'GOBILLING NET')
        ->set('tagline', 'Internet Super Cepat & Stabil')
        ->set('alamat', 'Cyber Building Lt. 5, Jakarta')
        ->set('telepon', '021-9998887')
        ->set('whatsapp', '0811-2233-4455')
        ->set('email', 'corporate@gobilling.id')
        ->set('nama_bank', 'Bank Mandiri')
        ->set('nomor_rekening', '1230009988771')
        ->set('atas_nama', 'PT GOBILLING DIGITAL')
        ->set('catatan_invoice', 'Harap transfer sesuai nominal.')
        ->call('save')
        ->assertHasNoErrors();

    $company = Perusahaan::default();
    expect($company->nama_perusahaan)->toBe('PT GOBILLING DIGITAL INDONESIA')
        ->and($company->nama_brand)->toBe('GOBILLING NET')
        ->and($company->tagline)->toBe('Internet Super Cepat & Stabil')
        ->and($company->alamat)->toBe('Cyber Building Lt. 5, Jakarta')
        ->and($company->telepon)->toBe('021-9998887')
        ->and($company->whatsapp)->toBe('0811-2233-4455')
        ->and($company->email)->toBe('corporate@gobilling.id')
        ->and($company->nama_bank)->toBe('Bank Mandiri')
        ->and($company->nomor_rekening)->toBe('1230009988771')
        ->and($company->atas_nama)->toBe('PT GOBILLING DIGITAL')
        ->and($company->catatan_invoice)->toBe('Harap transfer sesuai nominal.');
});

test('super_admin dapat mengunggah dan menghapus logo perusahaan via spatie medialibrary', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('company_logo.png', 400, 150);

    Livewire::actingAs($this->superAdmin)
        ->test(PerusahaanComponent::class)
        ->set('logo', $file)
        ->call('save')
        ->assertHasNoErrors();

    $company = Perusahaan::default();
    expect($company->hasMedia('logo'))->toBeTrue()
        ->and($company->logo_url)->not->toBeNull()
        ->and($company->logo_base64)->not->toBeNull();

    // Test Hapus Logo
    Livewire::actingAs($this->superAdmin)
        ->test(PerusahaanComponent::class)
        ->call('hapusLogo')
        ->assertHasNoErrors();

    $companyFresh = Perusahaan::default();
    expect($companyFresh->hasMedia('logo'))->toBeFalse()
        ->and($companyFresh->logo_url)->toBeNull()
        ->and($companyFresh->logo_base64)->toBeNull();
});

test('perusahaan default menyediakan fallback aman saat data kosong', function () {
    Perusahaan::query()->delete();
    cache()->forget(Perusahaan::CACHE_KEY);

    $fallback = Perusahaan::default();
    expect($fallback)->toBeInstanceOf(Perusahaan::class)
        ->and($fallback->nama_perusahaan)->not->toBeEmpty();
});
