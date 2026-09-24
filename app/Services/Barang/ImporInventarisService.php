<?php

namespace App\Services\Barang;

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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
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

    public const SHEET_BARANG = 'Data Barang';

    public const SHEET_MASUK = 'Barang Masuk';

    public const SHEET_KELUAR = 'Barang Keluar';

    private const URUT_SALDO_AWAL = 0;

    private const URUT_MASUK = 1;

    private const URUT_KELUAR = 2;

    /** @var array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>> */
    private array $masalah = [];

    /** @var list<string> */
    private array $galatUmum = [];

    /** @var array<string, true> nama teknisi (huruf kecil) yang belum cocok dengan user teknisi */
    private array $teknisiTakDikenal = [];

    /** @var array<string, int> */
    private array $petaTeknisi = [];

    private const KATEGORI_BAWAAN = 'UMUM';

    public function __construct(
        private CatatBarangMasukAction $catatMasuk,
        private CatatBarangKeluarAction $catatKeluar,
    ) {}

    /**
     * @param  array<string, int>  $petaTeknisi  nama teknisi di berkas (huruf kecil) => user id, dari pemetaan di pratinjau
     * @return array{bisa_disimpan: bool, galat_umum: list<string>, masalah: array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>>, jumlah: array<string, int>, disimpan: bool, teknisi_tak_dikenal: list<string>}
     */
    public function pratinjau(string $path, array $petaTeknisi = []): array
    {
        return $this->hasil($this->rencanakan($path, $petaTeknisi), false);
    }

    /**
     * Validasi ulang lalu simpan bila tanpa galat (satu transaksi).
     *
     * @param  array<string, int>  $petaTeknisi
     * @return array{bisa_disimpan: bool, galat_umum: list<string>, masalah: array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>>, jumlah: array<string, int>, disimpan: bool, teknisi_tak_dikenal: list<string>}
     */
    public function simpan(string $path, User $actor, array $petaTeknisi = []): array
    {
        $rencana = $this->rencanakan($path, $petaTeknisi);

        if (! $this->bisaDisimpan()) {
            return $this->hasil($rencana, false);
        }

        try {
            DB::transaction(function () use ($rencana, $actor) {
                /** @var array<string, JenisBarang> $jenisModel */
                $jenisModel = [];

                $kategoriBawaan = null;

                foreach ($rencana['barang'] as $kode => $barang) {
                    // Kategori tak disebut & prefix kode bukan kategori: masuk kategori UMUM (dibuat bila perlu).
                    $kategoriId = $barang['kategori_id']
                        ?? ($kategoriBawaan ??= KategoriBarang::firstOrCreate(['kode' => self::KATEGORI_BAWAAN], ['nama' => 'Umum'])->id);

                    $jenisModel[$kode] = JenisBarang::updateOrCreate(['kode' => $kode], [
                        'nama' => $barang['nama'],
                        'kategori_barang_id' => $kategoriId,
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
                            tipe: $op['tipe'],
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
     * @param  array<string, int>  $petaTeknisi
     * @return array{barang: array<string, array{baris: int, nama: string, kategori_id: int|null, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>, operasi: list<array{tanggal: Carbon, urutan: int, sheet: string, baris: int, jenis_kode: string, tipe: TipeMutasiBarang, jumlah: int, kode_unit: string|null, kondisi_id: int|null, brand_id: int|null, keterangan: string|null, teknisi_id: int|null}>, jumlah: array<string, int>}
     */
    private function rencanakan(string $path, array $petaTeknisi = []): array
    {
        $this->masalah = [];
        $this->galatUmum = [];
        $this->teknisiTakDikenal = [];
        $this->petaTeknisi = array_change_key_case($petaTeknisi, CASE_LOWER);
        $kosong = ['barang' => [], 'operasi' => [], 'jumlah' => ['barang' => 0, 'masuk' => 0, 'keluar' => 0]];

        if (MutasiBarang::query()->exists()) {
            $this->galatUmum[] = 'Impor hanya untuk migrasi awal: sudah ada mutasi barang di sistem. Jalankan `php artisan inventaris:reset` bila memang ingin mengulang.';

            return $kosong;
        }

        try {
            $mentah = $this->bacaBerkas($path);
        } catch (Throwable $e) {
            $this->galatUmum[] = 'Berkas tidak dapat dibaca sebagai Excel: '.$e->getMessage();

            return $kosong;
        }

        foreach ($mentah as $nama => $baris) {
            if ($baris === null) {
                $this->galatUmum[] = "Sheet \"{$nama}\" tidak ditemukan. Gunakan template impor.";
            }
        }

        if ($this->galatUmum !== []) {
            return $kosong;
        }

        $barisBarang = $this->barisBerheader($mentah[self::SHEET_BARANG] ?? [], self::SHEET_BARANG);
        $barisMasuk = $this->barisBerheader($mentah[self::SHEET_MASUK] ?? [], self::SHEET_MASUK);
        $barisKeluar = $this->barisBerheader($mentah[self::SHEET_KELUAR] ?? [], self::SHEET_KELUAR);

        if ($barisBarang === null || $barisMasuk === null || $barisKeluar === null) {
            return $kosong;
        }

        // Periode dari judul berkas ("PRIODE SEPTEMBER 2026"), selain itu bulan paling awal yang bertanggal.
        $periode = $this->periodeDariJudul([...($mentah[self::SHEET_BARANG] ?? []), ...($mentah[self::SHEET_MASUK] ?? []), ...($mentah[self::SHEET_KELUAR] ?? [])])
            ?? $this->bulanPalingAwal([...$barisMasuk, ...$barisKeluar]);

        $barang = $this->bacaDataBarang($barisBarang);
        $operasi = [
            ...$this->bacaMutasi($barisMasuk, self::SHEET_MASUK, $barang, $periode),
            ...$this->bacaMutasi($barisKeluar, self::SHEET_KELUAR, $barang, $periode),
        ];

        $awalBulan = $periode ?? collect($operasi)->min(fn (array $op) => $op['tanggal']->copy()->startOfMonth()) ?? Carbon::today()->startOfMonth();

        foreach ($barang as $kode => $b) {
            if (! $b['dilacak'] && $b['stok_awal'] > 0) {
                $operasi[] = $this->operasi($awalBulan, self::URUT_SALDO_AWAL, self::SHEET_BARANG, $b['baris'], $kode, TipeMutasiBarang::SaldoAwal, $b['stok_awal']);
            }
        }

        usort($operasi, fn (array $a, array $b) => [$a['tanggal']->toDateString(), $a['urutan'], $a['sheet'], $a['baris']]
            <=> [$b['tanggal']->toDateString(), $b['urutan'], $b['sheet'], $b['baris']]);

        $this->putarUlang($operasi, $barang);

        return [
            'barang' => $barang,
            'operasi' => $operasi,
            'jumlah' => [
                'barang' => count($barisBarang),
                'masuk' => count($barisMasuk),
                'keluar' => count($barisKeluar),
            ],
        ];
    }

    /**
     * @param  list<array{baris: int, data: array<string, mixed>}>  $rows
     * @return array<string, array{baris: int, nama: string, kategori_id: int|null, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>
     */
    private function bacaDataBarang(array $rows): array
    {
        $sheet = self::SHEET_BARANG;
        $kategori = KategoriBarang::query()->pluck('id', 'kode');
        $ada = JenisBarang::query()->get()->keyBy('kode');
        $hasil = [];

        foreach ($rows as ['baris' => $baris, 'data' => $row]) {
            $kode = JenisBarang::normalkanKode($this->teks($row, ['kode_barang', 'kode']));
            $nama = $this->teks($row, ['nama_barang', 'nama']);
            $label = trim("{$kode} {$nama}") ?: '(kosong)';

            if ($kode === '' || ! JenisBarang::kodeValid($kode)) {
                $this->galat($sheet, $baris, $label, 'KODE BARANG wajib diisi (huruf, angka, spasi, tanda - : / .).');

                continue;
            }

            if (isset($hasil[$kode])) {
                $this->galat($sheet, $baris, $label, "KODE BARANG {$kode} ganda (juga di baris {$hasil[$kode]['baris']}).");

                continue;
            }

            if ($nama === '') {
                $this->galat($sheet, $baris, $label, 'NAMA BARANG wajib diisi.');
            }

            $kolomKategori = strtoupper($this->teks($row, ['kategori']));
            $kategoriId = $kolomKategori !== ''
                ? ($kategori[$kolomKategori] ?? null)
                : ($kategori[strtoupper(strtok($kode, '-') ?: '')] ?? $kategori[self::KATEGORI_BAWAAN] ?? null);

            if ($kolomKategori !== '' && $kategoriId === null) {
                $this->galat($sheet, $baris, $label, "Kategori \"{$kolomKategori}\" tidak ditemukan di Kategori & Kondisi Barang.");
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
                'kategori_id' => $kategoriId === null ? null : (int) $kategoriId,
                'satuan' => $this->teks($row, ['satuan']) ?: ($ada[$kode]->satuan ?? 'pcs'),
                'dilacak' => $dilacak,
                'stok_awal' => max(0, $stokAwal),
                'stok_akhir_file' => $this->angka($row, ['stok_akhir']),
            ];
        }

        return $hasil;
    }

    /**
     * @param  list<array{baris: int, data: array<string, mixed>}>  $rows
     * @param  array<string, array{baris: int, nama: string, kategori_id: int|null, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>  $barang
     * @return list<array{tanggal: Carbon, urutan: int, sheet: string, baris: int, jenis_kode: string, tipe: TipeMutasiBarang, jumlah: int, kode_unit: string|null, kondisi_id: int|null, brand_id: int|null, keterangan: string|null, teknisi_id: int|null}>
     */
    private function bacaMutasi(array $rows, string $sheet, array $barang, ?Carbon $periode): array
    {
        $keluar = $sheet === self::SHEET_KELUAR;
        $jenisDb = JenisBarang::query()->with('kategori')->get()->keyBy('kode');
        $kategori = KategoriBarang::query()->get()->keyBy('kode');
        $kondisi = KondisiBarang::query()->get()->keyBy('kode');
        $brand = PengaturanPrefixRegistrasi::query()->get()->keyBy('kode');
        $teknisi = User::role('teknisi')->get()->keyBy(fn (User $u) => mb_strtolower(trim($u->name)));
        $hasil = [];

        foreach ($rows as ['baris' => $baris, 'data' => $row]) {
            $kode = JenisBarang::normalkanKode($this->teks($row, ['kode_barang', 'kode']));
            $nama = $this->teks($row, ['nama_barang', 'nama']);
            $label = trim("{$kode} {$nama}") ?: '(kosong)';
            $keterangan = $this->teks($row, ['keterangan']);
            $galatAwal = count($this->masalah[$sheet][$baris]['galat'] ?? []);

            $nilaiTanggal = $row['tanggalbulan'] ?? $row['tanggal'] ?? null;
            $tanggal = $this->tanggal($nilaiTanggal);
            if ($tanggal === null && ! $keluar && trim((string) $nilaiTanggal) === '' && $periode !== null) {
                // Barang masuk tanpa tanggal dianggap masuk di awal periode berkas, sebelum barang keluar.
                $tanggal = $periode->copy()->startOfMonth();
            }
            if ($tanggal === null) {
                $this->galat($sheet, $baris, $label, 'Tanggal kosong/tidak dikenali (gunakan dd/mm/yyyy, yyyy-mm-dd, atau bulan mm/yyyy).');
            }

            $tipe = TipeMutasiBarang::Pemakaian;
            $teknisiId = null;

            if ($keluar) {
                // Barang datang cacat/rusak = dihapusbukukan, bukan dipakai teknisi.
                if (preg_match('/\b(EROR|ERROR|CACAT|RUSAK)\b/i', $keterangan)) {
                    $tipe = TipeMutasiBarang::Rusak;
                }

                $tim = $this->teks($row, ['teknis', 'tim_teknis', 'teknisi']);
                $namaPertama = trim((preg_split('/\s*(?:,|&|\/|\bdan\b)\s*/i', $tim) ?: [''])[0]);

                if ($tim !== '' && mb_strtolower($namaPertama) !== 'all') {
                    $kunci = mb_strtolower($namaPertama);
                    $teknisiId = $this->petaTeknisi[$kunci] ?? $teknisi[$kunci]->id ?? null;

                    if ($teknisiId === null) {
                        $this->teknisiTakDikenal[$kunci] = true;
                        $this->galat($sheet, $baris, $label, "Teknisi \"{$namaPertama}\" tidak ditemukan sebagai user berperan teknisi; petakan di pratinjau atau buat user-nya.");
                    }
                }

                if ($teknisiId === null && $tipe !== TipeMutasiBarang::Rusak && ($tim === '' || mb_strtolower($namaPertama) === 'all')) {
                    $this->galat($sheet, $baris, $label, 'TEKNIS wajib diisi nama teknisi (hanya barang rusak/cacat yang boleh tanpa teknisi).');
                }

                // Tim lebih dari satu orang: teknisi pertama jadi penerima, tim lengkap dicatat di keterangan.
                if ($tim !== '' && mb_strtolower($tim) !== mb_strtolower($namaPertama)) {
                    $keterangan = trim($keterangan.' (Tim: '.$tim.')');
                }
            } else {
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

            $jumlahKolom = $this->angka($row, $keluar ? ['jumlah_keluar', 'jumlah'] : ['jumlah_masuk', 'jumlah']);
            $kodeUnit = null;
            $kondisiId = null;
            $brandId = null;
            $jenisKode = null;

            $dilacakBerkas = $barang[$kode]['dilacak'] ?? null;
            $dilacakDb = $jenisDb[$kode]->dilacak_per_unit ?? null;

            if ($dilacakBerkas !== null || $dilacakDb !== null) {
                $jenisKode = $kode;
                if ($dilacakBerkas ?? $dilacakDb) {
                    $this->galat($sheet, $baris, $label, "{$kode} dilacak per unit: tulis kode unit (mis. {$kode}-…) per baris, bukan kode barang.");
                }
                if ($jumlahKolom === null || $jumlahKolom < 1) {
                    $this->galat($sheet, $baris, $label, 'Jumlah wajib diisi minimal 1.');
                }
            } else {
                $segmen = explode('-', $kode);
                $nomor = end($segmen);
                if (! in_array(count($segmen), [3, 4], true) || ! ctype_digit((string) $nomor)) {
                    $this->galat($sheet, $baris, $label, "KODE BARANG \"{$kode}\" tidak ada di sheet Data Barang dan bukan kode unit berformat KATEGORI-KONDISI[-BRAND]-NOMOR.");
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
                $keterangan !== '' ? mb_substr($keterangan, 0, 500) : null,
                $teknisiId,
            );
        }

        return $hasil;
    }

    /**
     * Baca 3 sheet (nama tanpa beda huruf besar/kecil) sebagai baris mentah mulai baris 1, memakai nilai
     * rumus yang terakhir dihitung Excel. Dibaca langsung lewat PhpSpreadsheet, bukan Laravel Excel:
     * berkas gudang asli memakai VLOOKUP/SUMIF lintas sheet pada Excel Table, dan Laravel Excel
     * melepas sheet satu per satu sehingga rumus lintas sheet gagal dihitung.
     *
     * @return array<string, list<array<int, mixed>>|null>
     */
    private function bacaBerkas(string $path): array
    {
        $workbook = IOFactory::load($path);
        $hasil = [self::SHEET_BARANG => null, self::SHEET_MASUK => null, self::SHEET_KELUAR => null];

        foreach ($workbook->getAllSheets() as $sheet) {
            $nama = collect(array_keys($hasil))->first(fn (string $n) => mb_strtolower($n) === mb_strtolower(trim($sheet->getTitle())));

            if ($nama === null || $hasil[$nama] !== null) {
                continue;
            }

            $baris = [];
            foreach ($sheet->getRowIterator() as $row) {
                $nilai = [];
                foreach ($row->getCellIterator() as $cell) {
                    $v = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                    $nilai[] = $v instanceof RichText ? $v->getPlainText() : $v;
                }
                $baris[] = $nilai;
            }

            $hasil[$nama] = $baris;
        }

        $workbook->disconnectWorksheets();

        return $hasil;
    }

    /**
     * Cari baris header (yang memuat "KODE BARANG") di 10 baris pertama, lalu ubah baris sesudahnya
     * menjadi array berkunci slug header ("TIM TEKNIS" -> `tim_teknis`). Baris templat kosong (hanya
     * berisi rumus, tanpa kode/nama/jumlah/tanggal) dilewati.
     *
     * @param  list<array<int, mixed>>  $mentah
     * @return list<array{baris: int, data: array<string, mixed>}>|null null bila header tak ditemukan / terlalu banyak baris
     */
    private function barisBerheader(array $mentah, string $sheet): ?array
    {
        $indeksHeader = null;
        $kolom = [];

        foreach (array_slice($mentah, 0, 10, true) as $i => $baris) {
            $slug = array_map(fn ($nilai) => Str::slug(trim((string) $nilai), '_'), $baris);
            if (in_array('kode_barang', $slug, true)) {
                $indeksHeader = $i;
                $kolom = $slug;
                break;
            }
        }

        if ($indeksHeader === null) {
            $this->galatUmum[] = "Sheet \"{$sheet}\": baris header dengan kolom KODE BARANG tidak ditemukan di 10 baris pertama.";

            return null;
        }

        $hasil = [];

        foreach (array_slice($mentah, $indeksHeader + 1, null, true) as $i => $baris) {
            $data = [];
            foreach ($kolom as $posisi => $nama) {
                if ($nama !== '' && ! array_key_exists($nama, $data)) {
                    $data[$nama] = $baris[$posisi] ?? null;
                }
            }

            $kosong = collect(['kode_barang', 'nama_barang', 'stok_awal', 'jumlah_masuk', 'jumlah_keluar', 'tanggal', 'tanggalbulan'])
                ->every(fn (string $k) => in_array(trim((string) ($data[$k] ?? '')), ['', '0'], true));

            if (! $kosong) {
                $hasil[] = ['baris' => $i + 1, 'data' => $data];
            }
        }

        if (count($hasil) > self::MAKS_BARIS) {
            $this->galatUmum[] = "Sheet \"{$sheet}\" melebihi ".self::MAKS_BARIS.' baris.';

            return null;
        }

        return $hasil;
    }

    /**
     * "PRIODE SEPTEMBER 2026" / "PERIODE 09/2026" pada baris judul.
     *
     * @param  list<array<int, mixed>>  $mentah
     */
    private function periodeDariJudul(array $mentah): ?Carbon
    {
        foreach ($mentah as $baris) {
            foreach ($baris as $nilai) {
                if (preg_match('/\bP(?:E)?RIODE\s+(.+)$/i', trim((string) $nilai), $m)) {
                    $tanggal = $this->tanggal(trim($m[1]));

                    if ($tanggal !== null) {
                        return $tanggal->copy()->startOfMonth();
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array{baris: int, data: array<string, mixed>}>  $rows
     */
    private function bulanPalingAwal(array $rows): ?Carbon
    {
        return collect($rows)
            ->map(fn (array $r) => $this->tanggal($r['data']['tanggalbulan'] ?? $r['data']['tanggal'] ?? null))
            ->filter()
            ->min()
            ?->copy()->startOfMonth();
    }

    /**
     * Cocokkan baris kode unit ke jenis barang dilacak: kategori sama dan nama sama (tanpa beda
     * huruf besar/kecil); nama kosong diterima bila kategori itu hanya punya satu jenis dilacak.
     *
     * @param  array<string, array{baris: int, nama: string, kategori_id: int|null, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>  $barang
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
     * @param  array<string, array{baris: int, nama: string, kategori_id: int|null, satuan: string, dilacak: bool, stok_awal: int, stok_akhir_file: int|null}>  $barang
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
                $this->peringatan(self::SHEET_BARANG, $b['baris'], "{$kode} {$b['nama']}", "STOK AKHIR di berkas {$b['stok_akhir_file']}, hasil hitung dari mutasi ".($stok[$kode] ?? 0).'.');
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
     * @return array{bisa_disimpan: bool, galat_umum: list<string>, masalah: array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>>, jumlah: array<string, int>, disimpan: bool, teknisi_tak_dikenal: list<string>}
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
            'teknisi_tak_dikenal' => array_keys($this->teknisiTakDikenal),
        ];
    }
}
