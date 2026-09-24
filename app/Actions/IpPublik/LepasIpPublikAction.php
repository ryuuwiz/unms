<?php

namespace App\Actions\IpPublik;

use App\Models\IpPublik;

class LepasIpPublikAction
{
    /**
     * Kembalikan IP Publik ke inventaris. Dengan $reprovision, secret layanan dikembalikan ke profile
     * per pool dan sesi diputus (dinonaktifkan saat layanan berhenti/dihapus karena secret ikut dinonaktifkan/dihapus).
     */
    public function execute(IpPublik $ipPublik, bool $reprovision = true): void
    {
        $layanan = $ipPublik->layananPelanggan;

        $ipPublik->update([
            'layanan_pelanggan_id' => null,
            'harga_ditagih' => null,
        ]);

        if ($reprovision && $layanan) {
            $layanan->unsetRelation('ipPubliks');
            TetapkanIpPublikAction::reprovision($layanan);
        }
    }
}
