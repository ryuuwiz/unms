<?php

namespace App\Actions\IpPublik;

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TetapkanIpPublikAction
{
    /**
     * Tetapkan IP Publik Dedicated ke layanan PPPoE dan salin harga bulanannya (snapshot). Sesi aktif
     * diputus lewat job provisi agar alamat baru langsung dipakai.
     *
     * @throws ValidationException
     */
    public function execute(IpPublik $ipPublik, LayananPelanggan $layanan): void
    {
        DB::transaction(function () use ($ipPublik, $layanan) {
            $ipPublik = IpPublik::whereKey($ipPublik->id)->lockForUpdate()->firstOrFail();

            if ($ipPublik->layanan_pelanggan_id === $layanan->id) {
                return;
            }

            $galat = match (true) {
                $layanan->jenis_koneksi !== JenisKoneksi::Pppoe => 'IP Publik hanya dapat dipasang pada layanan berjenis PPPoE.',
                $layanan->status === StatusLayanan::Berhenti => 'Layanan yang sudah berhenti tidak dapat diberi IP Publik.',
                $ipPublik->router_id !== $layanan->router_id => 'IP Publik terdaftar pada router lain, bukan router layanan ini.',
                ! $ipPublik->isTersedia() => 'IP Publik sudah dipakai layanan lain.',
                $layanan->ipPubliks()->exists() => 'Layanan ini sudah memiliki IP Publik. Lepas IP yang lama terlebih dahulu.',
                default => null,
            };

            if ($galat !== null) {
                throw ValidationException::withMessages(['ip_publik_id' => $galat]);
            }

            $ipPublik->update([
                'layanan_pelanggan_id' => $layanan->id,
                'harga_ditagih' => $ipPublik->harga_bulanan,
            ]);
        });

        $layanan->unsetRelation('ipPubliks');

        $this->reprovision($layanan);
    }

    /**
     * Provisi ulang + putus sesi, hanya untuk layanan yang sudah pernah diaktivasi (status Proses
     * tidak boleh berubah jadi Aktif lewat jalur ini).
     */
    public static function reprovision(LayananPelanggan $layanan): void
    {
        if ($layanan->router_id && $layanan->ppp_username && in_array($layanan->status, [StatusLayanan::Aktif, StatusLayanan::Suspend], true)) {
            ProvisionPppoeAccountJob::dispatch($layanan, kickActive: true)->afterCommit();
        }
    }
}
