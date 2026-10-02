<?php

namespace App\Services\PaymentGateway;

use App\DTO\PaymentGateway\HasilPelunasanSusulan;
use App\Enums\AksiPelunasanSusulan;
use App\Jobs\PaymentGateway\PindaiPelunasanSusulanJob;
use App\Models\User;
use App\Support\Rupiah;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pemindaian Pelunasan Susulan untuk satu rentang waktu bayar: jadwal harian, command, dan halaman
 * Pelunasan Susulan memakai kunci yang sama sehingga tidak pernah berjalan bersamaan. Pemindaian
 * dari halaman berjalan di antrean dan hasilnya disimpan di cache sekitar 1 jam.
 *
 * @phpstan-type BarisPemindaian array{koneksi: string, invoice: string, pelanggan: string, nominal: string, dibayar: string, status_lokal: string, aksi: string, keterangan: string}
 * @phpstan-type Pemindaian array{id: string, status: string, mode: string, dari: string, oleh: string, dimulai_pada: string, selesai_pada?: string, baris?: list<BarisPemindaian>, ringkasan?: string, galat?: string}
 */
class PemindaianPelunasanSusulan
{
    public const KUNCI = 'pelunasan-susulan:pemindaian';

    public const DETIK_KUNCI = 3600;

    public const DETIK_HASIL = 3600;

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
     * sampai job selesai atau gagal.
     */
    public function mulai(CarbonInterface $sejak, bool $dryRun, User $oleh): ?string
    {
        $kunci = Cache::lock(self::KUNCI, self::DETIK_KUNCI);
        if (! $kunci->get()) {
            return null;
        }

        $id = (string) Str::uuid();
        $this->simpan([
            'id' => $id,
            'status' => 'berjalan',
            'mode' => $dryRun ? 'pratinjau' : 'lunasi',
            'dari' => $sejak->toDateString(),
            'oleh' => $oleh->name,
            'dimulai_pada' => now()->toIso8601String(),
        ]);

        try {
            PindaiPelunasanSusulanJob::dispatch($id, $sejak->toIso8601String(), $dryRun, $kunci->owner());
        } catch (Throwable $e) {
            $this->gagal($id, $kunci->owner(), $e->getMessage());

            throw $e;
        }

        return $id;
    }

    /**
     * Dijalankan job: pindai, simpan hasilnya, lalu lepaskan kunci.
     */
    public function proses(string $id, CarbonInterface $sejak, bool $dryRun, string $pemilikKunci): void
    {
        try {
            $hasil = $this->jalankan($sejak, $dryRun);
            $this->perbarui($id, [
                'status' => 'selesai',
                'selesai_pada' => now()->toIso8601String(),
                'baris' => $this->keBaris($hasil),
                'ringkasan' => $this->ringkasan($hasil),
            ]);
        } finally {
            Cache::restoreLock(self::KUNCI, $pemilikKunci)->release();
        }
    }

    public function gagal(string $id, string $pemilikKunci, string $pesan): void
    {
        $this->perbarui($id, ['status' => 'gagal', 'selesai_pada' => now()->toIso8601String(), 'galat' => $pesan]);
        Cache::restoreLock(self::KUNCI, $pemilikKunci)->release();
    }

    /**
     * Pemindaian terakhir dari halaman (dipakai bersama oleh semua staf), selama masih di cache.
     *
     * @return Pemindaian|null
     */
    public function terakhir(): ?array
    {
        $id = Cache::get(self::KUNCI_TERAKHIR);

        return $id ? Cache::get($this->kunciHasil($id)) : null;
    }

    /**
     * Baris tabel hasil; pembayaran yang memang sudah tercatat tidak ditampilkan.
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
     * @param  Pemindaian  $pemindaian
     */
    private function simpan(array $pemindaian): void
    {
        Cache::put($this->kunciHasil($pemindaian['id']), $pemindaian, self::DETIK_HASIL);
        Cache::put(self::KUNCI_TERAKHIR, $pemindaian['id'], self::DETIK_HASIL);
    }

    /**
     * @param  array<string, mixed>  $perubahan
     */
    private function perbarui(string $id, array $perubahan): void
    {
        $pemindaian = Cache::get($this->kunciHasil($id));
        if ($pemindaian) {
            Cache::put($this->kunciHasil($id), array_merge($pemindaian, $perubahan), self::DETIK_HASIL);
        }
    }

    private function kunciHasil(string $id): string
    {
        return "pelunasan-susulan:pemindaian:{$id}";
    }
}
