<?php

namespace App\Actions\LayananPelanggan;

use App\Enums\StatusLayanan;
use App\Events\LayananPelangganStatusChangedEvent;
use App\Models\LayananPelanggan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UbahStatusLayananAction
{
    /**
     * Eksekusi perubahan status layanan pelanggan secara atomic dan picu event terkait.
     */
    public function execute(
        LayananPelanggan $layanan,
        StatusLayanan $statusBaru,
        ?User $actor = null,
        ?string $catatan = null
    ): LayananPelanggan {
        if ($layanan->status === $statusBaru) {
            return $layanan;
        }

        $statusLama = $layanan->status;

        DB::transaction(function () use ($layanan, $statusBaru) {
            $layanan->update([
                'status' => $statusBaru,
            ]);
        });

        // Dispatch domain event setelah transaksi berhasil
        event(new LayananPelangganStatusChangedEvent(
            layanan: $layanan->fresh(),
            statusLama: $statusLama,
            statusBaru: $statusBaru,
            actor: $actor,
            catatan: $catatan
        ));

        return $layanan->fresh();
    }
}
