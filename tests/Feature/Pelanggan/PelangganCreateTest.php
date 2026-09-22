<?php

use App\Enums\StatusPelanggan;
use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Create;
use App\Models\Pelanggan;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->salesUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->salesUser->assignRole('sales');

    $this->teknisiUser = User::factory()->create(['status' => UserStatus::Active]);
    $this->teknisiUser->assignRole('teknisi');
});

test('sales user can access create pelanggan page', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->assertOk();
});

test('teknisi without pelanggan.buat cannot access create pelanggan page', function () {
    $this->actingAs($this->teknisiUser)
        ->get(route('pelanggan.create'))
        ->assertForbidden();
});

test('can create a pelanggan with valid data, normalized phone, and sequential no_reg', function () {
    $prefix = PengaturanPrefixRegistrasi::factory()->create(['kode' => 'BF']);

    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('prefix_registrasi_id', $prefix->id)
        ->set('nama_depan', 'Ahmad')
        ->set('nama_belakang', 'Dahlan')
        ->set('email', 'ahmad@example.com')
        ->set('no_hp', '081234567890')
        ->set('alamat_lengkap', 'Jl. Sudirman Kav. 5, Jakarta')
        ->set('latitude', -6.2088)
        ->set('longitude', 106.8456)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pelanggan.index'));

    $pelanggan = Pelanggan::where('nama_depan', 'Ahmad')->first();

    $expectedNoReg = 'BF'.now()->format('dmY').'01';
    expect($pelanggan)->not->toBeNull()
        ->and($pelanggan->no_reg)->toBe($expectedNoReg)
        ->and($pelanggan->identitasLengkap())->toBe("{$expectedNoReg}_Ahmad Dahlan")
        ->and($pelanggan->no_hp)->toBe('6281234567890')
        ->and($pelanggan->status)->toBe(StatusPelanggan::BelumTerpasang)
        ->and($pelanggan->dibuat_oleh)->toBe($this->salesUser->id)
        ->and($pelanggan->latitude)->toBe(-6.2088)
        ->and($pelanggan->longitude)->toBe(106.8456)
        ->and($pelanggan->kode_pembayaran)->not->toBeEmpty();

    expect(Activity::where('subject_type', Pelanggan::class)->where('subject_id', $pelanggan->id)->exists())->toBeTrue();
});

test('can create a pelanggan with custom unique no_reg', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('no_reg', 'ARS2309202601')
        ->set('nama_depan', 'Siti')
        ->set('nama_belakang', 'Aminah')
        ->set('email', 'siti@example.com')
        ->set('no_hp', '081234567891')
        ->set('alamat_lengkap', 'Jl. Thamrin No. 10, Jakarta')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('pelanggan.index'));

    $pelanggan = Pelanggan::where('email', 'siti@example.com')->first();
    expect($pelanggan)->not->toBeNull()
        ->and($pelanggan->no_reg)->toBe('ARS2309202601')
        ->and($pelanggan->identitasLengkap())->toBe('ARS2309202601_Siti Aminah');
});

test('rejects duplicate no_reg', function () {
    Pelanggan::factory()->create(['no_reg' => 'WG2309202601']);

    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('no_reg', 'WG2309202601')
        ->set('nama_depan', 'Budi')
        ->set('no_hp', '081234567892')
        ->set('alamat_lengkap', 'Jl. Melati')
        ->call('save')
        ->assertHasErrors(['no_reg' => 'unique']);
});

test('rejects invalid phone numbers', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('nama_depan', 'Budi')
        ->set('no_hp', '12345')
        ->set('alamat_lengkap', 'Jl. Test')
        ->call('save')
        ->assertHasErrors(['no_hp' => 'regex']);
});

test('requires prefix selection when no_reg is left blank', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('nama_depan', 'Budi')
        ->set('no_hp', '081234567893')
        ->set('alamat_lengkap', 'Jl. Test')
        ->call('save')
        ->assertHasErrors(['prefix_registrasi_id' => 'required']);
});

test('requires mandatory fields', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('nama_depan', '')
        ->set('no_hp', '')
        ->set('alamat_lengkap', '')
        ->call('save')
        ->assertHasErrors(['nama_depan', 'no_hp', 'alamat_lengkap']);
});
