<?php

namespace App\Livewire\Portal;

use App\Enums\KeadaanKoneksi;
use App\Enums\StatusLayanan;
use App\Models\AkunPelanggan;
use App\Models\LayananPelanggan;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Status Koneksi satu Layanan Pelanggan di Portal (CONTEXT.md "Status Koneksi"): Online/Offline
 * dan lama sesi, dibaca live dari router. Hanya `keadaan` dan `lamaSesi` yang keluar dari
 * status PPP -- router, IP, caller-id, dan profile tidak pernah sampai ke view.
 */
#[Lazy]
class StatusKoneksi extends Component
{
    private const BATAS_MUAT_ULANG_PER_MENIT = 6;

    #[Locked]
    public int $layananId;

    public bool $dibatasi = false;

    private bool $paksaBacaUlang = false;

    public function mount(int $layananId): void
    {
        $this->layananId = $layananId;
        $this->layanan();
    }

    public function muatUlang(): void
    {
        $kunci = 'portal-status-koneksi:'.$this->layanan()->pelanggan_id;

        $this->dibatasi = RateLimiter::tooManyAttempts($kunci, self::BATAS_MUAT_ULANG_PER_MENIT);
        if ($this->dibatasi) {
            return;
        }

        RateLimiter::hit($kunci, 60);
        $this->paksaBacaUlang = true;
    }

    public function placeholder(): View
    {
        return view('livewire.portal.status-koneksi-placeholder');
    }

    public function render(MikrotikService $mikrotik): View
    {
        $layanan = $this->layanan();

        return view('livewire.portal.status-koneksi', [
            'diisolir' => $layanan->status === StatusLayanan::Suspend,
            'masaAktifHabis' => $layanan->status === StatusLayanan::Aktif && $layanan->isExpired(),
            ...$this->bacaKoneksi($layanan, $mikrotik),
        ]);
    }

    /**
     * @return array{keadaan: KeadaanKoneksi, lamaSesi: ?string}
     */
    private function bacaKoneksi(LayananPelanggan $layanan, MikrotikService $mikrotik): array
    {
        if (! $layanan->router || blank($layanan->ppp_username)) {
            return ['keadaan' => KeadaanKoneksi::BelumTersambung, 'lamaSesi' => null];
        }

        $status = $this->paksaBacaUlang
            ? $mikrotik->refreshPppStatus($layanan->router, $layanan->ppp_username)
            : $mikrotik->getPppStatus($layanan->router, $layanan->ppp_username);

        if (! $status['router_online']) {
            return ['keadaan' => KeadaanKoneksi::TidakTersedia, 'lamaSesi' => null];
        }

        return $status['is_connected']
            ? ['keadaan' => KeadaanKoneksi::Online, 'lamaSesi' => self::lamaSesi($status['uptime'])]
            : ['keadaan' => KeadaanKoneksi::Offline, 'lamaSesi' => null];
    }

    /**
     * Uptime RouterOS (mis. `1w2d3h4m5s`) jadi dua satuan terbesar, mis. "9 hari 3 jam".
     */
    private static function lamaSesi(?string $uptime): ?string
    {
        if (! preg_match_all('/(\d+)([wdhms])/', (string) $uptime, $cocok, PREG_SET_ORDER)) {
            return null;
        }

        $detik = 0;
        foreach ($cocok as [, $angka, $satuan]) {
            $detik += (int) $angka * ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1][$satuan];
        }

        $bagian = [];
        foreach (['hari' => 86400, 'jam' => 3600, 'menit' => 60, 'detik' => 1] as $nama => $panjang) {
            if ($detik >= $panjang) {
                $bagian[] = intdiv($detik, $panjang).' '.$nama;
                $detik %= $panjang;
            }
        }

        return implode(' ', array_slice($bagian, 0, 2)) ?: null;
    }

    /**
     * Layanan milik pelanggan yang login; layanan pelanggan lain 404.
     */
    private function layanan(): LayananPelanggan
    {
        /** @var AkunPelanggan $akun */
        $akun = Auth::guard('pelanggan')->user();

        return LayananPelanggan::query()
            ->with('router')
            ->where('pelanggan_id', $akun->pelanggan_id)
            ->findOrFail($this->layananId);
    }
}
