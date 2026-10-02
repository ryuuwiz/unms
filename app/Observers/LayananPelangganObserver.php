<?php

namespace App\Observers;

use App\Actions\IpPublik\LepasIpPublikAction;
use App\Actions\IpPublik\TetapkanIpPublikAction;
use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Enums\StatusOdpPort;
use App\Jobs\Mikrotik\CleanupPppSecretOnOldRouterJob;
use App\Jobs\Mikrotik\UpdatePppoeProfileJob;
use App\Models\LayananPelanggan;
use App\Models\OdpPort;
use Illuminate\Support\Facades\Auth;

class LayananPelangganObserver
{
    /**
     * Handle the LayananPelanggan "updating" event.
     *
     * Ketika router_id berubah (layanan pindah router) atau ppp_username berubah,
     * dispatch job untuk menghapus PPP Secret lama dari router agar tidak
     * terjadi duplikat secret / orphaned secret di RouterOS.
     */
    public function updating(LayananPelanggan $layanan): void
    {
        // 1. Jika router_id berubah: hapus secret dari router lama
        if ($layanan->isDirty('router_id')) {
            $oldRouterId = (int) $layanan->getOriginal('router_id');
            $pppUsername = (string) $layanan->getOriginal('ppp_username');

            if ($oldRouterId && $pppUsername) {
                CleanupPppSecretOnOldRouterJob::dispatch(
                    $oldRouterId,
                    $pppUsername,
                    $layanan->id,
                    Auth::id() ?? 'system:tanpa-user',
                    'Layanan dipindah ke router lain',
                );
            }
        }

        // 2. Jika ppp_username berubah pada router yang sama: hapus username lama
        if ($layanan->isDirty('ppp_username') && ! $layanan->isDirty('router_id')) {
            $routerId = (int) $layanan->router_id;
            $oldUsername = (string) $layanan->getOriginal('ppp_username');

            if ($routerId && $oldUsername) {
                CleanupPppSecretOnOldRouterJob::dispatch(
                    $routerId,
                    $oldUsername,
                    $layanan->id,
                    Auth::id() ?? 'system:tanpa-user',
                    'Username PPP layanan diganti',
                );
            }
        }
    }

    /**
     * Handle the LayananPelanggan "updated" event.
     *
     * Ketika paket_layanan_id berubah (upgrade/downgrade paket), otomatis
     * dispatch UpdatePppoeProfileJob ke MikroTik agar profil secret terupdate
     * dan sesi aktif diputus (re-dial instan dengan kecepatan baru).
     */
    public function updated(LayananPelanggan $layanan): void
    {
        if ($layanan->wasChanged('paket_layanan_id') && $layanan->router_id) {
            UpdatePppoeProfileJob::dispatch($layanan);
        }

        // Username/router/IP statis berubah: secret lama sudah dibersihkan (updating), jadi provisi ulang
        // sekarang + putus sesi agar tidak ada jendela outage sampai rekonsiliasi 15 menit. Tanpa IP Pool
        // turunan (PPPoE) provisi pasti ditolak, jadi ditunda sampai router/profil punya pool yang cocok.
        if (! $layanan->trashed()
            && $layanan->wasChanged(['ppp_username', 'router_id', 'ip_static'])
            && ($layanan->ipPool || $layanan->jenis_koneksi === JenisKoneksi::IpStatic)) {
            TetapkanIpPublikAction::reprovision($layanan);
        }
    }

    /**
     * Sinkronkan status Pelanggan dari seluruh layanannya setiap kali status layanan
     * berubah, lewat jalur mana pun (form, listener, pembayaran, command isolir).
     */
    public function saved(LayananPelanggan $layanan): void
    {
        if ($layanan->wasChanged('status')) {
            $layanan->pelanggan?->sinkronkanStatusDariLayanan();

            // Suspend tetap memegang IP Publik (tetap ditagih); Berhenti mengembalikannya ke inventaris.
            if ($layanan->status === StatusLayanan::Berhenti) {
                $this->lepasIpPublik($layanan);
            }
        }
    }

    /**
     * Handle the LayananPelanggan "deleted" event (soft delete / force delete).
     *
     * Ketika layanan pelanggan dihapus dari UNMS, otomatis hapus PPP Secret
     * terkait dari router MikroTik agar tidak tertinggal sebagai orphaned secret,
     * dan lepas IP Publik serta port ODP-nya.
     */
    public function deleted(LayananPelanggan $layanan): void
    {
        $layanan->pelanggan?->sinkronkanStatusDariLayanan();
        $this->lepasIpPublik($layanan);
        OdpPort::where('layanan_pelanggan_id', $layanan->id)->update([
            'status' => StatusOdpPort::Kosong,
            'layanan_pelanggan_id' => null,
        ]);

        $routerId = (int) $layanan->router_id;
        $pppUsername = (string) $layanan->ppp_username;

        if ($routerId && $pppUsername) {
            CleanupPppSecretOnOldRouterJob::dispatch(
                $routerId,
                $pppUsername,
                $layanan->id,
                Auth::id() ?? 'system:tanpa-user',
                'Layanan dihapus dari UNMS',
            );
        }
    }

    /**
     * Lepas seluruh IP Publik layanan tanpa provisi ulang: secret ikut dinonaktifkan/dihapus oleh jalur lain.
     */
    private function lepasIpPublik(LayananPelanggan $layanan): void
    {
        $action = app(LepasIpPublikAction::class);

        foreach ($layanan->ipPubliks()->get() as $ipPublik) {
            $action->execute($ipPublik, reprovision: false);
        }
    }
}
