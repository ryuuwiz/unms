<?php

namespace App\Services\Inventaris;

use App\Models\Barang;
use App\Models\BarangKeluar;
use App\Models\BarangMasuk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BarangService
{
    /**
     * Catat barang masuk dan tambahkan ke stok berjalan `Barang.stok`.
     */
    public function catatBarangMasuk(Barang $barang, int $jumlah, Carbon $tanggal, ?string $keterangan = null, ?int $dicatatOleh = null): BarangMasuk
    {
        if ($jumlah < 1) {
            throw new RuntimeException('Jumlah barang masuk harus lebih dari 0.');
        }

        return DB::transaction(function () use ($barang, $jumlah, $tanggal, $keterangan, $dicatatOleh) {
            /** @var Barang $lockedBarang */
            $lockedBarang = Barang::query()->lockForUpdate()->findOrFail($barang->id);

            $barangMasuk = BarangMasuk::create([
                'barang_id' => $lockedBarang->id,
                'tanggal' => $tanggal,
                'jumlah_masuk' => $jumlah,
                'keterangan' => $keterangan,
                'dicatat_oleh' => $dicatatOleh,
            ]);

            $lockedBarang->increment('stok', $jumlah);

            return $barangMasuk;
        });
    }

    /**
     * Catat barang keluar dan kurangi stok berjalan `Barang.stok`.
     *
     * @throws RuntimeException jika stok tidak mencukupi.
     */
    public function catatBarangKeluar(Barang $barang, int $jumlah, Carbon $tanggal, ?string $keterangan = null, ?string $teknisi = null, ?int $dicatatOleh = null): BarangKeluar
    {
        if ($jumlah < 1) {
            throw new RuntimeException('Jumlah barang keluar harus lebih dari 0.');
        }

        return DB::transaction(function () use ($barang, $jumlah, $tanggal, $keterangan, $teknisi, $dicatatOleh) {
            /** @var Barang $lockedBarang */
            $lockedBarang = Barang::query()->lockForUpdate()->findOrFail($barang->id);

            if ($lockedBarang->stok < $jumlah) {
                throw new RuntimeException("Stok {$lockedBarang->nama_barang} tidak mencukupi (tersedia {$lockedBarang->stok}, diminta {$jumlah}).");
            }

            $barangKeluar = BarangKeluar::create([
                'barang_id' => $lockedBarang->id,
                'tanggal' => $tanggal,
                'jumlah_keluar' => $jumlah,
                'keterangan' => $keterangan,
                'teknisi' => $teknisi,
                'dicatat_oleh' => $dicatatOleh,
            ]);

            $lockedBarang->decrement('stok', $jumlah);

            return $barangKeluar;
        });
    }
}
