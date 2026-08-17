<?php

use App\Enums\StatusPelanggan;
use App\Enums\UserStatus;
use App\Livewire\Pelanggan\Create;
use App\Models\Pelanggan;
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
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
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

    $expectedPrefix = 'REG-'.now()->year.'-000001';
    expect($pelanggan)->not->toBeNull()
        ->and($pelanggan->no_reg)->toBe($expectedPrefix)
        ->and($pelanggan->no_hp)->toBe('6281234567890')
        ->and($pelanggan->status)->toBe(StatusPelanggan::Prospek)
        ->and($pelanggan->dibuat_oleh)->toBe($this->salesUser->id)
        ->and($pelanggan->latitude)->toBe(-6.2088)
        ->and($pelanggan->longitude)->toBe(106.8456)
        ->and($pelanggan->kode_pembayaran)->not->toBeEmpty();

    expect(Activity::where('subject_type', Pelanggan::class)->where('subject_id', $pelanggan->id)->exists())->toBeTrue();
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

test('requires mandatory fields', function () {
    Livewire::actingAs($this->salesUser)
        ->test(Create::class)
        ->set('nama_depan', '')
        ->set('no_hp', '')
        ->set('alamat_lengkap', '')
        ->call('save')
        ->assertHasErrors(['nama_depan', 'no_hp', 'alamat_lengkap']);
});
