<?php

namespace App\Actions\Odp;

use App\Models\Odp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hapus permanen ODP (satuan/massal) kecuali yang portnya masih dipakai layanan -- lihat
 * CONTEXT.md "Penghapusan ODP". Menghapus ODP terpakai akan melepas `odp_port_id` layanan
 * pelanggan secara diam-diam (FK nullOnDelete), jadi ODP seperti itu dilewati.
 */
class HapusOdpAction
{
    /**
     * @param  list<int>  $ids
     * @return array{dihapus: list<string>, dilewati: list<string>}
     */
    public function execute(array $ids, User $actor): array
    {
        return DB::transaction(function () use ($ids, $actor) {
            $dipakai = Odp::query()->whereKey($ids)->portDipakai()->orderBy('nama_odp')->pluck('nama_odp', 'id');
            $hapus = Odp::query()->whereKey($ids)->whereKeyNot($dipakai->keys()->all())->orderBy('nama_odp')->get(['id', 'nama_odp']);

            if ($hapus->isNotEmpty()) {
                Odp::query()->whereKey($hapus->modelKeys())->delete();

                activity('odp')
                    ->causedBy($actor)
                    ->withProperties([
                        'action' => 'hapus_odp',
                        'odp' => $hapus->map(fn (Odp $odp) => ['id' => $odp->id, 'nama' => $odp->nama_odp])->all(),
                        'dilewati_port_dipakai' => $dipakai->keys()->all(),
                    ])
                    ->log("Menghapus {$hapus->count()} ODP");
            }

            return [
                'dihapus' => array_values($hapus->map(fn (Odp $odp): string => $odp->nama_odp)->all()),
                'dilewati' => array_values(array_map(strval(...), $dipakai->all())),
            ];
        });
    }
}
