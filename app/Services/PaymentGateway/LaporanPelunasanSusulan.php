<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\Enums\AksiPelunasanSusulan;
use App\Models\User;
use App\Notifications\PelunasanSusulanNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

/**
 * Jejak Pelunasan Susulan: audit trail per pelunasan/kasus dan notifikasi Admin -- lihat CONTEXT.md.
 */
class LaporanPelunasanSusulan
{
    public const LOG = 'pelunasan_susulan';

    /**
     * Catat ke audit trail setiap pelunasan dan kasus yang perlu ditindaklanjuti -- kasus yang
     * sudah pernah dicatat (pembayaran dan alasan yang sama) dilewati agar sapuan harian tidak
     * mengulang laporan -- lalu kabari Admin dan super_admin sekali bila ada yang baru.
     *
     * @param  list<HasilPelunasanSusulan>  $hasil
     */
    public function laporkan(array $hasil, bool $kabariAdmin = true): void
    {
        $baru = array_values(array_filter(
            $hasil,
            fn (HasilPelunasanSusulan $item): bool => $item->aksi->perluDilaporkan() && ! $this->sudahPernahDilaporkan($item),
        ));

        foreach ($baru as $item) {
            $this->catatAudit($item);
        }

        if ($baru === [] || ! $kabariAdmin) {
            return;
        }

        $dilunasi = count(array_filter($baru, fn (HasilPelunasanSusulan $item): bool => $item->aksi === AksiPelunasanSusulan::Dilunasi));

        Notification::send(
            User::role(['super_admin', 'admin'])->get(),
            new PelunasanSusulanNotification($dilunasi, count($baru) - $dilunasi),
        );
    }

    private function sudahPernahDilaporkan(HasilPelunasanSusulan $item): bool
    {
        return $item->aksi === AksiPelunasanSusulan::Dilaporkan
            && $item->pembayaran !== null
            && Activity::query()
                ->inLog(self::LOG)
                ->where('properties->external_id', $item->pembayaran->externalId)
                ->where('properties->keterangan', $item->keterangan)
                ->exists();
    }

    private function catatAudit(HasilPelunasanSusulan $item): void
    {
        $log = activity(self::LOG)->withProperties([
            'aksi' => $item->aksi->value,
            'koneksi' => $item->koneksi,
            'external_id' => $item->pembayaran?->externalId,
            'xendit_id' => $item->pembayaran?->eventId,
            'nominal' => $item->pembayaran?->paidAmount,
            'dibayar_pada' => $item->pembayaran?->paidAt,
            'status_sebelum' => $item->statusSebelum,
            'keterangan' => $item->keterangan,
        ]);

        if ($item->invoice) {
            $log->performedOn($item->invoice);
        }

        $log->log("Pelunasan Susulan {$item->aksi->value}: ".($item->invoice?->no_invoice ?? $item->pembayaran?->externalId ?? $item->koneksi));
    }
}
