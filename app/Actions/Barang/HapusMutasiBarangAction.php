<?php

namespace App\Actions\Barang;

use App\Enums\Barang\ArahMutasiBarang;
use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Models\JenisBarang;
use App\Models\MutasiBarang;
use App\Models\UnitBarang;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penghapusan Mutasi -- lihat CONTEXT.md "Penghapusan Mutasi" dan ADR-0058.
 */
class HapusMutasiBarangAction
{
    /**
     * Hapus mutasi dan balik efeknya ke unit. Ditolak bila mutasi bukan yang terakhir untuk salah
     * satu unitnya, atau bila stok jadi negatif di tanggal mana pun sesudahnya.
     *
     * @throws ValidationException
     */
    public function execute(MutasiBarang $mutasi): void
    {
        DB::transaction(function () use ($mutasi) {
            JenisBarang::whereKey($mutasi->jenis_barang_id)->lockForUpdate()->first();
            $mutasi->load('units');

            foreach ($mutasi->units as $unit) {
                if ((int) $unit->mutasi()->max('mutasi_barang.id') !== $mutasi->id) {
                    throw ValidationException::withMessages(['hapus' => "Unit {$unit->kode} sudah punya mutasi sesudahnya; hapus mutasi itu lebih dulu."]);
                }
            }

            if ($mutasi->arah === ArahMutasiBarang::Masuk) {
                $this->pastikanStokTidakNegatif($mutasi);
            }

            foreach ($mutasi->units as $unit) {
                match (true) {
                    $mutasi->tipe === TipeMutasiBarang::Pengembalian => $unit->update([
                        'status' => StatusUnitBarang::Terpasang,
                        'kondisi_barang_id' => $unit->pivot->kondisi_sebelum_id ?? $unit->kondisi_barang_id,
                    ]),
                    $mutasi->arah === ArahMutasiBarang::Masuk => $unit->delete(),
                    default => $unit->update(['status' => $this->statusSebelum($unit, $mutasi)]),
                };
            }

            $mutasi->delete();
        });
    }

    /**
     * Menghapus Barang Masuk mengurangi saldo berjalan sebesar jumlahnya mulai dari mutasi itu.
     */
    private function pastikanStokTidakNegatif(MutasiBarang $mutasi): void
    {
        $saldo = 0;

        MutasiBarang::query()
            ->where('jenis_barang_id', $mutasi->jenis_barang_id)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get(['id', 'arah', 'tanggal', 'jumlah'])
            ->reject(fn (MutasiBarang $m) => $m->id === $mutasi->id)
            ->each(function (MutasiBarang $m) use (&$saldo) {
                $saldo += $m->arah === ArahMutasiBarang::Masuk ? $m->jumlah : -$m->jumlah;

                if ($saldo < 0) {
                    throw ValidationException::withMessages(['hapus' => "Stok menjadi negatif pada {$m->tanggal->format('d/m/Y')}; hapus barang keluar sesudahnya lebih dulu."]);
                }
            });
    }

    /**
     * Status unit menurut mutasi sebelum mutasi yang dihapus.
     */
    private function statusSebelum(UnitBarang $unit, MutasiBarang $mutasi): StatusUnitBarang
    {
        $sebelum = $unit->mutasi()->where('mutasi_barang.id', '<', $mutasi->id)->orderByDesc('mutasi_barang.id')->first();

        return match ($sebelum?->tipe) {
            TipeMutasiBarang::Pengembalian => StatusUnitBarang::Dikembalikan,
            TipeMutasiBarang::Pemakaian => StatusUnitBarang::Terpasang,
            TipeMutasiBarang::Rusak => StatusUnitBarang::Rusak,
            default => StatusUnitBarang::DiGudang,
        };
    }
}
