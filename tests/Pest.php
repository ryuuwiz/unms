<?php

use App\Enums\JenisKoneksi;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\ProfilBandwidth;
use App\Models\Router;
use App\Models\RouterPaket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
    ->beforeEach(fn () => Http::preventStrayRequests())
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
 * Layanan PPPoE dinamis siap provisi pada router + pool, paket "P10" terdaftar di router itu (Router Paket).
 *
 * @return array{0: Router, 1: LayananPelanggan}
 */
function layananPppoeDinamis(): array
{
    $router = Router::factory()->online()->create();
    $pool = IpPool::factory()->create(['router_id' => $router->id, 'nama_pool' => 'Pool-Rumah', 'ip_network' => '10.0.0.0', 'cidr' => 24]);
    $profil = ProfilBandwidth::firstWhere('nama_bandwidth', 'P10') ?? ProfilBandwidth::factory()->create(['nama_bandwidth' => 'P10']);
    $paket = PaketLayanan::firstWhere('nama_paket', 'P10') ?? PaketLayanan::factory()->create(['nama_paket' => 'P10', 'profil_bandwidth_id' => $profil->id]);
    RouterPaket::create(['paket_layanan_id' => $paket->id, 'router_id' => $router->id, 'ip_pool_id' => $pool->id]);
    $pelanggan = Pelanggan::factory()->create();

    $layanan = LayananPelanggan::factory()->create([
        'router_id' => $router->id,
        'pelanggan_id' => $pelanggan->id,
        'paket_layanan_id' => $paket->id,
        'jenis_koneksi' => JenisKoneksi::Pppoe,
        'ip_static' => null,
        'ppp_username' => "{$pelanggan->no_reg}_12345",
        'ppp_password_terenkripsi' => 'secret123',
    ]);

    return [$router, $layanan];
}

/**
 * Daftarkan paket layanan ke router layanan itu (Router Paket, ADR-0063) memakai pool terlama router (dibuat bila belum ada).
 */
function daftarkanRouterPaket(LayananPelanggan $layanan): RouterPaket
{
    return RouterPaket::firstOrCreate(
        ['paket_layanan_id' => $layanan->paket_layanan_id, 'router_id' => $layanan->router_id],
        ['ip_pool_id' => (IpPool::where('router_id', $layanan->router_id)->orderBy('id')->first()
            ?? IpPool::factory()->create(['router_id' => $layanan->router_id]))->id],
    );
}

/**
 * @param  array<string, list<list<mixed>>>  $sheets  nama sheet => baris (baris pertama = header)
 */
function berkasImpor(array $sheets): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach ($sheets as $nama => $baris) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($nama);
        $sheet->fromArray($baris);
    }

    $path = tempnam(sys_get_temp_dir(), 'uji-impor-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

/**
 * Palsukan Payment Session Xendit: `POST /sessions` menghasilkan session baru dengan id unik dan
 * payment_link_url yang memuat id tersebut; `GET /sessions/{id}` selalu melaporkan ACTIVE.
 * Tes yang butuh status lain mengganti factory lewat Http::swap() lalu memalsukan ulang.
 */
function fakeXenditSession(): void
{
    Http::fake([
        'api.xendit.co/sessions' => function (Request $request) {
            $id = 'ps-'.bin2hex(random_bytes(12));

            return Http::response([
                'payment_session_id' => $id,
                'reference_id' => $request['reference_id'],
                'status' => 'ACTIVE',
                'amount' => $request['amount'],
                'expires_at' => $request['expires_at'],
                'payment_link_url' => 'https://xen.to/'.$id,
            ], 201);
        },
        'api.xendit.co/sessions/*' => Http::response(['status' => 'ACTIVE']),
    ]);
}
