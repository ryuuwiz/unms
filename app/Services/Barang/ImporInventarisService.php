<?php

namespace App\Services\Barang;

use App\Actions\Barang\CatatBarangKeluarAction;
use App\Actions\Barang\CatatBarangMasukAction;
use App\Enums\Barang\TipeMutasiBarang;
use App\Imports\Barang\InventarisImport;
use App\Models\JenisBarang;
use App\Models\KategoriBarang;
use App\Models\KondisiBarang;
use App\Models\MutasiBarang;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\UnitBarang;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Impor Inventaris: migrasi satu kali 3 sheet (Data Barang, Barang Masuk, Barang Keluar) --
 * lihat CONTEXT.md "Impor Inventaris" dan ADR-0057. Validasi memutar ulang seluruh mutasi per
 * barang secara kronologis; penyimpanan semua-atau-tidak lewat action yang sama dengan input manual.
 */
class ImporInventarisService
{
    public const MAKS_BARIS = 5000;

    private const URUT_SALDO_AWAL = 0;

    private const URUT_MASUK = 1;

    private const URUT_KELUAR = 2;

    /** @var array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>> */
    private array $masalah = [];

    /** @var list<string> */
    private array $galatUmum = [];

    public function __construct(
        private CatatBarangMasukAction $catatMasuk,
        private CatatBarangKeluarAction $catatKeluar,
    ) {}

    /**
     * @return array{bisa_disimpan: bool, galat_umum: list<string>, masalah: array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>>, jumlah: array<string, int>, disimpan: bool}
     */
    public function pratinjau(string $path): array
    {
        return $this->hasil($this->rencanakan($path), false);
    }

    /**
     * Validasi ulang lalu simpan bila tanpa galat (satu transaksi).
     *
     * @return array{bisa_disimpan: bool, galat_umum: list<string>, masalah: array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>>, jumlah: array<string, int>, disimpan: bool}
     */
    public function simpan(string $path, User $actor): array
    {
        $rencana = $this->rencanakan($path);

        if (! $this->bisaDisimpan()) {
            return $this->hasil($rencana, false);
        }

        try {
            DB::transaction(function () use ($rencana, $actor) {
                /** @var array<string, JenisBarang> $jenisModel */
                $jenisModel = [];

                foreach ($rencana['barang'] as $kode => $barang) {
                    $jenisModel[$kode] = JenisBarang::updateOrCreate(['kode' => $kode], [
                        'nama' => $barang['nama'],
                        'kategori_barang_id' => $barang['kategori_id'],
                        'satuan' => $barang['satuan'],
                        'dilacak_per_unit' => $barang['dilacak'],
                    ]);
                }

                foreach ($rencana['operasi'] as $op) {
                    $jenis = $jenisModel[$op['jenis_kode']] ??= JenisBarang::with('kategori')->where('kode', $op['jenis_kode'])->firstOrFail();
                    $jenis->loadMissing('kategori');
                    $unitIds = $op['kode_unit'] !== null && $op['tipe'] !== TipeMutasiBarang::SaldoAwal && $op['tipe'] !== TipeMutasiBarang::Pembelian
                        ? [(int) UnitBarang::where('kode', $op['kode_unit'])->value('id')]
                        : [];

                    if ($op['urutan'] === self::URUT_KELUAR) {
                        $this->catatKeluar->execute(
                            jenis: $jenis,
                            tipe: TipeMutasiBarang::Pemakaian,
                            tanggal: $op['tanggal'],
                            jumlah: $op['jumlah'],
                            actor: $actor,
                            keterangan: $op['keterangan'],
                            teknisi: $op['teknisi_id'] ? User::find($op['teknisi_id']) : null,
                            unitIds: $unitIds,
                        );

                        continue;
                    }

                    $this->catatMasuk->execute(
                        jenis: $jenis,
                        tipe: $op['tipe'],
                        tanggal: $op['tanggal'],
                        jumlah: $op['jumlah'],
                        actor: $actor,
                        keterangan: $op['keterangan'],
                        kondisi: $op['kondisi_id'] ? KondisiBarang::find($op['kondisi_id']) : null,
                        brand: $op['brand_id'] ? PengaturanPrefixRegistrasi::find($op['brand_id']) : null,
                        unitIds: $unitIds,
                        kodeUnit: $op['tipe'] === TipeMutasiBarang::Pengembalian ? null : $op['kode_unit'],
                    );
                }

                activity('inventaris')
                    ->causedBy($actor)
                    ->withProperties([
                        'action' => 'impor_inventaris',
                        'jumlah_barang' => count($rencana['barang']),
                        'jumlah_mutasi' => count($rencana['operasi']),
                    ])
                    ->log('Mengimpor data inventaris dari Excel');
            });
        } catch (Throwable $e) {
            report($e);
            $this->galatUmum[] = 'Penyimpanan dibatalkan, tidak ada data yang tersimpan: '.$e->getMessage();

            return $this->hasil($rencana, false);
        }

        return $this->hasil($rencana, true);
    }

