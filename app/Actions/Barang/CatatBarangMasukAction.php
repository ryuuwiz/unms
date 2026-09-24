<?php

namespace App\Actions\Barang;

use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Models\JenisBarang;
use App\Models\KondisiBarang;
use App\Models\MutasiBarang;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\UnitBarang;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Barang Masuk -- lihat CONTEXT.md "Barang Masuk / Barang Keluar" dan ADR-0057.
 */
class CatatBarangMasukAction
{
    /**
     * Jenis biasa: cukup jumlah. Jenis dilacak: tipe Pengembalian memakai unit berkode yang sudah ada
     * ($unitIds, kodenya tetap, kondisi berubah), tipe lain meng-generate $jumlah unit baru -- kecuali
     * $kodeUnit diisi (Impor Inventaris): satu unit dibuat dengan kode lama itu dan penghitung
     * prefix-nya dimajukan agar kode baru tidak bentrok.
     *
     * @param  list<int>  $unitIds
     *
     * @throws ValidationException
     */
    public function execute(
        JenisBarang $jenis,
        TipeMutasiBarang $tipe,
        Carbon $tanggal,
        int $jumlah,
        User $actor,
        ?string $keterangan = null,
        ?KondisiBarang $kondisi = null,
        ?PengaturanPrefixRegistrasi $brand = null,
        array $unitIds = [],
        ?string $kodeUnit = null,
    ): MutasiBarang {
        if (! in_array($tipe, TipeMutasiBarang::masuk(), true)) {
            throw ValidationException::withMessages(['tipe' => 'Tipe mutasi bukan barang masuk.']);
        }

        if ($jenis->dilacak_per_unit && ! $kondisi) {
            throw ValidationException::withMessages(['kondisi' => 'Kondisi wajib dipilih untuk barang yang dilacak per unit.']);
        }

        return DB::transaction(function () use ($jenis, $tipe, $tanggal, $jumlah, $actor, $keterangan, $kondisi, $brand, $unitIds, $kodeUnit) {
            JenisBarang::whereKey($jenis->id)->lockForUpdate()->first();

            $units = new Collection;

            if ($jenis->dilacak_per_unit && $tipe === TipeMutasiBarang::Pengembalian) {
                $units = UnitBarang::query()
                    ->where('jenis_barang_id', $jenis->id)
                    ->whereIn('id', $unitIds)
                    ->where('status', StatusUnitBarang::Terpasang)
                    ->lockForUpdate()
                    ->get();

                if ($units->isEmpty() || $units->count() !== count(array_unique($unitIds))) {
                    throw ValidationException::withMessages(['unitIds' => 'Pilih unit berstatus Terpasang dari jenis barang ini.']);
                }

                // Kode unit tetap; kondisinya saja yang berubah (CONTEXT.md "Status Unit Barang").
                UnitBarang::whereKey($units->modelKeys())->update([
                    'status' => StatusUnitBarang::Dikembalikan,
                    'kondisi_barang_id' => $kondisi?->id,
                ]);
                $jumlah = $units->count();
            } elseif ($jenis->dilacak_per_unit && $kondisi && $kodeUnit !== null) {
                if (UnitBarang::where('kode', $kodeUnit)->exists()) {
                    throw ValidationException::withMessages(['kodeUnit' => "Kode unit {$kodeUnit} sudah terdaftar."]);
                }

                $units->push(UnitBarang::create([
                    'jenis_barang_id' => $jenis->id,
                    'kode' => $kodeUnit,
                    'kondisi_barang_id' => $kondisi->id,
                    'prefix_registrasi_id' => $brand?->id,
                    'status' => StatusUnitBarang::DiGudang,
                ]));
                $this->majukanPenghitung($kodeUnit);
                $jumlah = 1;
            } elseif ($jenis->dilacak_per_unit && $kondisi) {
                if ($jumlah < 1) {
                    throw ValidationException::withMessages(['jumlah' => 'Jumlah minimal 1.']);
                }

                $prefix = implode('-', array_filter([$jenis->kategori->kode, $kondisi->kode, $brand?->kode]));

                foreach ($this->ambilNomor($prefix, $jumlah) as $nomor) {
                    $units->push(UnitBarang::create([
                        'jenis_barang_id' => $jenis->id,
                        'kode' => "{$prefix}-{$nomor}",
                        'kondisi_barang_id' => $kondisi->id,
                        'prefix_registrasi_id' => $brand?->id,
                        'status' => StatusUnitBarang::DiGudang,
                    ]));
                }
            } elseif ($jumlah < 1) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah minimal 1.']);
            }

            $mutasi = MutasiBarang::create([
                'jenis_barang_id' => $jenis->id,
                'arah' => $tipe->arah(),
                'tipe' => $tipe,
                'tanggal' => $tanggal->toDateString(),
                'jumlah' => $jumlah,
                'keterangan' => $keterangan,
                'dicatat_oleh' => $actor->id,
            ]);

            if ($units->isNotEmpty()) {
                $mutasi->units()->attach($units->modelKeys());
            }

            return $mutasi;
        });
    }

    /**
     * Nomor urut dari penghitung per prefix, bukan MAX()+1 dari unit yang ada, supaya nomor unit
     * yang terhapus tidak pernah terpakai ulang (ADR-0057). Dipanggil di dalam transaksi.
     *
     * @return list<int>
     */
    private function ambilNomor(string $prefix, int $jumlah): array
    {
        DB::table('kode_barang_counter')->insertOrIgnore([
            'prefix' => $prefix, 'nomor_terakhir' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $terakhir = (int) DB::table('kode_barang_counter')->where('prefix', $prefix)->lockForUpdate()->value('nomor_terakhir');

        DB::table('kode_barang_counter')->where('prefix', $prefix)->update([
            'nomor_terakhir' => $terakhir + $jumlah, 'updated_at' => now(),
        ]);

        return range($terakhir + 1, $terakhir + $jumlah);
    }

    /**
     * Pastikan penghitung prefix minimal setara nomor kode lama (`MDM-NEW-BF-240` -> prefix
     * `MDM-NEW-BF` >= 240). Dipanggil di dalam transaksi.
     */
    private function majukanPenghitung(string $kodeUnit): void
    {
        $posisi = strrpos($kodeUnit, '-');
        $nomor = $posisi === false ? '' : substr($kodeUnit, $posisi + 1);

        if ($posisi === false || ! ctype_digit($nomor)) {
            return;
        }

        $prefix = substr($kodeUnit, 0, $posisi);

        DB::table('kode_barang_counter')->insertOrIgnore([
            'prefix' => $prefix, 'nomor_terakhir' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('kode_barang_counter')
            ->where('prefix', $prefix)
            ->where('nomor_terakhir', '<', (int) $nomor)
            ->update(['nomor_terakhir' => (int) $nomor, 'updated_at' => now()]);
    }
}
