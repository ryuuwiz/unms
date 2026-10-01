<?php

use App\Enums\StatusLayanan;
use App\Livewire\Portal\StatusKoneksi;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pelanggan = Pelanggan::factory()->create();
    $this->akun = $this->pelanggan->akunPelanggan;
    $this->mikrotik = Mockery::mock(MikrotikService::class);
    $this->app->instance(MikrotikService::class, $this->mikrotik);
    Livewire::withoutLazyLoading();
});

/**
 * @param  array<string, mixed>  $atribut
 */
function layananTersambung(Pelanggan $pelanggan, array $atribut = []): LayananPelanggan
{
    return LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'router_id' => Router::factory()->create(['nama_router' => 'ROUTER-RAHASIA'])->id,
        'ppp_username' => 'WIFI0110202601_12345',
        'status' => StatusLayanan::Aktif,
        'tanggal_expired' => now()->addMonth(),
        ...$atribut,
    ]);
}

/**
 * @param  array<string, mixed>  $timpa
 * @return array<string, mixed>
 */
function statusPpp(array $timpa = []): array
{
    return [
        'is_connected' => true, 'status_label' => 'Connected', 'profile' => 'PAKET-RAHASIA', 'service' => 'pppoe',
        'ip_address' => '10.10.10.77', 'local_address' => '10.10.10.1', 'uptime' => '1d3h12m5s',
        'caller_id' => 'AA:BB:CC:DD:EE:FF', 'last_logged_out' => 'jan/02/2026 10:00:00', 'is_disabled' => false,
        'router_online' => true, 'error_message' => null, ...$timpa,
    ];
}

test('Status Koneksi menampilkan Online, Offline, atau tidak tersedia tanpa membocorkan detail jaringan', function (array $status, string $keadaan, ?string $teks) {
    $layanan = layananTersambung($this->pelanggan);
    $this->mikrotik->shouldReceive('getPppStatus')->once()->andReturn(statusPpp($status));

    $halaman = Livewire::actingAs($this->akun, 'pelanggan')
        ->test(StatusKoneksi::class, ['layananId' => $layanan->id])
        ->assertSeeHtml('data-status-koneksi="'.$keadaan.'"');

    if ($teks) {
        $halaman->assertSee($teks);
    }

    $halaman->assertDontSee('10.10.10.77')
        ->assertDontSee('10.10.10.1')
        ->assertDontSee('AA:BB:CC:DD:EE:FF')
        ->assertDontSee('ROUTER-RAHASIA')
        ->assertDontSee('PAKET-RAHASIA')
        ->assertDontSee('WIFI0110202601_12345');
})->with([
    'online' => [[], StatusKoneksi::KEADAAN_ONLINE, 'tersambung 1 hari 3 jam'],
    'offline' => [['is_connected' => false, 'uptime' => null], StatusKoneksi::KEADAAN_OFFLINE, 'Offline'],
    'router tak terjangkau' => [['is_connected' => false, 'router_online' => false], StatusKoneksi::KEADAAN_TIDAK_TERSEDIA, 'Status tidak tersedia'],
]);

test('layanan tanpa router atau PPP Username tidak memanggil router', function () {
    $layanan = LayananPelanggan::factory()->create([
        'pelanggan_id' => $this->pelanggan->id,
        'router_id' => null,
        'ppp_username' => null,
        'status' => StatusLayanan::Proses,
    ]);
    $this->mikrotik->shouldNotReceive('getPppStatus', 'refreshPppStatus');

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(StatusKoneksi::class, ['layananId' => $layanan->id])
        ->assertSeeHtml('data-status-koneksi="'.StatusKoneksi::KEADAAN_BELUM_TERSAMBUNG.'"');
});

test('layanan suspend menampilkan ajakan melunasi tagihan', function () {
    $layanan = layananTersambung($this->pelanggan, ['status' => StatusLayanan::Suspend]);
    $this->mikrotik->shouldReceive('getPppStatus')->andReturn(statusPpp(['is_connected' => false]));

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(StatusKoneksi::class, ['layananId' => $layanan->id])
        ->assertSee('Layanan diisolir. Lunasi tagihan untuk mengaktifkan kembali.');
});

test('cek ulang membaca ulang router dan dibatasi per pelanggan', function () {
    $layanan = layananTersambung($this->pelanggan);
    $this->mikrotik->shouldReceive('getPppStatus')->andReturn(statusPpp());
    $this->mikrotik->shouldReceive('refreshPppStatus')->times(6)->andReturn(statusPpp());

    $komponen = Livewire::actingAs($this->akun, 'pelanggan')
        ->test(StatusKoneksi::class, ['layananId' => $layanan->id]);

    foreach (range(1, 6) as $ke) {
        $komponen->call('muatUlang')->assertSet('dibatasi', false);
    }

    $komponen->call('muatUlang')
        ->assertSet('dibatasi', true)
        ->assertSee('Terlalu sering memeriksa');
});

test('pelanggan tidak bisa membaca Status Koneksi layanan pelanggan lain', function () {
    $layananLain = layananTersambung(Pelanggan::factory()->create());
    $this->mikrotik->shouldNotReceive('getPppStatus');

    Livewire::actingAs($this->akun, 'pelanggan')
        ->test(StatusKoneksi::class, ['layananId' => $layananLain->id]);
})->throws(ModelNotFoundException::class);
