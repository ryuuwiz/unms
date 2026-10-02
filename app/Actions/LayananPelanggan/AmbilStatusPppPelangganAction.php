<?php

namespace App\Actions\LayananPelanggan;

use App\Models\Pelanggan;
use App\Services\Mikrotik\MikrotikService;

/**
 * Status realtime PPP seluruh layanan pelanggan (yang punya router & PPP Username), dipakai
 * halaman detail Pelanggan dan stream SSE-nya. getPppStatus() tidak pernah throw, jadi
 * kegagalan satu router tidak menghalangi layanan lain.
 */
class AmbilStatusPppPelangganAction
{
    public function __construct(private MikrotikService $mikrotikService) {}

    /**
     * @return array<int, array<string, mixed>> status per layanan id
     */
    public function execute(Pelanggan $pelanggan): array
    {
        $pelanggan->loadMissing('layanans.router');

        $statuses = [];

        foreach ($pelanggan->layanans as $layanan) {
            if ($layanan->router && ! empty($layanan->ppp_username)) {
                $statuses[$layanan->id] = $this->mikrotikService->getPppStatus($layanan->router, $layanan->ppp_username);
            }
        }

        return $statuses;
    }
}
