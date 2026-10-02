<?php

namespace App\Jobs\Mikrotik;

use App\Events\StatusSesiPppBerubah;
use App\Models\LayananPelanggan;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Pemantauan Sesi PPP satu router (mikrotik:pantau-sesi, ADR-0070): bandingkan sesi aktif dengan
 * snapshot tick sebelumnya dan siarkan StatusSesiPppBerubah untuk layanan yang sesinya berubah.
 * Router yang berubah terjangkau <-> tidak terjangkau menyiarkan seluruh layanannya, hanya saat transisi.
 */
class PantauSesiPppRouterJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 10;

    public int $uniqueFor = 30;

    public function __construct(public Router $router)
    {
        $this->onQueue('mikrotik-high');
    }

    public function uniqueId(): string
    {
        return (string) $this->router->id;
    }

    public function handle(MikrotikService $mikrotikService): void
    {
        $kunci = "sesi-ppp:{$this->router->id}";
        $sebelumnya = Cache::get($kunci);
        $sekarang = $mikrotikService->getSidikSesiPppAktif($this->router);

        // false = router tidak terjangkau; snapshot kedaluwarsa bila pemantauan berhenti, tick pertama sesudahnya diam.
        Cache::put($kunci, $sekarang ?? false, now()->addMinutes(5));

        if ($sebelumnya === null || ($sebelumnya === false && $sekarang === null)) {
            return;
        }

        $layanans = LayananPelanggan::where('router_id', $this->router->id)->whereNotNull('ppp_username');

        if ($sebelumnya !== false && $sekarang !== null) {
            $berubah = array_keys(array_diff_assoc($sebelumnya, $sekarang) + array_diff_assoc($sekarang, $sebelumnya));

            if ($berubah === []) {
                return;
            }

            $layanans->whereIn('ppp_username', $berubah);
        }

        $layanans->get(['id', 'pelanggan_id'])
            ->each(fn (LayananPelanggan $layanan) => StatusSesiPppBerubah::dispatch($layanan->pelanggan_id, $layanan->id));
    }
}
