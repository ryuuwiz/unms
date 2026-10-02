<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\Enums\AksiPelunasanSusulan;
use App\Enums\StatusPemindaian;
use App\Jobs\PaymentGateway\PindaiPelunasanSusulanJob;
use App\Models\User;
use App\Support\Rupiah;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pemindaian Pelunasan Susulan untuk satu rentang waktu bayar: jadwal harian, command, dan halaman
 * Pelunasan Susulan memakai kunci yang sama sehingga tidak pernah berjalan bersamaan. Pemindaian
 * dari halaman berjalan di antrean dan hasilnya disimpan di cache sekitar 1 jam.
 *
 * @phpstan-type BarisPemindaian array{koneksi: string, invoice: string, pelanggan: string, nominal: string, dibayar: string, status_lokal: string, aksi: string, keterangan: string}
 * @phpstan-type Pemindaian array{id: string, status: StatusPemindaian, mode: string, dari: string, oleh: string, dimulai_pada: string, pemilik_kunci: string, diproses_pada?: string, selesai_pada?: string, baris?: list<BarisPemindaian>, ringkasan?: string, galat?: string}
 */
class PemindaianPelunasanSusulan
{
    public const KUNCI = 'pelunasan-susulan:pemindaian';

    /**
     * Lebih panjang dari batas waktu job ditambah waktu tunggu antrean, agar kunci tidak habis
     * di tengah pemindaian. Pemindaian yang belum diambil worker bisa dibatalkan dari halaman.
     */
    public const DETIK_KUNCI = 7200;

    public const DETIK_HASIL = 3600;

    /** Pemindaian yang belum diambil worker selama ini ditandai "menunggu antrean" dan boleh dibatalkan. */
    public const DETIK_MENUNGGU_WORKER = 120;

    /** Kolom tabel hasil (kunci baris => judul) untuk halaman dan command. */
    public const KOLOM = [
        'koneksi' => 'Koneksi',
        'invoice' => 'No Invoice',
        'pelanggan' => 'Pelanggan',
        'nominal' => 'Nominal',
        'dibayar' => 'Dibayar',
        'status_lokal' => 'Status Lokal',
        'aksi' => 'Aksi',
        'keterangan' => 'Keterangan',
    ];

    private const KUNCI_TERAKHIR = 'pelunasan-susulan:pemindaian-terakhir';

    public function __construct(
        private PelunasanSusulan $pelunasanSusulan,
        private LaporanPelunasanSusulan $laporan,
    ) {}

    /**
     * Jalankan langsung (command/jadwal harian); null bila pemindaian lain sedang berjalan.
     *
     * @return list<HasilPelunasanSusulan>|null
     */
    public function jalankanDenganKunci(CarbonInterface $sejak, bool $dryRun): ?array
    {
        $kunci = Cache::lock(self::KUNCI, self::DETIK_KUNCI);
        if (! $kunci->get()) {
            return null;
        }

        try {
            return $this->jalankan($sejak, $dryRun);
        } finally {
            $kunci->release();
        }
    }

    /**
     * Mulai pemindaian di antrean; null bila pemindaian lain sedang berjalan. Kunci dipegang
     * sampai job selesai, gagal, atau dibatalkan.
     */
    public function mulai(CarbonInterface $sejak, bool $dryRun, User $oleh): ?string
    {
        $kunci = Cache::lock(self::KUNCI, self::DETIK_KUNCI);
        if (! $kunci->get()) {
            return null;
        }

        $id = (string) Str::uuid();
        $this->simpan($id, [
            'id' => $id,
            'status' => StatusPemindaian::Berjalan,
            'mode' => $dryRun ? 'pratinjau' : 'lunasi',
            'dari' => $sejak->toDateString(),
            'oleh' => $oleh->name,
            'dimulai_pada' => now()->toIso8601String(),
            'pemilik_kunci' => $kunci->owner(),
        ], self::DETIK_KUNCI);
        Cache::put(self::KUNCI_TERAKHIR, $id, self::DETIK_KUNCI);

        try {
            PindaiPelunasanSusulanJob::dispatch($id, $sejak->toIso8601String(), $dryRun, $kunci->owner());
        } catch (Throwable $e) {
            $this->gagal($id, $kunci->owner(), $e->getMessage());

            throw $e;
        }

        return $id;
    }

    /**
     * Dijalankan job: pindai, simpan hasilnya, lalu lepaskan kunci. Pemindaian yang sudah
     * dibatalkan atau kuncinya sudah tidak dipegang tidak dijalankan.
     */
    public function proses(string $id, CarbonInterface $sejak, bool $dryRun, string $pemilikKunci): void
    {
        if (($this->ambil($id)['status'] ?? null) !== StatusPemindaian::Berjalan) {
            return;
        }

        $kunci = Cache::restoreLock(self::KUNCI, $pemilikKunci);
        if (! $kunci->isOwnedByCurrentProcess()) {
            $this->perbarui($id, ['status' => StatusPemindaian::Gagal, 'selesai_pada' => now()->toIso8601String(), 'galat' => 'Kunci pemindaian sudah kedaluwarsa sebelum job berjalan. Ulangi pemindaian.']);

            return;
        }

        $this->perbarui($id, ['diproses_pada' => now()->toIso8601String()]);

        try {
            $hasil = $this->jalankan($sejak, $dryRun);
            $this->perbarui($id, [
                'status' => StatusPemindaian::Selesai,
                'selesai_pada' => now()->toIso8601String(),
                'baris' => $this->keBaris($hasil),
                'ringkasan' => $this->ringkasan($hasil),
            ]);
        } finally {
            $kunci->release();
        }
    }

