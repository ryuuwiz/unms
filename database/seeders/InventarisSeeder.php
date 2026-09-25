<?php

namespace Database\Seeders;

use App\Actions\Barang\CatatBarangKeluarAction;
use App\Actions\Barang\CatatBarangMasukAction;
use App\Enums\Barang\TipeMutasiBarang;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use App\Models\KondisiBarang;
use App\Models\MutasiBarang;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\UnitBarang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh inventaris untuk development (bukan data master produksi): kategori, jenis barang
 * bergaya gudang asli, dan mutasi bulan berjalan lewat action yang sama dengan input manual, sehingga
 * stok, kode unit, dan penghitung kode konsisten. Dilewati bila sudah ada mutasi. Lihat ADR-0057.
 */
class InventarisSeeder extends Seeder
{
    public function run(): void
    {
        if (MutasiBarang::query()->exists()) {
            optional($this->command)->warn('InventarisSeeder dilewati: sudah ada mutasi barang.'); // command null bila dipanggil lewat $this->seed()

            return;
        }

        $admin = User::where('email', 'admin@example.com')->first() ?? User::first();
        $teknisi = User::role('teknisi')->orderBy('id')->get();

        if (! $admin || $teknisi->isEmpty()) {
            optional($this->command)->warn('InventarisSeeder dilewati: butuh user admin dan minimal satu teknisi (jalankan DevUsersSeeder).'); // command null bila dipanggil lewat $this->seed()

            return;
        }

        $kategori = $this->kategori();
        $baru = KondisiBarang::firstOrCreate(['kode' => 'NEW'], ['nama' => 'Baru']);
        $pgt = KondisiBarang::firstOrCreate(['kode' => 'PGT'], ['nama' => 'Pergantian (bekas)']);
        $brand = PengaturanPrefixRegistrasi::where('kode', 'BF')->first();

        $jenis = collect($this->jenisBarang())->mapWithKeys(fn (array $b) => [
            $b['kode'] => JenisBarang::updateOrCreate(['kode' => $b['kode']], [
                'nama' => $b['nama'],
                'kategori_barang_id' => $kategori[$b['kategori']]->id,
                'satuan' => $b['satuan'],
                'dilacak_per_unit' => $b['dilacak'] ?? false,
            ]),
        ]);

        $masuk = app(CatatBarangMasukAction::class);
        $keluar = app(CatatBarangKeluarAction::class);
        $awalBulan = Carbon::today()->startOfMonth();
        $tanggal = fn (int $hari): Carbon => $awalBulan->copy()->addDays($hari - 1)->min(Carbon::today());

        // Saldo awal barang biasa (stok lama gudang), tanggal 1.
        foreach ($this->jenisBarang() as $b) {
            if (! ($b['dilacak'] ?? false)) {
                $masuk->execute($jenis[$b['kode']], TipeMutasiBarang::SaldoAwal, $awalBulan, $b['stok_awal'], $admin, 'Stok lama gudang');
            }
        }

        // Router tercatat per unit: 5 baru (brand BF) + 2 bekas tanpa kode lama (PGT), saldo awal.
        $router = $jenis['MDM-F670'];
        $masuk->execute($router, TipeMutasiBarang::SaldoAwal, $awalBulan, 5, $admin, 'Stok lama gudang', $baru, $brand);
        $masuk->execute($router, TipeMutasiBarang::SaldoAwal, $awalBulan, 2, $admin, 'Modem bekas pelanggan pra-sistem', $pgt);

        // Pembelian bulan ini.
        $masuk->execute($jenis['BF-KBL-1C'], TipeMutasiBarang::Pembelian, $tanggal(3), 500, $admin, 'Pembelian kabel drop 1 core');
        $masuk->execute($router, TipeMutasiBarang::Pembelian, $tanggal(3), 10, $admin, 'Pembelian router F670L', $baru, $brand);

        // Pemakaian teknisi.
        $unitBaru = UnitBarang::where('jenis_barang_id', $router->id)->where('kode', 'like', 'MDM-NEW-BF-%')->orderBy('id')->take(3)->pluck('id')->all();
        $keluar->execute($router, TipeMutasiBarang::Pemakaian, $tanggal(5), 0, $admin, 'PEMASANGAN BARU', [$teknisi[0]], unitIds: [$unitBaru[0], $unitBaru[1]]);
        $keluar->execute($router, TipeMutasiBarang::Pemakaian, $tanggal(8), 0, $admin, 'PEMASANGAN BARU', [$teknisi[$teknisi->count() > 1 ? 1 : 0]], unitIds: [$unitBaru[2]]);
        $keluar->execute($jenis['BF-KBL-1C'], TipeMutasiBarang::Pemakaian, $tanggal(5), 150, $admin, 'PEMASANGAN BARU', [$teknisi[0]]);
        $keluar->execute($jenis['BF-RST-001'], TipeMutasiBarang::Pemakaian, $tanggal(5), 2, $admin, 'PEMASANGAN BARU', [$teknisi[0]]);
        $keluar->execute($jenis['BF-PTC-001'], TipeMutasiBarang::Pemakaian, $tanggal(6), 3, $admin, 'TROUBLE', [$teknisi[0]]);
        $keluar->execute($jenis['BF-KLP-001'], TipeMutasiBarang::Pemakaian, $tanggal(6), 40, $admin, 'PENYAMBUNGAN ODP', [$teknisi[$teknisi->count() > 1 ? 1 : 0]]);

        // Barang datang cacat: dihapusbukukan tanpa teknisi.
        $keluar->execute($jenis['BF-SPL-1:8'], TipeMutasiBarang::Rusak, $tanggal(4), 1, $admin, 'DATANG KE ADAAN EROR/CACAT');

        // Satu router terpasang kembali ke gudang sebagai bekas (kode lama tetap, kondisi PGT).
        $masuk->execute($router, TipeMutasiBarang::Pengembalian, $tanggal(9), 0, $admin, 'Dikembalikan dari pelanggan berhenti', $pgt, unitIds: [$unitBaru[0]]);
    }

