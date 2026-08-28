<?php

use App\Enums\Sysblas\SysblasProvider;
use App\Livewire\Sysblas\Koneksi\Index;
use App\Models\Sysblas;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SysblasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        SysblasSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

test('halaman koneksi api sysblas dapat diakses staf berwenang', function () {
    $this->actingAs($this->admin)
        ->get(route('sysblas.koneksi.index'))
        ->assertOk()
        ->assertSee('SysBlast - Koneksi API Gateway');
});

test('staf dapat menambah koneksi sysblas baru', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('nama', 'WABLAS CS 2')
        ->set('provider', SysblasProvider::Wablas->value)
        ->set('nomor', '081234567899')
        ->set('url_api', 'https://tegal.wablas.com')
        ->set('api_token', 'token_cs2_secret')
        ->set('api_secret', 'secret_cs2')
        ->set('limit_per_menit', 30)
        ->set('is_default', false)
        ->set('is_aktif', true)
        ->set('keterangan', 'Koneksi khusus chat CS')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Sysblas::where('nama', 'WABLAS CS 2')->exists())->toBeTrue()
        ->and(Sysblas::where('nama', 'WABLAS CS 2')->first()->limit_per_menit)->toBe(30);
});

test('staf dapat memperbarui koneksi sysblas', function () {
    $sysblas = Sysblas::first();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openEditModal', $sysblas->id)
        ->set('nama', 'WABLAS Utama Updated')
        ->set('limit_per_menit', 45)
        ->call('simpan')
        ->assertHasNoErrors();

    expect($sysblas->fresh()->nama)->toBe('WABLAS Utama Updated')
        ->and($sysblas->fresh()->limit_per_menit)->toBe(45);
});

test('staf dapat menyetel koneksi sebagai default tunggal', function () {
    $first = Sysblas::first();
    $second = Sysblas::factory()->create(['is_default' => false]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('setAsDefault', $second->id);

    expect($second->fresh()->is_default)->toBeTrue()
        ->and($first->fresh()->is_default)->toBeFalse();
});

test('staf dapat melakukan ping tes status koneksi gateway', function () {
    Http::fake([
        'https://tegal.wablas.com/api/device/info*' => Http::response([
            'status' => true,
            'message' => 'Device terhubung',
            'data' => [
                'phone' => '628970919525',
                'status' => 'connected',
                'quota' => 2500,
                'active_period' => '2027-01-01',
            ],
        ], 200),
    ]);

    $sysblas = Sysblas::first();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openPingModal', $sysblas->id)
        ->assertSet('pingResult.connected', true)
        ->assertSet('pingResult.quota', 2500);
});

test('staf dapat mengirim pesan uji coba dari modal tes pesan', function () {
    Http::fake([
        'https://tegal.wablas.com/api/v2/send-message*' => Http::response([
            'status' => true,
            'message' => 'Pesan berhasil',
        ], 200),
    ]);

    $sysblas = Sysblas::first();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openTestSendModal', $sysblas->id)
        ->set('testPhone', '081234567890')
        ->set('testMessage', 'Pesan Uji Coba SysBlast')
        ->call('kirimPesanTest')
        ->assertHasNoErrors();
});

test('staf dapat menghapus koneksi non-default', function () {
    $nonDefault = Sysblas::factory()->create(['is_default' => false]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('hapus', $nonDefault->id);

    expect(Sysblas::find($nonDefault->id))->toBeNull();
});
