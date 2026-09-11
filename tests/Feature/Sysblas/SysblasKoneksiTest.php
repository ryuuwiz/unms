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

test('staf dapat menambah koneksi sysblas baru dengan pengaturan anti-ban', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('nama', 'WAHA CS Anti-Ban')
        ->set('provider', SysblasProvider::Waha->value)
        ->set('nomor', '081234567899')
        ->set('url_api', 'https://waha.gobilling.id')
        ->set('username', 'admin')
        ->set('password', 'secret')
        ->set('limit_per_menit', 20)
        ->set('delay_detik', 4)
        ->set('jitter_detik', 3)
        ->set('is_typing_simulation', true)
        ->set('is_default', false)
        ->set('is_aktif', true)
        ->set('keterangan', 'Koneksi WAHA aman anti-ban')
        ->call('simpan')
        ->assertHasNoErrors();

    $created = Sysblas::where('nama', 'WAHA CS Anti-Ban')->first();
    expect($created)->not->toBeNull()
        ->and($created->limit_per_menit)->toBe(20)
        ->and($created->delay_detik)->toBe(4)
        ->and($created->jitter_detik)->toBe(3)
        ->and($created->is_typing_simulation)->toBeTrue();
});

test('validasi menolak limit per menit di atas 20 sebagai pengaman anti-ban', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('nama', 'WAHA Limit Kebablasan')
        ->set('provider', SysblasProvider::Waha->value)
        ->set('url_api', 'https://waha.gobilling.id')
        ->set('username', 'admin')
        ->set('password', 'secret')
        ->set('limit_per_menit', 21)
        ->call('simpan')
        ->assertHasErrors(['limit_per_menit' => 'max']);

    expect(Sysblas::where('nama', 'WAHA Limit Kebablasan')->exists())->toBeFalse();
});

test('form tambah koneksi baru memakai default batas laju pengiriman dan jeda antar-pesan yang konsisten', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openCreateModal')
        ->assertSet('limit_per_menit', 4)
        ->assertSet('delay_detik', 15);
});

