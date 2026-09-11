<?php

use App\Enums\JenisKoneksi;
use App\Exceptions\MikrotikException;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RouterOS\Client;
use RouterOS\Exceptions\StreamException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = new MikrotikService;
});

/**
 * Susun layanan pelanggan IP Static agar guard IP Pool tidak ikut memicu query RouterOS tambahan,
 * sehingga urutan panggilan query/read ke client mock tetap dapat diprediksi.
 */
function buatLayananUntukTesIdempotensi(): LayananPelanggan
{
    $pelanggan = Pelanggan::factory()->create();
    $profil = ProfilBandwidth::factory()->create(['nama_bandwidth' => 'Profile-Idempotent-10M']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);

    return LayananPelanggan::factory()->create([
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'jenis_koneksi' => JenisKoneksi::IpStatic,
        'ppp_username' => 'user-idempotent-1',
        'ppp_password_terenkripsi' => 'secret123',
    ]);
}

/**
 * Buat mock RouterOS Client yang mengembalikan setiap response secara berurutan pada setiap panggilan read().
 *
 * @param  array<int, callable>  $responses  Antrean closure, masing-masing dieksekusi (dan bisa throw) pada satu panggilan read().
 */
function mockClientDenganUrutanResponse(array $responses): Client
{
    $mockClient = Mockery::mock(Client::class);
    $mockClient->shouldReceive('query')->andReturnSelf();
    $mockClient->shouldReceive('read')->andReturnUsing(function () use (&$responses) {
        return (array_shift($responses))();
    });

    return $mockClient;
}

test('createOrUpdatePppoeSecret melanjutkan ke update saat create gagal karena entry sudah ada (race sempit)', function () {
    $router = Router::factory()->online()->create();
    $layanan = buatLayananUntukTesIdempotensi();
    $layanan->update(['router_id' => $router->id]);

    // Urutan panggilan read() yang diharapkan:
    // 1. ensurePppProfile: cari profile -> sudah ada -> lanjut ke set (update), bukan create.
    // 2. ensurePppProfile: set profile -> ack.
    // 3. createOrUpdatePppoeSecret: cari secret -> belum ada.
    // 4. createOrUpdatePppoeSecret: create secret -> RouterOS menolak, entry ternyata sudah ada.
    // 5. Re-query verifikasi -> entry ditemukan.
    // 6. Update secret yang ditemukan -> ack.
    $mockClient = mockClientDenganUrutanResponse([
        fn () => [['.id' => '*1', 'name' => 'Profile-Idempotent-10M', 'rate-limit' => '10M/10M', 'comment' => 'x']],
        fn () => [],
        fn () => [],
        fn () => ['after' => ['message' => 'failure: already have such entry']],
        fn () => [['.id' => '*2', 'name' => 'user-idempotent-1']],
        fn () => [],
    ]);

    $result = $this->service->createOrUpdatePppoeSecret($router, $layanan, $mockClient);

    expect($result)->not->toBeNull()
        ->and($result['status'])->toBe('success')
        ->and($result['action'])->toBe('updated');

    $layanan->refresh();
    expect($layanan->provisioning_status->value)->toBe('success');
});

test('createOrUpdatePppoeSecret tetap melempar MikrotikException untuk kegagalan create yang bukan soal duplikat', function () {
    $router = Router::factory()->online()->create();
    $layanan = buatLayananUntukTesIdempotensi();
    $layanan->update(['router_id' => $router->id]);

    $mockClient = mockClientDenganUrutanResponse([
        fn () => [['.id' => '*1', 'name' => 'Profile-Idempotent-10M', 'rate-limit' => '10M/10M', 'comment' => 'x']],
        fn () => [],
        fn () => [],
        function () {
            throw new StreamException('Stream timed out');
        },
        // Re-query verifikasi tetap tidak menemukan entry -> ini benar-benar error, bukan duplikat.
        fn () => [],
    ]);

    expect(fn () => $this->service->createOrUpdatePppoeSecret($router, $layanan, $mockClient))
        ->toThrow(MikrotikException::class, 'Stream timed out');

    $layanan->refresh();
    expect($layanan->provisioning_status->value)->toBe('failed');
});