    public function gagal(string $id, string $pemilikKunci, string $pesan): void
    {
        $this->perbarui($id, ['status' => StatusPemindaian::Gagal, 'selesai_pada' => now()->toIso8601String(), 'galat' => $pesan]);
        Cache::restoreLock(self::KUNCI, $pemilikKunci)->release();
    }

    /**
     * Batalkan pemindaian yang macet -- belum diambil worker antrean (mis. worker mati) atau
     * melewati batas waktu job -- agar kuncinya tidak menahan pemindaian lain dan jadwal harian.
     * False bila pemindaian masih wajar berjalan atau sudah selesai.
     */
    public function batalkan(string $id): bool
    {
        $pemindaian = $this->ambil($id);
        if (! $pemindaian || ! $this->macet($pemindaian)) {
            return false;
        }

        $this->perbarui($id, ['status' => StatusPemindaian::Dibatalkan, 'selesai_pada' => now()->toIso8601String()]);
        Cache::restoreLock(self::KUNCI, $pemindaian['pemilik_kunci'])->release();

        return true;
    }

    /**
     * Pemindaian masih "berjalan" tetapi belum diambil worker setelah beberapa saat, atau sudah
     * diproses lebih lama dari batas waktu job (worker mati di tengah jalan).
     *
     * @param  array<string, mixed>  $pemindaian
     */
    public function macet(array $pemindaian): bool
    {
        if ($pemindaian['status'] !== StatusPemindaian::Berjalan) {
            return false;
        }

        return isset($pemindaian['diproses_pada'])
            ? Carbon::parse($pemindaian['diproses_pada'])->addSeconds(PindaiPelunasanSusulanJob::BATAS_DETIK + 60)->isPast()
            : Carbon::parse($pemindaian['dimulai_pada'])->addSeconds(self::DETIK_MENUNGGU_WORKER)->isPast();
    }

    /**
     * Pemindaian terakhir dari halaman (dipakai bersama oleh semua staf), selama masih di cache.
     *
     * @return Pemindaian|null
     */
    public function terakhir(): ?array
    {
        $id = Cache::get(self::KUNCI_TERAKHIR);

        return $id ? $this->ambil($id) : null;
    }

    /**
     * Baris tabel hasil (kunci sesuai KOLOM); pembayaran yang memang sudah tercatat tidak ditampilkan.
     *
     * @param  list<HasilPelunasanSusulan>  $hasil
     * @return list<BarisPemindaian>
     */
    public function keBaris(array $hasil): array
    {
        return collect($hasil)
            ->reject(fn (HasilPelunasanSusulan $item): bool => $item->aksi === AksiPelunasanSusulan::SudahTercatat)
            ->map(fn (HasilPelunasanSusulan $item): array => [
                'koneksi' => $item->koneksi,
                'invoice' => $item->invoice?->no_invoice ?? $item->pembayaran?->externalId ?? '-',
                'pelanggan' => $item->invoice?->pelanggan?->namaLengkap() ?? '-',
                'nominal' => $item->pembayaran ? Rupiah::format($item->pembayaran->paidAmount) : '-',
                'dibayar' => $item->pembayaran?->paidAt ?? '-',
                'status_lokal' => $item->statusSebelum ?? '-',
                'aksi' => $item->aksi->value,
                'keterangan' => $item->keterangan,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<HasilPelunasanSusulan>  $hasil
     */
    public function ringkasan(array $hasil): string
    {
        $jumlah = collect($hasil)->countBy(fn (HasilPelunasanSusulan $item): string => $item->aksi->value);

        return sprintf(
            '%d dilunasi, %d akan dilunasi (pratinjau), %d dilaporkan, %d sudah tercatat, %d koneksi gagal.',
            $jumlah[AksiPelunasanSusulan::Dilunasi->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::AkanDilunasi->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::Dilaporkan->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::SudahTercatat->value] ?? 0,
            $jumlah[AksiPelunasanSusulan::GagalKoneksi->value] ?? 0,
        );
    }

    /**
     * @return list<HasilPelunasanSusulan>
     */
    private function jalankan(CarbonInterface $sejak, bool $dryRun): array
    {
        $hasil = $this->pelunasanSusulan->jalankan($sejak, $dryRun);
        if (! $dryRun) {
            $this->laporan->laporkan($hasil);
        }

        return $hasil;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ambil(string $id): ?array
    {
        return Cache::get($this->kunciHasil($id));
    }

    /**
     * @param  array<string, mixed>  $pemindaian
     */
    private function simpan(string $id, array $pemindaian, int $detik = self::DETIK_HASIL): void
    {
        Cache::put($this->kunciHasil($id), $pemindaian, $detik);
    }

    /**
     * Pemindaian yang selesai/gagal/dibatalkan disimpan sekitar 1 jam lagi; yang masih berjalan
     * disimpan selama kuncinya.
     *
     * @param  array<string, mixed>  $perubahan
     */
    private function perbarui(string $id, array $perubahan): void
    {
        $pemindaian = $this->ambil($id);
        if (! $pemindaian) {
            return;
        }

        $pemindaian = array_merge($pemindaian, $perubahan);
        $selesai = $pemindaian['status'] !== StatusPemindaian::Berjalan;
        $this->simpan($id, $pemindaian, $selesai ? self::DETIK_HASIL : self::DETIK_KUNCI);

        if ($selesai && Cache::get(self::KUNCI_TERAKHIR) === $id) {
            Cache::put(self::KUNCI_TERAKHIR, $id, self::DETIK_HASIL);
        }
    }

    private function kunciHasil(string $id): string
    {
        return "pelunasan-susulan:pemindaian:{$id}";
    }
}