test('staf dapat memperbarui koneksi sysblas dan pengaturan rate limit', function () {
    $sysblas = Sysblas::first();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openEditModal', $sysblas->id)
        ->set('nama', 'WAHA Utama Updated')
        ->set('limit_per_menit', 15)
        ->set('delay_detik', 5)
        ->set('jitter_detik', 2)
        ->set('is_typing_simulation', true)
        ->call('simpan')
        ->assertHasNoErrors();

    expect($sysblas->fresh()->nama)->toBe('WAHA Utama Updated')
        ->and($sysblas->fresh()->limit_per_menit)->toBe(15)
        ->and($sysblas->fresh()->delay_detik)->toBe(5)
        ->and($sysblas->fresh()->jitter_detik)->toBe(2)
        ->and($sysblas->fresh()->is_typing_simulation)->toBeTrue();
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

test('staf dapat membuka dan memperbarui koneksi yang memiliki api_token null', function () {
    $sysblas = Sysblas::create([
        'nama' => 'WAHA Gateway',
        'provider' => SysblasProvider::Waha,
        'nomor' => '081234567890',
        'url_api' => 'https://waha.gobilling.id',
        'username' => 'admin',
        'password' => 'secret123',
        'api_token' => null,
        'api_secret' => null,
        'limit_per_menit' => 4,
        'is_default' => false,
        'is_aktif' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openEditModal', $sysblas->id)
        ->assertSet('api_token', '')
        ->assertSet('username', 'admin')
        ->assertSet('password', 'secret123')
        ->set('nama', 'WAHA Gateway Updated')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($sysblas->fresh()->nama)->toBe('WAHA Gateway Updated')
        ->and($sysblas->fresh()->api_token)->toBeNull();
});

test('staf dapat melakukan ping tes status koneksi gateway', function () {
    $sysblas = Sysblas::create([
        'nama' => 'WAHA Ping Test',
        'provider' => SysblasProvider::Waha,
        'session_name' => 'default',
        'url_api' => 'https://waha.gobilling.id',
        'username' => 'admin',
        'password' => 'pass123',
        'api_token' => 'token_ping',
        'limit_per_menit' => 60,
        'is_default' => true,
        'is_aktif' => true,
    ]);

    Http::fake([
        'https://waha.gobilling.id/api/sessions*' => Http::response([
            [
                'name' => 'default',
                'status' => 'WORKING',
            ],
        ], 200),
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openPingModal', $sysblas->id)
        ->assertSet('pingResult.connected', true)
        ->assertSet('pingResult.quota', 'Unlimited');
});

test('staf dapat mengirim pesan uji coba dari modal tes pesan', function () {
    $sysblas = Sysblas::create([
        'nama' => 'WAHA Send Test',
        'provider' => SysblasProvider::Waha,
        'session_name' => 'default',
        'url_api' => 'https://waha.gobilling.id',
        'username' => 'admin',
        'password' => 'pass123',
        'api_token' => 'token_send',
        'limit_per_menit' => 60,
        'is_default' => false,
        'is_aktif' => true,
    ]);

    Http::fake([
        'https://waha.gobilling.id/api/sendText*' => Http::response([
            'success' => true,
            'status' => 'success',
        ], 200),
    ]);

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

test('staf dapat membuka modal pairing qr code dan memuat qr session waha', function () {
    $sysblas = Sysblas::create([
        'nama' => 'WAHA QR Test',
        'provider' => SysblasProvider::Waha,
        'session_name' => 'billing-cs',
        'url_api' => 'https://waha.gobilling.id',
        'username' => 'admin',
        'password' => 'pass123',
        'limit_per_menit' => 60,
        'is_default' => false,
        'is_aktif' => false,
    ]);

    Http::fake([
        'https://waha.gobilling.id/api/sessions*' => Http::response([
            [
                'name' => 'billing-cs',
                'status' => 'SCAN_QR_CODE',
            ],
        ], 200),
        'https://waha.gobilling.id/api/billing-cs/auth/qr*' => Http::response('fake-png-binary-data', 200, [
            'Content-Type' => 'image/png',
        ]),
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('openQrModal', $sysblas->id)
        ->assertSet('showQrModal', true)
        ->assertSet('qrSessionStatus', 'SCAN_QR_CODE')
        ->assertSet('qrCodeImage', 'data:image/png;base64,'.base64_encode('fake-png-binary-data'));
});

test('staf dapat mengontrol siklus hidup session waha start dan stop', function () {
    $sysblas = Sysblas::create([
        'nama' => 'WAHA Lifecycle Test',
        'provider' => SysblasProvider::Waha,
        'session_name' => 'test-session',
        'url_api' => 'https://waha.gobilling.id',
        'username' => 'admin',
        'password' => 'pass123',
        'limit_per_menit' => 60,
        'is_default' => false,
        'is_aktif' => false,
    ]);

    Http::fake([
        'https://waha.gobilling.id/api/sessions/start*' => Http::response(['success' => true], 200),
        'https://waha.gobilling.id/api/sessions/stop*' => Http::response(['success' => true], 200),
        'https://waha.gobilling.id/api/sessions*' => Http::response([
            [
                'name' => 'test-session',
                'status' => 'STARTING',
            ],
        ], 200),
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('startSession', $sysblas->id)
        ->call('stopSession', $sysblas->id)
        ->assertHasNoErrors();
});

test('waha driver melakukan simulasi typing presence dan pengiriman teks', function () {
    $sysblas = Sysblas::create([
        'nama' => 'WAHA Presence Test',
        'provider' => SysblasProvider::Waha,
        'session_name' => 'default',
        'url_api' => 'https://waha.gobilling.id',
        'username' => 'admin',
        'password' => 'pass123',
        'limit_per_menit' => 20,
        'delay_detik' => 1,
        'jitter_detik' => 0,
        'is_typing_simulation' => true,
        'is_default' => true,
        'is_aktif' => true,
    ]);

    Http::fake([
        'https://waha.gobilling.id/api/startTyping*' => Http::response(['success' => true], 200),
        'https://waha.gobilling.id/api/stopTyping*' => Http::response(['success' => true], 200),
        'https://waha.gobilling.id/api/sendText*' => Http::response(['success' => true, 'id' => 'waha_msg_123'], 200),
    ]);

    $client = $sysblas->makeClient();
    $result = $client->sendMessage('081234567890', 'Pesan uji coba anti-ban');

    expect($result['success'])->toBeTrue()
        ->and($result['status'])->toBe('success');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/startTyping'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/stopTyping'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/sendText'));
});

test('staf dapat menambah koneksi gowa baru dengan basic auth', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('nama', 'GOWA CS Utama')
        ->set('provider', SysblasProvider::Gowa->value)
        ->set('nomor', '081234567899')
        ->set('url_api', 'http://localhost:3000')
        ->set('username', 'admin')
        ->set('password', 'secret')
        ->set('session_name', 'org_1')
        ->set('limit_per_menit', 20)
        ->set('delay_detik', 300)
        ->set('jitter_detik', 3)
        ->set('is_default', false)
        ->set('is_aktif', true)
        ->call('simpan')
        ->assertHasNoErrors();

    $created = Sysblas::where('nama', 'GOWA CS Utama')->first();
    expect($created)->not->toBeNull()
        ->and($created->provider)->toBe(SysblasProvider::Gowa)
        ->and($created->username)->toBe('admin')
        ->and($created->password)->toBe('secret')
        ->and($created->session_name)->toBe('org_1');
});

test('validasi gagal saat username atau password kosong untuk provider gowa', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('nama', 'GOWA Tanpa Auth')
        ->set('provider', SysblasProvider::Gowa->value)
        ->set('url_api', 'http://localhost:3000')
        ->set('username', '')
        ->set('password', '')
        ->call('simpan')
        ->assertHasErrors(['username', 'password']);
});

test('staf dapat menarik daftar device dari server gowa', function () {
    Http::fake([
        'http://localhost:3000/devices' => Http::response([
            'code' => 'SUCCESS',
            'message' => 'List devices',
            'status' => 200,
            'results' => [
                ['id' => 'org_1', 'phone_number' => '6281234567890', 'display_name' => 'CS Utama', 'state' => 'logged_in'],
            ],
        ], 200),
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('provider', SysblasProvider::Gowa->value)
        ->set('url_api', 'http://localhost:3000')
        ->set('username', 'admin')
        ->set('password', 'secret')
        ->call('tarikDeviceGowa')
        ->assertSet('session_name', 'org_1')
        ->assertSet('nomor', '6281234567890');
});

test('waha driver menangani response http 429 rate limit dengan aman', function () {
    $sysblas = Sysblas::create([
        'nama' => 'WAHA 429 Test',
        'provider' => SysblasProvider::Waha,
        'session_name' => 'default',
        'url_api' => 'https://waha.gobilling.id',
        'limit_per_menit' => 20,
        'is_typing_simulation' => false,
        'is_default' => false,
        'is_aktif' => true,
    ]);

    Http::fake([
        'https://waha.gobilling.id/api/sendText*' => Http::response([
            'message' => 'Rate limit exceeded',
        ], 429),
    ]);

    $client = $sysblas->makeClient();
    $result = $client->sendMessage('081234567890', 'Pesan saat rate limit');

    expect($result['success'])->toBeFalse()
        ->and($result['status'])->toBe('rate_limited')
        ->and($result['message'])->toContain('429');
});