    /**
     * @return array<string, KategoriBarang>
     */
    private function kategori(): array
    {
        $hasil = [];

        foreach (['MDM' => 'Modem / Router', 'KBL' => 'Kabel', 'PSV' => 'Perangkat Pasif', 'AKS' => 'Aksesori', 'TLS' => 'Peralatan'] as $kode => $nama) {
            $hasil[$kode] = KategoriBarang::firstOrCreate(['kode' => $kode], ['nama' => $nama]);
        }

        return $hasil;
    }

    /**
     * @return list<array{kode: string, nama: string, kategori: string, satuan: string, stok_awal: int, dilacak?: bool}>
     */
    private function jenisBarang(): array
    {
        return [
            ['kode' => 'MDM-F670', 'nama' => 'ROUTER F670L', 'kategori' => 'MDM', 'satuan' => 'unit', 'stok_awal' => 0, 'dilacak' => true],
            ['kode' => 'BF-KBL-1C', 'nama' => 'KABEL FO 1 CORE', 'kategori' => 'KBL', 'satuan' => 'meter', 'stok_awal' => 1000],
            ['kode' => 'BF-PTC-001', 'nama' => 'PATHCORD', 'kategori' => 'AKS', 'satuan' => 'pcs', 'stok_awal' => 67],
            ['kode' => 'BF-RST-001', 'nama' => 'ROSET', 'kategori' => 'AKS', 'satuan' => 'pcs', 'stok_awal' => 111],
            ['kode' => 'BF-KLP-001', 'nama' => 'KLEM PAKU', 'kategori' => 'AKS', 'satuan' => 'pcs', 'stok_awal' => 209],
            ['kode' => 'BF-SLN-001', 'nama' => 'SOLASI HITAM', 'kategori' => 'AKS', 'satuan' => 'pcs', 'stok_awal' => 46],
            ['kode' => 'BF-SPL-1:8', 'nama' => 'SPLITTER 1:8', 'kategori' => 'PSV', 'satuan' => 'pcs', 'stok_awal' => 5],
            ['kode' => 'BF-ODP-TRC-8 C', 'nama' => 'ODP TARMOC 8 CORE', 'kategori' => 'PSV', 'satuan' => 'pcs', 'stok_awal' => 10],
            ['kode' => 'BF-TG-001', 'nama' => 'TANG', 'kategori' => 'TLS', 'satuan' => 'pcs', 'stok_awal' => 10],
            ['kode' => 'BF-CTR-001', 'nama' => 'CUTTER', 'kategori' => 'TLS', 'satuan' => 'pcs', 'stok_awal' => 2],
        ];
    }
}
