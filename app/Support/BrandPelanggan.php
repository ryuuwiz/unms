<?php

namespace App\Support;

use App\Models\AkunPelanggan;
use App\Models\PengaturanPrefixRegistrasi;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Identitas Brand Pelanggan yang dilihat seorang pelanggan -- satu-satunya sumber nama dan logo
 * merek di semua yang dilihat pelanggan (WhatsApp, invoice PDF, payment gateway, Portal).
 * Prefix Registrasi yang cocok dengan huruf awal No. Registrasi (termasuk yang nonaktif),
 * atau brand Perusahaan bila tidak ada yang cocok. Lihat CONTEXT.md "Brand Pelanggan",
 * ADR-0061 dan ADR-0066.
 */
final class BrandPelanggan
{
    private const ATRIBUT_REQUEST = 'brand_pelanggan';

    public const WARNA_DEFAULT = '#4f46e5';

    public const PANJANG_NAMA_PENDEK = 12;

    /** Pengenal brand Perusahaan di URL/cookie, karena brand Perusahaan tidak punya kode prefix. */
    public const KODE_PERUSAHAAN = 'default';

    public const COOKIE_PETUNJUK = 'petunjuk_brand';

    private const UMUR_PETUNJUK_MENIT = 60 * 24 * 365 * 2;

    /** Perusahaan::default() dibuat ulang dari cache tiap panggilan; disimpan agar media-nya dimuat sekali. */
    private ?Perusahaan $perusahaan = null;

    private function __construct(private readonly ?PengaturanPrefixRegistrasi $prefix) {}

    public static function untukNoReg(?string $noReg): self
    {
        return new self(PengaturanPrefixRegistrasi::untukNoReg($noReg));
    }

    public static function perusahaan(): self
    {
        return new self(null);
    }

    /**
     * Brand dari pengenalnya (kode prefix, termasuk nonaktif, atau KODE_PERUSAHAAN); null bila
     * tidak dikenal.
     */
    public static function dariPengenal(mixed $pengenal): ?self
    {
        if ($pengenal === self::KODE_PERUSAHAAN) {
            return self::perusahaan();
        }

        $prefix = is_string($pengenal) && $pengenal !== ''
            ? PengaturanPrefixRegistrasi::where('kode', $pengenal)->first()
            : null;

        return $prefix ? new self($prefix) : null;
    }

    /**
     * Brand yang tampil di halaman Portal Pelanggan pada request ini: brand yang ditetapkan
     * halaman (mis. dari invoice), lalu brand pelanggan yang login, lalu Petunjuk Brand
     * perangkat, lalu brand Perusahaan. Petunjuk Brand hanya menentukan tampilan.
     */
    public static function untukPortal(): self
    {
        $ditetapkan = request()->attributes->get(self::ATRIBUT_REQUEST);
        if ($ditetapkan instanceof self) {
            return $ditetapkan;
        }

        /** @var AkunPelanggan|null $akun */
        $akun = Auth::guard('pelanggan')->user();
        $brand = $akun
            ? self::untukNoReg($akun->pelanggan?->no_reg)
            : (self::dariPengenal(request()->cookie(self::COOKIE_PETUNJUK, '')) ?? self::perusahaan());

        self::tetapkanUntukRequest($brand);

        return $brand;
    }

    /**
     * Kunci brand Portal untuk sisa request ini, mis. Halaman Tagihan Mandiri memakai brand
     * pemilik invoice, bukan brand sesi.
     */
    public static function tetapkanUntukRequest(self $brand): void
    {
        request()->attributes->set(self::ATRIBUT_REQUEST, $brand);
    }

    /**
     * Kode Prefix Registrasi brand ini; null untuk brand Perusahaan.
     */
    public function kode(): ?string
    {
        return $this->prefix?->kode;
    }

