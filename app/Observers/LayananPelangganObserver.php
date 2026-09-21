<?php

namespace App\Observers;

use App\Jobs\Mikrotik\CleanupPppSecretOnOldRouterJob;
use App\Jobs\Mikrotik\UpdatePppoeProfileJob;
use App\Models\LayananPelanggan;

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
                );
            }

            // Reset ip_pool_id karena pool milik router lama tidak berlaku di router baru,
            // kecuali pool baru dipilih pada penyimpanan yang sama (mis. form Edit).
            if (! $layanan->isDirty('ip_pool_id')) {
                $layanan->ip_pool_id = null;
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
    }

    /**
     * Sinkronkan status Pelanggan dari seluruh layanannya setiap kali status layanan
     * berubah, lewat jalur mana pun (form, listener, pembayaran, command isolir).
     */
    public function saved(LayananPelanggan $layanan): void
    {
        if ($layanan->wasChanged('status')) {
            $layanan->pelanggan?->sinkronkanStatusDariLayanan();
        }
    }

    /**
     * Handle the LayananPelanggan "deleted" event (soft delete / force delete).
     *
     * Ketika layanan pelanggan dihapus dari UNMS, otomatis hapus PPP Secret
     * terkait dari router MikroTik agar tidak tertinggal sebagai orphaned secret.
     */
    public function deleted(LayananPelanggan $layanan): void
    {
        $layanan->pelanggan?->sinkronkanStatusDariLayanan();

        $routerId = (int) $layanan->router_id;
        $pppUsername = (string) $layanan->ppp_username;

        if ($routerId && $pppUsername) {
            CleanupPppSecretOnOldRouterJob::dispatch(
                $routerId,
                $pppUsername,
                $layanan->id,
            );
        }
    }
}
