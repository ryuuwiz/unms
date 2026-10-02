<?php

use App\Events\StatusSesiPppBerubah;
use App\Exceptions\MikrotikConnectionException;
use App\Models\LayananPelanggan;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([StatusSesiPppBerubah::class]);

    $this->router = Router::factory()->online()->create();
    $this->budi = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'ppp_username' => 'BF01_11111']);
    $this->sari = LayananPelanggan::factory()->create(['router_id' => $this->router->id, 'ppp_username' => 'BF01_22222']);

    $this->routerOs = Mockery::mock(MikrotikService::class)->makePartial();
    $this->app->instance(MikrotikService::class, $this->routerOs);
});

/**
 * Satu tick Pemantauan Sesi PPP dengan isi `/ppp/active` tertentu; null = router tidak terjangkau.
 *
 * @param  array<int, array<string, string>>|null  $sesiAktif
 */
function tickPemantauan(MockInterface $routerOs, ?array $sesiAktif): void
{
    $getClient = $routerOs->shouldReceive('getClient')->once();
    $sesiAktif === null
        ? $getClient->andThrow(new MikrotikConnectionException('down'))
        : $getClient->andReturn(fakeRouterOs(['/ppp/active/print' => $sesiAktif])[0]);

    test()->artisan('mikrotik:pantau-sesi')->assertSuccessful();
}

/** @return array<string, string> */
function sesi(string $username, string $id = '*1', string $address = '10.0.0.2', string $uptime = '1m'): array
{
    return ['.id' => $id, 'name' => $username, 'address' => $address, 'caller-id' => 'AA:BB', 'uptime' => $uptime];
}

/** @return array<int, int> id layanan yang disiarkan */
function layananDisiarkan(): array
{
    return Event::dispatched(StatusSesiPppBerubah::class)->map(fn ($e) => $e[0]->layananId)->sort()->values()->all();
}

test('tick pertama hanya menyimpan snapshot', function () {
    tickPemantauan($this->routerOs, [sesi('BF01_11111')]);

    Event::assertNotDispatched(StatusSesiPppBerubah::class);
});

test('connect dan disconnect menyiarkan layanan terkait ke channel pelanggannya', function () {
    tickPemantauan($this->routerOs, [sesi('BF01_11111')]);
    tickPemantauan($this->routerOs, [sesi('BF01_22222')]);

    expect(layananDisiarkan())->toBe([$this->budi->id, $this->sari->id]);
    Event::assertDispatched(fn (StatusSesiPppBerubah $e) => $e->layananId === $this->budi->id && $e->pelangganId === $this->budi->pelanggan_id);
});

test('reconnect menyiarkan, uptime yang bertambah tidak', function () {
    tickPemantauan($this->routerOs, [sesi('BF01_11111'), sesi('BF01_22222', '*2')]);
    tickPemantauan($this->routerOs, [sesi('BF01_11111', '*9', '10.0.0.7'), sesi('BF01_22222', '*2', uptime: '2m')]);

    expect(layananDisiarkan())->toBe([$this->budi->id]);
});

test('sesi yang bukan milik layanan mana pun diabaikan', function () {
    tickPemantauan($this->routerOs, []);
    tickPemantauan($this->routerOs, [sesi('secret-manual-noc')]);

    Event::assertNotDispatched(StatusSesiPppBerubah::class);
});

test('router tidak terjangkau menyiarkan seluruh layanannya sekali saat transisi', function () {
    tickPemantauan($this->routerOs, [sesi('BF01_11111')]);
    tickPemantauan($this->routerOs, null);
    expect(layananDisiarkan())->toBe([$this->budi->id, $this->sari->id]);

    tickPemantauan($this->routerOs, null);
    expect(layananDisiarkan())->toHaveCount(2);

    tickPemantauan($this->routerOs, [sesi('BF01_11111')]);
    expect(layananDisiarkan())->toHaveCount(4);
});