    /**
     * Pengenal stabil brand ini untuk URL ikon dan Petunjuk Brand.
     */
    public function pengenal(): string
    {
        return $this->prefix->kode ?? self::KODE_PERUSAHAAN;
    }

    /**
     * Cookie Petunjuk Brand perangkat untuk brand ini.
     */
    public function cookiePetunjuk(): Cookie
    {
        return cookie(self::COOKIE_PETUNJUK, $this->pengenal(), self::UMUR_PETUNJUK_MENIT);
    }

    public function adalahPerusahaan(): bool
    {
        return $this->prefix === null;
    }

    public function nama(): string
    {
        return $this->prefix?->nama
            ?: (Perusahaan::default()->nama_brand ?: config('app.name', 'GOBILLING'));
    }

    /**
     * Label Aplikasi Pelanggan di layar utama; kosong berarti kata-kata awal nama brand yang
     * muat dalam batas panjang (dipotong paksa bila kata pertama saja sudah terlalu panjang).
     */
    public function namaPendek(): string
    {
        if ($this->sumber()->nama_pendek) {
            return $this->sumber()->nama_pendek;
        }

        $pendek = '';
        foreach (preg_split('/\s+/', trim($this->nama())) ?: [] as $kata) {
            $calon = ltrim($pendek.' '.$kata);
            if (mb_strlen($calon) > self::PANJANG_NAMA_PENDEK) {
                break;
            }
            $pendek = $calon;
        }

        return $pendek ?: mb_substr($this->nama(), 0, self::PANJANG_NAMA_PENDEK);
    }

    /**
     * Warna utama (hex `#rrggbb`); kosong berarti warna default aplikasi, bukan warna Perusahaan.
     */
    public function warnaUtama(): string
    {
        $warna = (string) $this->sumber()->warna_utama;

        return preg_match('/^#[0-9a-f]{6}$/i', $warna) ? strtolower($warna) : self::WARNA_DEFAULT;
    }

    /**
     * Warna teks yang terbaca di atas warna utama (hitam/putih menurut luminansinya).
     */
    public function warnaTeks(): string
    {
        [$r, $g, $b] = self::rgb($this->warnaUtama());

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#18181b' : '#ffffff';
    }

    /**
     * Logo milik brand ini sendiri -- prefix tanpa logo tidak meminjam logo Perusahaan.
     */
    public function logoUrl(): ?string
    {
        return $this->sumber()->logo_url;
    }

    public function logoBase64(): ?string
    {
        return $this->sumber()->logo_base64;
    }

    /**
     * @return array{int<0, 255>, int<0, 255>, int<0, 255>}
     */
    public static function rgb(string $hex): array
    {
        $nilai = fn (int $mulai): int => max(0, min(255, (int) hexdec(substr($hex, $mulai, 2))));

        return [$nilai(1), $nilai(3), $nilai(5)];
    }

    public function logoMedia(): ?Media
    {
        return $this->sumber()->getFirstMedia('logo');
    }

    public function ikonAplikasiMedia(): ?Media
    {
        return $this->sumber()->getFirstMedia('ikon_aplikasi');
    }

    /**
     * Berubah setiap kali identitas visual brand berubah -- dipakai untuk cache ikon dan
     * cache-busting URL ikon di manifest.
     */
    public function versiIdentitas(): string
    {
        return substr(md5(implode('|', [
            $this->pengenal(),
            $this->namaPendek(),
            $this->warnaUtama(),
            $this->ikonAplikasiMedia()?->uuid,
            $this->logoMedia()?->uuid,
        ])), 0, 10);
    }

    public function urlIkon(int $ukuran): string
    {
        return route('portal.aplikasi.ikon', [
            'brand' => $this->pengenal(),
            'ukuran' => $ukuran,
            'v' => $this->versiIdentitas(),
        ]);
    }

    private function sumber(): PengaturanPrefixRegistrasi|Perusahaan
    {
        return $this->prefix ?? ($this->perusahaan ??= Perusahaan::default());
    }
}
