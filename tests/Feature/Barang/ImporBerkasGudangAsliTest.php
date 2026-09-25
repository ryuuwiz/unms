<?php

use App\Enums\Barang\TipeMutasiBarang;
use App\Enums\UserStatus;
use App\Models\JenisBarang;
use App\Models\MutasiBarang;
use App\Models\User;
use App\Services\Barang\ImporInventarisService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/**
 * Berkas gudang asli (docs/) dengan dua koreksi data yang disepakati: baris 30 "SPLITTER 1:8"
 * berkode ganda diberi kode BF-SPL-1:8-B, dan STOK AWAL BF-RJ45-B-U menjadi 2.
 */
function berkasGudangTerkoreksi(): string
{
    $spreadsheet = IOFactory::load(base_path('docs/DATA BARANG MASUK & KELUAR BULAN SEPT 2026.xlsx'));
    $data = $spreadsheet->getSheetByName('DATA BARANG');

    for ($baris = 5; $baris <= 64; $baris++) {
        $kode = trim((string) $data->getCell("A{$baris}")->getValue());
        if ($kode === 'BF-SPL-1:8' && str_contains((string) $data->getCell("B{$baris}")->getValue(), 'SPLITTER 1:8')) {
            $data->setCellValue("A{$baris}", 'BF-SPL-1:8-B');
        }
        if ($kode === 'BF-RJ45-B-U') {
            $data->setCellValue("C{$baris}", 2);
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'gudang-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::Active]);
    $this->admin->assignRole('admin');

    $this->teknisi = collect(['ADNAN', 'FERDI', 'ADJI', 'EGI', 'RAFI', 'CHEFI'])
        ->mapWithKeys(function (string $nama) {
            $user = User::factory()->create(['status' => UserStatus::Active, 'name' => ucfirst(strtolower($nama))]);
            $user->assignRole('teknisi');

            return [$nama => $user];
        });

    $this->service = app(ImporInventarisService::class);
});

test('berkas gudang asli bisa diimpor; anggota tim FERDY dipetakan ke user FERDI', function () {
    $path = berkasGudangTerkoreksi();

    expect($this->service->pratinjau($path)['teknisi_tak_dikenal'])->toBe(['ferdy']);

    $peta = ['ferdy' => $this->teknisi['FERDI']->id];
    $pratinjau = $this->service->pratinjau($path, $peta);
    expect($pratinjau['galat_umum'])->toBe([])
        ->and($pratinjau['teknisi_tak_dikenal'])->toBe([]);

    $galat = collect($pratinjau['masalah'])->flatMap(fn ($baris, $sheet) => collect($baris)
        ->filter(fn ($m) => $m['galat'] !== [])
        ->map(fn ($m, $n) => "{$sheet}:{$n} {$m['label']} ".implode('; ', $m['galat'])))->values()->all();

    expect($galat)->toBe([])
        ->and($pratinjau['jumlah'])->toBe(['barang' => 44, 'masuk' => 2, 'keluar' => 54]);

    $hasil = $this->service->simpan($path, $this->admin, $peta);
    expect($hasil['disimpan'])->toBeTrue();

    $stok = fn (string $kode) => JenisBarang::where('kode', $kode)->firstOrFail()->stok();

    expect($stok('BF-RTR-F660'))->toBe(41)
        ->and($stok('BF-RTR-F670'))->toBe(10)
        ->and($stok('BF-RST-001'))->toBe(103)
        ->and($stok('BF-RJ45-B-U'))->toBe(0)
        ->and(JenisBarang::where('kode', 'BF-ODP-TRC-8 C')->exists())->toBeTrue()
        ->and(JenisBarang::where('kode', 'BF-PGTL')->exists())->toBeTrue()
        ->and(JenisBarang::where('kode', 'BF-RTR-F660')->first()->kategori->kode)->toBe('UMUM');

    // Barang masuk tanpa tanggal jatuh di awal periode (PRIODE SEPTEMBER 2026).
    expect(MutasiBarang::where('tipe', TipeMutasiBarang::Pembelian)->pluck('tanggal')->map->toDateString()->unique()->all())->toBe(['2026-09-01']);

    // "DATANG KE ADAAN EROR/CACAT" oleh "All" = barang rusak tanpa teknisi.
    $rusak = MutasiBarang::where('tipe', TipeMutasiBarang::Rusak)->firstOrFail();
    expect($rusak->jumlah)->toBe(9)->and($rusak->teknisi)->toBeEmpty();

    // Tim multi-orang "RAFI,FERDY & ADNAN": semua anggota jadi teknisi penerima.
    $idTim = collect(['RAFI', 'FERDI', 'ADNAN'])->map(fn (string $nama) => $this->teknisi[$nama]->id)->sort()->values()->all();
    expect(MutasiBarang::with('teknisi')->get()->contains(fn (MutasiBarang $m) => $m->teknisi->pluck('id')->sort()->values()->all() === $idTim))->toBeTrue();
});

test('berkas asli tanpa koreksi menunjukkan kode ganda dan stok negatif sebagai galat', function () {
    $pratinjau = $this->service->pratinjau(base_path('docs/DATA BARANG MASUK & KELUAR BULAN SEPT 2026.xlsx'));

    $semuaGalat = collect($pratinjau['masalah'])->flatten()->implode(' | ');

    expect($pratinjau['bisa_disimpan'])->toBeFalse()
        ->and($semuaGalat)->toContain('KODE BARANG BF-SPL-1:8 ganda')
        ->and($semuaGalat)->toContain('Stok BF-RJ45-B-U tidak cukup');
});

test('nama anggota tim yang tidak dikenal bisa dipetakan ke user teknisi', function () {
    $path = berkasImpor([
        'Data Barang' => [['KODE BARANG', 'NAMA BARANG', 'STOK AWAL'], ['BF-RST-001', 'ROSET', 10]],
        'Barang Masuk' => [['NO', 'TANGGAL', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH MASUK']],
        'Barang Keluar' => [['NO', 'TANGGAL', 'KODE BARANG', 'NAMA BARANG', 'JUMLAH KELUAR', 'KETERANGAN', 'TIM TEKNIS'], [1, '05/09/2026', 'BF-RST-001', 'ROSET', 2, 'PEMASANGAN BARU', 'FERDY & ADNAN']],
    ]);

    expect($this->service->pratinjau($path)['teknisi_tak_dikenal'])->toBe(['ferdy']);

    $hasil = $this->service->simpan($path, $this->admin, ['ferdy' => $this->teknisi['FERDI']->id]);

    expect($hasil['disimpan'])->toBeTrue()
        ->and(MutasiBarang::where('tipe', TipeMutasiBarang::Pemakaian)->firstOrFail()->teknisi->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->teknisi['FERDI']->id, $this->teknisi['ADNAN']->id])->sort()->values()->all());
});
