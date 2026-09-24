<?php

use App\Enums\JenisKoneksi;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RouterOS\Client;
use RouterOS\Query;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Client RouterOS palsu: mencatat setiap query terkirim dan membalas read() menurut endpoint terakhir.
 *
 * @param  array<string, array<int, array<string, string>>>  $reads
 * @return array{0: Client, 1: ArrayObject<int, Query>}
 */
function fakeRouterOs(array $reads = []): array
{
    $sent = new ArrayObject;
    $last = null;
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('query')->andReturnUsing(function (Query $q) use ($sent, &$last, $client) {
        $sent->append($q);
        $last = $q->getEndpoint();

        return $client;
    });
    $client->shouldReceive('read')->andReturnUsing(function () use (&$last, $reads) {
        return $reads[$last] ?? [];
    });

    return [$client, $sent];
}

/**
 * Atribut `=key=value` dari query terkirim ke endpoint tertentu, sebagai map key => value.
 *
 * @param  ArrayObject<int, Query>  $sent
 * @return array<string, string>|null null jika tidak ada query ke endpoint itu
 */
function sentAttributes(ArrayObject $sent, string $endpoint): ?array
{
    foreach ($sent as $query) {
        if ($query->getEndpoint() === $endpoint) {
            $map = [];
            foreach ($query->getAttributes() as $word) {
                [, $key, $value] = array_pad(explode('=', $word, 3), 3, '');
                $map[$key] = $value;
            }

            return $map;
        }
    }

    return null;
}

/**
 * Layanan PPPoE dinamis siap provisi pada router + pool, dengan profil "P10".
 *
 * @return array{0: Router, 1: LayananPelanggan}
 */
function layananPppoeDinamis(): array
{
    $router = Router::factory()->online()->create();
    $pool = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Rumah', 'ip_network' => '10.0.0.0', 'cidr' => 24]);
    $profil = ProfilBandwidth::firstWhere('nama_bandwidth', 'P10') ?? ProfilBandwidth::factory()->create(['nama_bandwidth' => 'P10']);
    $paket = PaketLayanan::factory()->create(['profil_bandwidth_id' => $profil->id]);
    $pelanggan = Pelanggan::factory()->create();

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'ip_pool_id' => $pool->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ip_static' => null,
        'ppp_username' => "{$pelanggan->no_reg}_12345",
        'ppp_password_terenkripsi' => 'secret123',
    ]);

    return [$router, $layanan];
}
