<?php

use App\Enums\UserStatus;
use App\Livewire\Promo\Create;
use App\Livewire\Promo\Edit;
use App\Livewire\Promo\Index;
use App\Models\Promo;
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

test('user with promo.lihat can view promo index', function () {
    Promo::factory()->create(['nama_promo' => 'Promo Diskon Merdeka']);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->assertOk()
        ->assertSee('Promo Diskon Merdeka');
});

test('admin can create a new promo discount', function () {
    Livewire::actingAs($this->adminUser)
        ->test(Create::class)
        ->set('kode_promo', 'DISKONHEMAT')
        ->set('nama_promo', 'Promo Diskon Hemat 25rb')
        ->set('jenis', 'diskon')
        ->set('diskon_tipe', 'nominal')
        ->set('diskon_nilai', 25000)
        ->set('minimal_nominal_invoice', 100000)
        ->set('kuota_global', 50)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('promo.index'));

    $promo = Promo::where('kode_promo', 'DISKONHEMAT')->first();
    expect($promo)->not->toBeNull()
        ->and((float) $promo->diskon_nilai)->toBe(25000.0)
        ->and($promo->kuota_global)->toBe(50);
});

test('admin can edit existing promo', function () {
    $promo = Promo::factory()->create([
        'kode_promo' => 'AWALPROMO',
        'nama_promo' => 'Promo Awal',
        'diskon_nilai' => 10000,
    ]);

    Livewire::actingAs($this->adminUser)
        ->test(Edit::class, ['promo' => $promo])
        ->set('nama_promo', 'Promo Awal Diupdate')
        ->set('diskon_nilai', 15000)
        ->call('save')
        ->assertHasNoErrors();

    expect($promo->fresh()->nama_promo)->toBe('Promo Awal Diupdate')
        ->and((float) $promo->fresh()->diskon_nilai)->toBe(15000.0);
});

test('admin can toggle active status of promo', function () {
    $promo = Promo::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->adminUser)
        ->test(Index::class)
        ->call('toggleStatus', $promo->id);

    expect($promo->fresh()->aktif)->toBeFalse();
});
