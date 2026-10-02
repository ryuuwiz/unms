<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\Enums\AksiPelunasanSusulan;
use App\Models\KasusPelunasanSusulan;
use App\Models\User;
use App\Notifications\PelunasanSusulanNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Jejak Pelunasan Susulan: audit trail per pelunasan/kasus, tabel Kasus Pelunasan Susulan,
 * dan notifikasi Admin -- lihat CONTEXT.md.
 */
class LaporanPelunasanSusulan
{
    public const LOG = 'pelunasan_susulan';

    /**
     * Simpan setiap Kasus Pelunasan Susulan (satu per pembayaran + alasan, sehingga sapuan
     * berulang tidak menggandakannya), catat ke audit trail pelunasan dan kasus yang baru,
     * lalu kabari Admin dan super_admin sekali bila ada yang baru.
     *
     * @param  list<HasilPelunasanSusulan>  $hasil
     */
    public function laporkan(array $hasil, bool $kabariAdmin = true): void
    {
        $baru = array_values(array_filter(
            $hasil,
            fn (HasilPelunasanSusulan $item): bool => $item->aksi->perluDilaporkan() && $this->kasusBaru($item),
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

    /**
     * Simpan kasus bila hasil ini menjadi kasus; false bila kasus yang sama sudah pernah tersimpan.
     */
    private function kasusBaru(HasilPelunasanSusulan $item): bool
    {
        if (! $item->menjadiKasus()) {
            return true;
        }

        $pembayaran = $item->pembayaran;

        return KasusPelunasanSusulan::firstOrCreate(
            ['external_id' => $pembayaran->externalId, 'alasan' => Str::limit((string) $item->tindakanManual, 497)],
            [
                'aksi' => $item->aksi,
                'xendit_id' => $pembayaran->eventId,
                'koneksi' => $item->koneksi,
                'invoice_id' => $item->invoice?->id,
                'nominal' => $pembayaran->paidAmount,
                'dibayar_pada' => $pembayaran->paidAt ? Carbon::parse($pembayaran->paidAt) : null,
                'status_invoice_saat_itu' => $item->statusSebelum,
            ],
        )->wasRecentlyCreated;
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
