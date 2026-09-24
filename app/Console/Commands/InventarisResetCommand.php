<?php

namespace App\Console\Commands;

use App\Models\MutasiBarang;
use App\Models\UnitBarang;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Kosongkan mutasi, unit, dan penghitung kode barang agar Impor Inventaris bisa diulang.
 * Jenis, kategori, dan kondisi barang tetap. Lihat CONTEXT.md "Impor Inventaris" dan ADR-0057.
 */
class InventarisResetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'inventaris:reset {--force : Lewati konfirmasi (untuk skrip)}';

    /**
     * @var string
     */
    protected $description = 'Hapus seluruh mutasi barang, unit barang, dan penghitung kode (jenis/kategori/kondisi tetap)';

    public function handle(): int
    {
        $mutasi = MutasiBarang::count();
        $unit = UnitBarang::count();

        $this->warn("Akan menghapus {$mutasi} mutasi barang dan {$unit} unit barang beserta penghitung kode. Tidak dapat dibatalkan.");

        if (! $this->option('force') && $this->ask('Ketik RESET untuk melanjutkan') !== 'RESET') {
            $this->error('Dibatalkan.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            DB::table('mutasi_barang_unit')->delete();
            DB::table('unit_barang')->delete();
            DB::table('mutasi_barang')->delete();
            DB::table('kode_barang_counter')->delete();
        });

        activity('inventaris')
            ->withProperties(['action' => 'reset_inventaris', 'mutasi' => $mutasi, 'unit' => $unit])
            ->log('Reset data inventaris lewat artisan inventaris:reset');

        $this->info('Data inventaris dikosongkan.');

        return self::SUCCESS;
    }
}
