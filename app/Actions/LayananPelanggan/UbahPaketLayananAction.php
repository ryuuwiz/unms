<?php

namespace App\Actions\LayananPelanggan;

use App\Models\LayananPelanggan;

class UbahPaketLayananAction
{
    /**
     * Ubah paket layanan (dan opsional router) suatu Data Registrasi Billing. Dipakai bersama
     * oleh modal Proses ADMIN (hanya paket) dan Proses NOC (paket + router) -- lihat CONTEXT.md
     * "Proses Divisi (NOC/Admin/Customer Service)". Re-provisioning profil MikroTik terpicu
     * otomatis lewat LayananPelangganObserver::updated() saat paket_layanan_id berubah, jadi
     * tidak ada logic MikroTik di sini.
     */
    public function execute(LayananPelanggan $layanan, int $paketLayananId, ?int $routerId = null): LayananPelanggan
    {
        $layanan->update(array_filter([
            'paket_layanan_id' => $paketLayananId,
            'router_id' => $routerId,
        ], fn ($v) => $v !== null));

        return $layanan->fresh();
    }
}
