<?php

use App\Livewire\Portal\Auth\GantiPassword;
use App\Livewire\Portal\Auth\KlaimAkun;
use App\Livewire\Portal\Auth\Login;
use App\Livewire\Portal\Dashboard;
use App\Models\AkunPelanggan;
use App\Models\Pelanggan;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->pelanggan = Pelanggan::factory()->create([
        'email' => 'budi@test.com',
        'no_hp' => '081234567890',
    ]);

    $this->akun = $this->pelanggan->akunPelanggan;
    $this->akun->update([
        'password' => Hash::make('password123'),
    ]);
});

test('pelanggan dapat login ke portal dengan email dan password yang valid', function () {
    Livewire::test(Login::class)
        ->set('email', 'budi@test.com')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('portal.dashboard'));

    expect(Auth::guard('pelanggan')->check())->toBeTrue()
        ->and(Auth::guard('pelanggan')->user()->id)->toBe($this->akun->id);
});

test('login gagal jika password salah', function () {
    Livewire::test(Login::class)
        ->set('email', 'budi@test.com')
        ->set('password', 'wrong_password')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(Auth::guard('pelanggan')->check())->toBeFalse();
});

test('pelanggan yang login dapat mengakses dashboard portal', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee($this->pelanggan->nama_depan);
});

test('tamu tidak dapat mengakses dashboard portal', function () {
    $response = $this->get(route('portal.dashboard'));
    $response->assertRedirect(route('portal.login'));
});

test('tamu yang mengakses prefix portal diarahkan ke login portal', function () {
    $this->get('/portal')->assertRedirect(route('portal.login'));
    $this->get(route('portal.index'))->assertRedirect(route('portal.login'));
});

test('pelanggan yang login mengakses prefix portal diarahkan ke dashboard portal', function () {
    $this->actingAs($this->akun, 'pelanggan')
        ->get(route('portal.index'))
        ->assertRedirect(route('portal.dashboard'));
});

test('pelanggan dapat mengganti password dari portal', function () {
    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(GantiPassword::class)
        ->set('current_password', 'password123')
        ->set('password', 'new_secure_password_99')
        ->set('password_confirmation', 'new_secure_password_99')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new_secure_password_99', $this->akun->fresh()->password))->toBeTrue();
});

test('pelanggan dapat melakukan klaim/aktivasi akun dengan no_reg dan no_hp', function () {
    $pelangganBaru = Pelanggan::factory()->create([
        'no_reg' => 'REG-2026-999888',
        'no_hp' => '087712345678',
        'email' => null,
    ]);

    Livewire::test(KlaimAkun::class)
        ->set('no_reg', 'REG-2026-999888')
        ->set('no_hp', '087712345678')
        ->call('verifikasiIdentitas')
        ->assertHasNoErrors()
        ->assertSet('verified', true)
        ->set('email', 'pelanggan_baru@test.com')
        ->set('password', 'password_baru_123')
        ->set('password_confirmation', 'password_baru_123')
        ->call('simpanAkun')
        ->assertHasNoErrors()
        ->assertRedirect(route('portal.login'));

    expect(AkunPelanggan::where('pelanggan_id', $pelangganBaru->id)->exists())->toBeTrue();
});
