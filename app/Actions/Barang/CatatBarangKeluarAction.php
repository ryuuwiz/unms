<?php

namespace App\Actions\Barang;

use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Models\JenisBarang;
use App\Models\MutasiBarang;
use App\Models\Ticket;
use App\Models\UnitBarang;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Barang Keluar -- lihat CONTEXT.md "Barang Masuk / Barang Keluar" dan ADR-0057.
 */
class CatatBarangKeluarAction
{
    /**
     * Pemakaian wajib menyebut minimal satu teknisi penerima; stok tidak boleh negatif. Jenis dilacak
     * wajib memilih unit spesifik ($unitIds) dan jumlah = banyaknya unit. Keperluan kosong = label tipe.
     *
     * @param  iterable<User>  $teknisi
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
        iterable $teknisi = [],
        ?Ticket $ticket = null,
        array $unitIds = [],
        ?string $keperluan = null,
    ): MutasiBarang {
        $teknisi = collect($teknisi);

        if (! in_array($tipe, TipeMutasiBarang::keluar(), true)) {
            throw ValidationException::withMessages(['tipe' => 'Tipe mutasi bukan barang keluar.']);
        }

        if ($tipe === TipeMutasiBarang::Pemakaian && $teknisi->isEmpty()) {
            throw ValidationException::withMessages(['teknisiIds' => 'Teknisi penerima wajib dipilih.']);
        }

        if ($teknisi->contains(fn (User $user) => ! $user->hasRole('teknisi'))) {
            throw ValidationException::withMessages(['teknisiIds' => 'Penerima harus user berperan teknisi.']);
        }

        return DB::transaction(function () use ($jenis, $tipe, $tanggal, $jumlah, $actor, $keterangan, $teknisi, $ticket, $unitIds, $keperluan) {
            $jenis = JenisBarang::whereKey($jenis->id)->lockForUpdate()->firstOrFail();

            $units = new Collection;

            if ($jenis->dilacak_per_unit) {
                $units = UnitBarang::query()
                    ->where('jenis_barang_id', $jenis->id)
                    ->whereIn('id', $unitIds)
                    ->whereIn('status', StatusUnitBarang::tersedia())
                    ->lockForUpdate()
                    ->get();

                if ($units->isEmpty() || $units->count() !== count(array_unique($unitIds))) {
                    throw ValidationException::withMessages(['unitIds' => 'Pilih unit yang masih tersedia di gudang dari jenis barang ini.']);
                }

                $jumlah = $units->count();
            } elseif ($jumlah < 1) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah minimal 1.']);
            }

            $stok = $jenis->stok();
            if ($jumlah > $stok) {
                throw ValidationException::withMessages(['jumlah' => "Stok {$jenis->nama} tidak cukup (tersisa {$stok} {$jenis->satuan})."]);
            }

            $mutasi = MutasiBarang::create([
                'jenis_barang_id' => $jenis->id,
                'arah' => $tipe->arah(),
                'tipe' => $tipe,
                'tanggal' => $tanggal->toDateString(),
                'jumlah' => $jumlah,
                'keperluan' => $keperluan ?: $tipe->label(),
                'keterangan' => $keterangan,
                'ticket_id' => $ticket?->id,
                'dicatat_oleh' => $actor->id,
            ]);

            $mutasi->teknisi()->attach($teknisi->pluck('id')->unique()->all());

            if ($units->isNotEmpty()) {
                UnitBarang::whereKey($units->modelKeys())->update([
                    'status' => $tipe === TipeMutasiBarang::Rusak ? StatusUnitBarang::Rusak : StatusUnitBarang::Terpasang,
                ]);
                $mutasi->units()->attach($units->modelKeys());
            }

            return $mutasi;
        });
    }
}