    /**
     * @return array{barang: array<string, array{baris: int, nama: string, kategori_id: int, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>, operasi: list<array{tanggal: Carbon, urutan: int, sheet: string, baris: int, jenis_kode: string, tipe: TipeMutasiBarang, jumlah: int, kode_unit: string|null, kondisi_id: int|null, brand_id: int|null, keterangan: string|null, teknisi_id: int|null}>, jumlah: array<string, int>}
     */
    private function rencanakan(string $path): array
    {
        $this->masalah = [];
        $this->galatUmum = [];
        $kosong = ['barang' => [], 'operasi' => [], 'jumlah' => ['barang' => 0, 'masuk' => 0, 'keluar' => 0]];

        if (MutasiBarang::query()->exists()) {
            $this->galatUmum[] = 'Impor hanya untuk migrasi awal: sudah ada mutasi barang di sistem. Jalankan `php artisan inventaris:reset` bila memang ingin mengulang.';

            return $kosong;
        }

        $impor = new InventarisImport;

        try {
            Excel::import($impor, $path);
        } catch (Throwable $e) {
            $this->galatUmum[] = 'Berkas tidak dapat dibaca sebagai Excel: '.$e->getMessage();

            return $kosong;
        }

        foreach ([InventarisImport::SHEET_BARANG => $impor->barang, InventarisImport::SHEET_MASUK => $impor->masuk, InventarisImport::SHEET_KELUAR => $impor->keluar] as $nama => $sheet) {
            if ($sheet->baris === null) {
                $this->galatUmum[] = "Sheet \"{$nama}\" tidak ditemukan. Gunakan template impor.";
            } elseif (count($sheet->baris) > self::MAKS_BARIS) {
                $this->galatUmum[] = "Sheet \"{$nama}\" melebihi ".self::MAKS_BARIS.' baris.';
            }
        }

        if ($this->galatUmum !== []) {
            return $kosong;
        }

        $barang = $this->bacaDataBarang($impor->barang->baris ?? []);
        $operasi = [
            ...$this->bacaMutasi($impor->masuk->baris ?? [], InventarisImport::SHEET_MASUK, $barang),
            ...$this->bacaMutasi($impor->keluar->baris ?? [], InventarisImport::SHEET_KELUAR, $barang),
        ];

        $awalBulan = collect($operasi)->min(fn (array $op) => $op['tanggal']->copy()->startOfMonth()) ?? Carbon::today()->startOfMonth();

        foreach ($barang as $kode => $b) {
            if (! $b['dilacak'] && $b['stok_awal'] > 0) {
                $operasi[] = $this->operasi($awalBulan, self::URUT_SALDO_AWAL, InventarisImport::SHEET_BARANG, $b['baris'], $kode, TipeMutasiBarang::SaldoAwal, $b['stok_awal']);
            }
        }

        usort($operasi, fn (array $a, array $b) => [$a['tanggal']->toDateString(), $a['urutan'], $a['sheet'], $a['baris']]
            <=> [$b['tanggal']->toDateString(), $b['urutan'], $b['sheet'], $b['baris']]);

        $this->putarUlang($operasi, $barang);

        return [
            'barang' => $barang,
            'operasi' => $operasi,
            'jumlah' => [
                'barang' => count($impor->barang->baris ?? []),
                'masuk' => count($impor->masuk->baris ?? []),
                'keluar' => count($impor->keluar->baris ?? []),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{baris: int, nama: string, kategori_id: int, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>
     */
    private function bacaDataBarang(array $rows): array
    {
        $sheet = InventarisImport::SHEET_BARANG;
        $kategori = KategoriBarang::query()->pluck('id', 'kode');
        $ada = JenisBarang::query()->get()->keyBy('kode');
        $hasil = [];

        foreach ($rows as $i => $row) {
            $baris = $i + 2;
            $kode = strtoupper($this->teks($row, ['kode_barang', 'kode']));
            $nama = $this->teks($row, ['nama_barang', 'nama']);
            $label = trim("{$kode} {$nama}") ?: '(kosong)';

            if ($kode === '' || ! preg_match('/^[A-Z0-9-]+$/', $kode)) {
                $this->galat($sheet, $baris, $label, 'KODE BARANG wajib diisi (huruf kapital, angka, tanda hubung).');

                continue;
            }

            if (isset($hasil[$kode])) {
                $this->galat($sheet, $baris, $label, "KODE BARANG {$kode} ganda (juga di baris {$hasil[$kode]['baris']}).");

                continue;
            }

            if ($nama === '') {
                $this->galat($sheet, $baris, $label, 'NAMA BARANG wajib diisi.');
            }

            $kodeKategori = strtoupper($this->teks($row, ['kategori'])) ?: strtoupper(strtok($kode, '-') ?: '');
            $kategoriId = $kategori[$kodeKategori] ?? null;
            if ($kategoriId === null) {
                $this->galat($sheet, $baris, $label, "Kategori \"{$kodeKategori}\" tidak ditemukan di Kategori & Kondisi Barang.");
            }

            $teksDilacak = strtoupper($this->teks($row, ['dilacak']));
            $dilacak = $teksDilacak === ''
                ? (bool) ($ada[$kode]->dilacak_per_unit ?? false)
                : in_array($teksDilacak, ['Y', 'YA', 'YES', 'TRUE', '1'], true);

            $stokAwal = $this->angka($row, ['stok_awal']) ?? 0;
            if ($stokAwal < 0) {
                $this->galat($sheet, $baris, $label, 'STOK AWAL tidak boleh negatif.');
            }
            if ($dilacak && $stokAwal > 0) {
                $this->galat($sheet, $baris, $label, 'Barang dilacak per unit: STOK AWAL harus 0; daftarkan unitnya di sheet Barang Masuk dengan TIPE "SALDO AWAL".');
            }

            $hasil[$kode] = [
                'baris' => $baris,
                'nama' => $nama,
                'kategori_id' => (int) $kategoriId,
                'satuan' => $this->teks($row, ['satuan']) ?: ($ada[$kode]->satuan ?? 'pcs'),
                'dilacak' => $dilacak,
                'stok_awal' => max(0, $stokAwal),
                'stok_akhir_file' => $this->angka($row, ['stok_akhir']),
            ];
        }

        return $hasil;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, array{baris: int, nama: string, kategori_id: int, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>  $barang
     * @return list<array{tanggal: Carbon, urutan: int, sheet: string, baris: int, jenis_kode: string, tipe: TipeMutasiBarang, jumlah: int, kode_unit: string|null, kondisi_id: int|null, brand_id: int|null, keterangan: string|null, teknisi_id: int|null}>
     */
    private function bacaMutasi(array $rows, string $sheet, array $barang): array
    {
        $keluar = $sheet === InventarisImport::SHEET_KELUAR;
        $jenisDb = JenisBarang::query()->with('kategori')->get()->keyBy('kode');
        $kategori = KategoriBarang::query()->get()->keyBy('kode');
        $kondisi = KondisiBarang::query()->get()->keyBy('kode');
        $brand = PengaturanPrefixRegistrasi::query()->get()->keyBy('kode');
        $teknisi = User::role('teknisi')->get()->keyBy(fn (User $u) => mb_strtolower(trim($u->name)));
        $hasil = [];

        foreach ($rows as $i => $row) {
            $baris = $i + 2;
            $kode = strtoupper($this->teks($row, ['kode_barang', 'kode']));
            $nama = $this->teks($row, ['nama_barang', 'nama']);
            $label = trim("{$kode} {$nama}") ?: '(kosong)';
            $galatAwal = count($this->masalah[$sheet][$baris]['galat'] ?? []);

            $tanggal = $this->tanggal($row[$keluar ? 'tanggalbulan' : 'tanggal'] ?? $row['tanggal'] ?? $row['tanggalbulan'] ?? null);
            if ($tanggal === null) {
                $this->galat($sheet, $baris, $label, 'Tanggal kosong/tidak dikenali (gunakan dd/mm/yyyy, yyyy-mm-dd, atau bulan mm/yyyy).');
            }

            $tipe = TipeMutasiBarang::Pemakaian;
            if (! $keluar) {
                $tipe = match (strtoupper(str_replace(['_', '-'], ' ', $this->teks($row, ['tipe'])))) {
                    '', 'PEMBELIAN' => TipeMutasiBarang::Pembelian,
                    'SALDO AWAL' => TipeMutasiBarang::SaldoAwal,
                    'PENGEMBALIAN' => TipeMutasiBarang::Pengembalian,
                    default => null,
                };
                if ($tipe === null) {
                    $this->galat($sheet, $baris, $label, 'TIPE harus SALDO AWAL, PEMBELIAN, atau PENGEMBALIAN.');
                    $tipe = TipeMutasiBarang::Pembelian;
                }
            }

            $teknisiId = null;
            if ($keluar) {
                $namaTeknisi = $this->teks($row, ['teknis', 'teknisi']);
                $teknisiId = $teknisi[mb_strtolower($namaTeknisi)]->id ?? null;
                if ($teknisiId === null) {
                    $this->galat($sheet, $baris, $label, $namaTeknisi === ''
                        ? 'TEKNIS wajib diisi.'
                        : "Teknisi \"{$namaTeknisi}\" tidak ditemukan sebagai user berperan teknisi.");
                }
            }

            $jumlahKolom = $this->angka($row, $keluar ? ['jumlah_keluar', 'jumlah'] : ['jumlah_masuk', 'jumlah']);
            $kodeUnit = null;
            $kondisiId = null;
            $brandId = null;
            $jenisKode = null;

            if (isset($barang[$kode]) || isset($jenisDb[$kode])) {
                $jenisKode = $kode;
                if ($barang[$kode]['dilacak'] ?? $jenisDb[$kode]->dilacak_per_unit) {
                    $this->galat($sheet, $baris, $label, "{$kode} dilacak per unit: tulis kode unit (mis. {$kode}-…) per baris, bukan kode barang.");
                }
                if ($jumlahKolom === null || $jumlahKolom < 1) {
                    $this->galat($sheet, $baris, $label, 'Jumlah wajib diisi minimal 1.');
                }
            } else {
                $segmen = explode('-', $kode);
                $nomor = end($segmen);
                if (! in_array(count($segmen), [3, 4], true) || ! ctype_digit((string) $nomor)) {
                    $this->galat($sheet, $baris, $label, "KODE BARANG \"{$kode}\" bukan kode barang di sheet Data Barang maupun kode unit berformat KATEGORI-KONDISI[-BRAND]-NOMOR.");
                } else {
                    [$kodeKategori, $kodeKondisi] = $segmen;
                    $kodeBrand = count($segmen) === 4 ? $segmen[2] : null;
                    $kondisiId = $kondisi[$kodeKondisi]->id ?? null;

                    // Unit yang dikembalikan tetap berkode lama, kondisinya menjadi PGT (CONTEXT.md "Status Unit Barang").
                    if ($tipe === TipeMutasiBarang::Pengembalian && $kondisiId !== null) {
                        $kondisiId = $kondisi['PGT']->id ?? $kondisiId;
                    }
                    $brandId = $kodeBrand ? ($brand[$kodeBrand]->id ?? null) : null;

                    if (! isset($kategori[$kodeKategori])) {
                        $this->galat($sheet, $baris, $label, "Kategori \"{$kodeKategori}\" pada kode unit tidak dikenal.");
                    }
                    if ($kondisiId === null) {
                        $this->galat($sheet, $baris, $label, "Kondisi \"{$kodeKondisi}\" pada kode unit tidak dikenal.");
                    }
                    if ($kodeBrand && $brandId === null) {
                        $this->galat($sheet, $baris, $label, "Brand \"{$kodeBrand}\" pada kode unit tidak ada di Prefix Registrasi.");
                    }
                    if ($jumlahKolom !== null && $jumlahKolom !== 1) {
                        $this->galat($sheet, $baris, $label, 'Baris kode unit selalu berjumlah 1.');
                    }

                    $jenisKode = $this->jenisUntukUnit($kodeKategori, $nama, $barang, $jenisDb);
                    if ($jenisKode === null) {
                        $this->galat($sheet, $baris, $label, "Tidak ada barang dilacak berkategori {$kodeKategori} bernama \"{$nama}\" (isi NAMA BARANG sesuai sheet Data Barang).");
                    }
                    $kodeUnit = $kode;
                }
            }

            if (count($this->masalah[$sheet][$baris]['galat'] ?? []) > $galatAwal || $tanggal === null || $jenisKode === null) {
                continue;
            }

            $hasil[] = $this->operasi(
                $tanggal,
                $keluar ? self::URUT_KELUAR : ($tipe === TipeMutasiBarang::SaldoAwal ? self::URUT_SALDO_AWAL : self::URUT_MASUK),
                $sheet, $baris, $jenisKode, $tipe,
                $kodeUnit !== null ? 1 : (int) $jumlahKolom,
                $kodeUnit, $kondisiId, $brandId,
                $this->teks($row, ['keterangan']) ?: null,
                $teknisiId,
            );
        }

        return $hasil;
    }

    /**
     * Cocokkan baris kode unit ke jenis barang dilacak: kategori sama dan nama sama (tanpa beda
     * huruf besar/kecil); nama kosong diterima bila kategori itu hanya punya satu jenis dilacak.
     *
     * @param  array<string, array{baris: int, nama: string, kategori_id: int, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>  $barang
     * @param  Collection<string, JenisBarang>  $jenisDb
     */
    private function jenisUntukUnit(string $kodeKategori, string $nama, array $barang, Collection $jenisDb): ?string
    {
        $kategoriId = KategoriBarang::where('kode', $kodeKategori)->value('id');
        $kandidat = [];

        foreach ($jenisDb as $kode => $jenis) {
            if (! isset($barang[$kode]) && $jenis->dilacak_per_unit && $jenis->kategori_barang_id === $kategoriId) {
                $kandidat[$kode] = $jenis->nama;
            }
        }
        foreach ($barang as $kode => $b) {
            if ($b['dilacak'] && $b['kategori_id'] === $kategoriId) {
                $kandidat[$kode] = $b['nama'];
            }
        }

        if ($nama === '') {
            return count($kandidat) === 1 ? (string) array_key_first($kandidat) : null;
        }

        foreach ($kandidat as $kode => $namaJenis) {
            if (mb_strtolower(trim($namaJenis)) === mb_strtolower($nama)) {
                return (string) $kode;
            }
        }

        return null;
    }

    /**
     * Putar ulang mutasi kronologis: stok per barang tidak boleh negatif, unit hanya keluar bila
     * di gudang, pengembalian hanya untuk unit terpasang. Lalu cocokkan STOK AKHIR berkas.
     *
     * @param  list<array{tanggal: Carbon, urutan: int, sheet: string, baris: int, jenis_kode: string, tipe: TipeMutasiBarang, jumlah: int, kode_unit: string|null, kondisi_id: int|null, brand_id: int|null, keterangan: string|null, teknisi_id: int|null}>  $operasi
     * @param  array<string, array{baris: int, nama: string, kategori_id: int, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>  $barang
     */
    private function putarUlang(array $operasi, array $barang): void
    {
        $stok = [];
        $unit = [];

        foreach ($operasi as $op) {
            $kode = $op['jenis_kode'];
            $stok[$kode] ??= 0;
            $label = $op['kode_unit'] ?? $kode;
            $tgl = $op['tanggal']->format('d/m/Y');

            if ($op['kode_unit'] !== null) {
                $status = $unit[$op['kode_unit']] ?? (UnitBarang::where('kode', $op['kode_unit'])->exists() ? 'terdaftar' : null);

                $pesan = match (true) {
                    $op['urutan'] === self::URUT_KELUAR && $status !== 'gudang' => "Unit {$label} tidak ada di gudang pada {$tgl}.",
                    $op['tipe'] === TipeMutasiBarang::Pengembalian && $status !== 'terpasang' => "Unit {$label} belum pernah keluar (terpasang) sebelum {$tgl}, tidak bisa dikembalikan.",
                    $op['urutan'] !== self::URUT_KELUAR && $op['tipe'] !== TipeMutasiBarang::Pengembalian && $status !== null => "Kode unit {$label} ganda/sudah terdaftar.",
                    default => null,
                };

                if ($pesan !== null) {
                    $this->galat($op['sheet'], $op['baris'], $label, $pesan);

                    continue;
                }

                $unit[$op['kode_unit']] = $op['urutan'] === self::URUT_KELUAR ? 'terpasang' : 'gudang';
            }

            if ($op['urutan'] === self::URUT_KELUAR) {
                if ($op['jumlah'] > $stok[$kode]) {
                    $this->galat($op['sheet'], $op['baris'], $label, "Stok {$kode} tidak cukup pada {$tgl} (tersisa {$stok[$kode]}, keluar {$op['jumlah']}).");

                    continue;
                }
                $stok[$kode] -= $op['jumlah'];
            } else {
                $stok[$kode] += $op['jumlah'];
            }
        }

        foreach ($barang as $kode => $b) {
            if ($b['stok_akhir_file'] !== null && $b['stok_akhir_file'] !== ($stok[$kode] ?? 0)) {
                $this->peringatan(InventarisImport::SHEET_BARANG, $b['baris'], "{$kode} {$b['nama']}", "STOK AKHIR di berkas {$b['stok_akhir_file']}, hasil hitung dari mutasi ".($stok[$kode] ?? 0).'.');
            }
        }
    }

    /**
     * @return array{tanggal: Carbon, urutan: int, sheet: string, baris: int, jenis_kode: string, tipe: TipeMutasiBarang, jumlah: int, kode_unit: string|null, kondisi_id: int|null, brand_id: int|null, keterangan: string|null, teknisi_id: int|null}
     */
    private function operasi(
        Carbon $tanggal, int $urutan, string $sheet, int $baris, string $jenisKode, TipeMutasiBarang $tipe, int $jumlah,
        ?string $kodeUnit = null, ?int $kondisiId = null, ?int $brandId = null, ?string $keterangan = null, ?int $teknisiId = null,
    ): array {
        return [
            'tanggal' => $tanggal, 'urutan' => $urutan, 'sheet' => $sheet, 'baris' => $baris, 'jenis_kode' => $jenisKode,
            'tipe' => $tipe, 'jumlah' => $jumlah, 'kode_unit' => $kodeUnit, 'kondisi_id' => $kondisiId, 'brand_id' => $brandId,
            'keterangan' => $keterangan, 'teknisi_id' => $teknisiId,
        ];
    }

    /**
     * Excel date serial, dd/mm/yyyy, yyyy-mm-dd, dd-mm-yyyy; bulan saja (mm/yyyy, yyyy-mm,
     * "Sep 2026", "September 2026") = hari terakhir bulan itu.
     */
    private function tanggal(mixed $nilai): ?Carbon
    {
        if (is_int($nilai) || is_float($nilai)) {
            return $nilai > 0 ? Carbon::instance(ExcelDate::excelToDateTimeObject($nilai))->startOfDay() : null;
        }

        $teks = trim((string) $nilai);

        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $teks, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? Carbon::create((int) $m[3], (int) $m[2], (int) $m[1]) : null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $teks, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]) : null;
        }

        if (preg_match('/^(\d{1,2})[\/-](\d{4})$/', $teks, $m) || preg_match('/^(\d{4})-(\d{1,2})$/', $teks, $m2)) {
            [$bulan, $tahun] = isset($m[2]) ? [(int) $m[1], (int) $m[2]] : [(int) $m2[2], (int) $m2[1]];

            return $bulan >= 1 && $bulan <= 12 ? Carbon::create($tahun, $bulan, 1)?->endOfMonth()->startOfDay() : null;
        }

        if (preg_match('/^([A-Za-z]+)[\s\/-]+(\d{4})$/', $teks, $m)) {
            $bulan = $this->nomorBulan($m[1]);

            return $bulan ? Carbon::create((int) $m[2], $bulan, 1)?->endOfMonth()->startOfDay() : null;
        }

        return null;
    }

    private function nomorBulan(string $nama): ?int
    {
        $awalan = mb_strtolower(substr($nama, 0, 3));
        $peta = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6, 'jul' => 7,
            'agu' => 8, 'agt' => 8, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'des' => 12, 'dec' => 12];

        return $peta[$awalan] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $kolom
     */
    private function teks(array $row, array $kolom): string
    {
        foreach ($kolom as $k) {
            if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                return trim((string) $row[$k]);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $kolom
     */
    private function angka(array $row, array $kolom): ?int
    {
        $teks = $this->teks($row, $kolom);

        return $teks === '' ? null : (is_numeric($teks) ? (int) round((float) $teks) : -1);
    }

    private function galat(string $sheet, int $baris, string $label, string $pesan): void
    {
        $this->masalah[$sheet][$baris] ??= ['label' => $label, 'galat' => [], 'peringatan' => []];
        $this->masalah[$sheet][$baris]['galat'][] = $pesan;
    }

    private function peringatan(string $sheet, int $baris, string $label, string $pesan): void
    {
        $this->masalah[$sheet][$baris] ??= ['label' => $label, 'galat' => [], 'peringatan' => []];
        $this->masalah[$sheet][$baris]['peringatan'][] = $pesan;
    }

    private function bisaDisimpan(): bool
    {
        if ($this->galatUmum !== []) {
            return false;
        }

        foreach ($this->masalah as $baris) {
            foreach ($baris as $m) {
                if ($m['galat'] !== []) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array{barang: array<string, mixed>, operasi: list<mixed>, jumlah: array<string, int>}  $rencana
     * @return array{bisa_disimpan: bool, galat_umum: list<string>, masalah: array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>>, jumlah: array<string, int>, disimpan: bool}
     */
    private function hasil(array $rencana, bool $disimpan): array
    {
        foreach (array_keys($this->masalah) as $sheet) {
            ksort($this->masalah[$sheet]);
        }

        return [
            'bisa_disimpan' => $this->bisaDisimpan(),
            'galat_umum' => $this->galatUmum,
            'masalah' => $this->masalah,
            'jumlah' => $rencana['jumlah'],
            'disimpan' => $disimpan,
        ];
    }
}
