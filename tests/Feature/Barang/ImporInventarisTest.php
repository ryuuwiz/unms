<?php

use App\Enums\Barang\StatusUnitBarang;
use App\Enums\Barang\TipeMutasiBarang;
use App\Enums\UserStatus;
use App\Livewire\Barang\Impor;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use App\Models\MutasiBarang;
use App\Models\UnitBarang;
use App\Models\User;
use App\Services\Barang\ImporInventarisService;
use Database\Seeders\PengaturanPrefixRegistrasiSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, PengaturanPrefixRegistrasiSeeder::class]);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = User::factory()->create(['status' => UserStatus::Active, 'name' => 'Budi Teknisi']);
    $this->teknisi->assignRole('teknisi');

    KategoriBarang::create(['kode' => 'KBL', 'nama' => 'Kabel']);
    $this->service = app(ImporInventarisService::class);
});

/**
 * @param  array<string, list<list<mixed>>>  $sheets  nama sheet => baris (baris pertama = header)
 */
function berkasImpor(array $sheets): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach ($sheets as $nama => $baris) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($nama);
        $sheet->fromArray($baris);
    }

    $path = tempnam(sys_get_temp_dir(), 'uji-impor-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function berkasValid(array $ubahKeluar = []): string
{
    return berkasImpor([
        'Data Barang' => [
            ['KODE BARANG', 'NAMA BARANG', 'STOK AWAL', 'MASUK', 'KELUAR', 'STOK AKHIR', 'KATEGORI', 'SATUAN', 'DILACAK'],
            ['KBL-DROP', 'Kabel Drop', 1000, 500, 300, 1200, '', 'meter', 'N'],
            ['MDM-F609', 'Modem ZTE F609', 0, 2, 1, 1, '', 'unit', 'Y'],
        ],
        'Barang Masuk' => [
            ['NO', 'TANGGAL', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH MASUK', 'TIPE', 'KETERANGAN'],
            [1, '05/09/2026', 'KBL-DROP', 'Kabel Drop', 500, 'PEMBELIAN', ''],
            [2, '01/09/2026', 'MDM-NEW-BF-240', 'Modem ZTE F609', 1, 'SALDO AWAL', 'Stok lama'],
            [3, '2026-09-06', 'MDM-NEW-BF-241', 'Modem ZTE F609', '', '', ''],
        ],
        'Barang Keluar' => [
            ['NO', 'TANGGAL/BULAN', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH KELUAR', 'KETERANGAN', 'TEKNIS'],
            ...($ubahKeluar ?: [
                [1, '09/2026', 'KBL-DROP', 'Kabel Drop', 300, 'Pemasangan', 'budi teknisi'],
                [2, '10/09/2026', 'MDM-NEW-BF-240', 'Modem ZTE F609', 1, 'TCK-1', 'Budi Teknisi'],
            ]),
        ],
    ]);
}

test('impor 3 sheet valid tersimpan: saldo awal, unit berkode lama, keluar per unit, dan penghitung kode dimajukan', function () {
    $hasil = $this->service->simpan(berkasValid(), $this->admin);

    expect($hasil['disimpan'])->toBeTrue()
        ->and($hasil['galat_umum'])->toBe([]);

    $kabel = JenisBarang::where('kode', 'KBL-DROP')->firstOrFail();
    $modem = JenisBarang::where('kode', 'MDM-F609')->firstOrFail();

    expect($kabel->stok())->toBe(1200)
        ->and($kabel->kategori->kode)->toBe('KBL')
        ->and($modem->dilacak_per_unit)->toBeTrue()
        ->and($modem->stok())->toBe(1)
        ->and(MutasiBarang::where('tipe', TipeMutasiBarang::SaldoAwal)->count())->toBe(2)
        ->and(UnitBarang::where('kode', 'MDM-NEW-BF-240')->value('status'))->toBe(StatusUnitBarang::Terpasang)
        ->and(UnitBarang::where('kode', 'MDM-NEW-BF-241')->value('status'))->toBe(StatusUnitBarang::DiGudang)
        ->and(DB::table('kode_barang_counter')->where('prefix', 'MDM-NEW-BF')->value('nomor_terakhir'))->toBe(241);

    // Keluar dengan tanggal bulan-saja jatuh di hari terakhir bulan itu, setelah semua barang masuk.
    expect(MutasiBarang::where('jenis_barang_id', $kabel->id)->where('tipe', TipeMutasiBarang::Pemakaian)->value('tanggal')->toDateString())->toBe('2026-09-30');
});

test('galat baris: stok negatif, teknisi tak dikenal, segmen kode tak dikenal, dan barang dilacak dengan stok awal', function () {
    $path = berkasImpor([
        'Data Barang' => [
            ['KODE BARANG', 'NAMA BARANG', 'STOK AWAL', 'STOK AKHIR', 'DILACAK'],
            ['KBL-DROP', 'Kabel Drop', 10, '', 'N'],
            ['MDM-F609', 'Modem ZTE F609', 5, '', 'Y'],
        ],
        'Barang Masuk' => [
            ['NO', 'TANGGAL', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH MASUK', 'TIPE'],
            [1, '01/09/2026', 'MDM-XXX-BF-1', 'Modem ZTE F609', 1, 'PEMBELIAN'],
        ],
        'Barang Keluar' => [
            ['NO', 'TANGGAL/BULAN', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH KELUAR', 'KETERANGAN', 'TEKNIS'],
            [1, '02/09/2026', 'KBL-DROP', 'Kabel Drop', 50, '', 'Budi Teknisi'],
            [2, '03/09/2026', 'KBL-DROP', 'Kabel Drop', 1, '', 'Orang Asing'],
        ],
    ]);

    $hasil = $this->service->pratinjau($path);
    $galat = fn (string $sheet, int $baris) => implode(' | ', $hasil['masalah'][$sheet][$baris]['galat'] ?? []);

    expect($hasil['bisa_disimpan'])->toBeFalse()
        ->and($galat('Data Barang', 3))->toContain('STOK AWAL harus 0')
        ->and($galat('Barang Masuk', 2))->toContain('Kondisi "XXX"')
        ->and($galat('Barang Keluar', 2))->toContain('Stok KBL-DROP tidak cukup')
        ->and($galat('Barang Keluar', 3))->toContain('Teknisi "Orang Asing" tidak ditemukan');

    // Tidak ada yang tersimpan saat ada galat.
    $this->service->simpan($path, $this->admin);
    expect(JenisBarang::count())->toBe(0)->and(MutasiBarang::count())->toBe(0);
});

test('selisih STOK AKHIR berkas hanya menjadi peringatan dan tetap bisa disimpan', function () {
    $path = berkasImpor([
        'Data Barang' => [
            ['KODE BARANG', 'NAMA BARANG', 'STOK AWAL', 'STOK AKHIR'],
            ['KBL-DROP', 'Kabel Drop', 100, 999],
        ],
        'Barang Masuk' => [['NO', 'TANGGAL', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH MASUK']],
        'Barang Keluar' => [['NO', 'TANGGAL/BULAN', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH KELUAR', 'KETERANGAN', 'TEKNIS']],
    ]);

    $hasil = $this->service->pratinjau($path);

    expect($hasil['bisa_disimpan'])->toBeTrue()
        ->and($hasil['masalah']['Data Barang'][2]['peringatan'][0])->toContain('STOK AKHIR di berkas 999, hasil hitung dari mutasi 100');
});

test('unit tidak bisa keluar sebelum masuk dan sheet yang hilang ditolak', function () {
    $hasil = $this->service->pratinjau(berkasValid([
        [1, '31/08/2026', 'MDM-NEW-BF-241', 'Modem ZTE F609', 1, '', 'Budi Teknisi'],
    ]));

    expect($hasil['masalah']['Barang Keluar'][2]['galat'][0])->toContain('Unit MDM-NEW-BF-241 tidak ada di gudang pada 31/08/2026');

    $tanpaSheet = $this->service->pratinjau(berkasImpor(['Data Barang' => [['KODE BARANG', 'NAMA BARANG']]]));
    expect($tanpaSheet['galat_umum'])->toContain('Sheet "Barang Masuk" tidak ditemukan. Gunakan template impor.');
});

test('impor ditolak bila sudah ada mutasi, dan inventaris:reset membuka kembali impor', function () {
    $this->service->simpan(berkasValid(), $this->admin);

    $ulang = $this->service->pratinjau(berkasValid());
    expect($ulang['bisa_disimpan'])->toBeFalse()
        ->and($ulang['galat_umum'][0])->toContain('sudah ada mutasi barang');

    $this->artisan('inventaris:reset')->expectsQuestion('Ketik RESET untuk melanjutkan', 'tidak')->assertFailed();
    expect(MutasiBarang::count())->toBeGreaterThan(0);

    $this->artisan('inventaris:reset', ['--force' => true])->assertSuccessful();
    expect(MutasiBarang::count())->toBe(0)
        ->and(UnitBarang::count())->toBe(0)
        ->and(DB::table('kode_barang_counter')->count())->toBe(0)
        ->and(JenisBarang::count())->toBe(2)
        ->and($this->service->pratinjau(berkasValid())['bisa_disimpan'])->toBeTrue();
});

test('halaman impor: template terunduh, pratinjau lalu simpan lewat Livewire, teknisi ditolak', function () {
    Livewire::actingAs($this->admin)
        ->test(Impor::class)
        ->call('unduhTemplate')
        ->assertFileDownloaded('Template-Impor-Inventaris.xlsx');

    $berkas = UploadedFile::fake()->createWithContent('inventaris.xlsx', file_get_contents(berkasValid()));

    Livewire::actingAs($this->admin)
        ->test(Impor::class)
        ->set('berkas', $berkas)
        ->call('pratinjau')
        ->assertSet('hasil.bisa_disimpan', true)
        ->call('simpan')
        ->assertSet('hasil.disimpan', true);

    expect(JenisBarang::where('kode', 'KBL-DROP')->exists())->toBeTrue();

    Livewire::actingAs($this->teknisi)->test(Impor::class)->assertForbidden();
});
