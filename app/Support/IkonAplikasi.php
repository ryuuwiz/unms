<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * PNG persegi ikon Aplikasi Pelanggan untuk satu Brand Pelanggan. Sumbernya ikon yang diunggah,
 * lalu logo brand di kanvas persegi, lalu inisial nama di atas warna utama -- tidak pernah
 * meminjam milik brand lain (ADR-0066).
 */
final class IkonAplikasi
{
    /** Ukuran yang dilayani: favicon/manifest 192, manifest 512, apple-touch-icon 180. */
    public const UKURAN = [180, 192, 512];

    private const PORSI_LOGO = 0.7;

    public static function png(BrandPelanggan $brand, int $ukuran): string
    {
        return Cache::rememberForever(
            "ikon-aplikasi:{$brand->pengenal()}:{$brand->versiIdentitas()}:{$ukuran}",
            fn (): string => self::render($brand, $ukuran),
        );
    }

    private static function render(BrandPelanggan $brand, int $ukuran): string
    {
        $kanvas = self::dariGambar($brand->ikonAplikasiMedia(), $ukuran, 1.0, null)
            ?? self::dariGambar($brand->logoMedia(), $ukuran, self::PORSI_LOGO, '#ffffff')
            ?? self::dariInisial($brand, $ukuran);

        ob_start();
        imagepng($kanvas);

        return (string) ob_get_clean();
    }

    /**
     * Gambar sumber dipusatkan di kanvas persegi, mengisi `$porsi` sisi kanvas; null bila tidak
     * ada media atau formatnya tidak dapat dibaca GD (mis. SVG).
     */
    private static function dariGambar(?Media $media, int $ukuran, float $porsi, ?string $latar): ?GdImage
    {
        $isi = $media ? self::isiMedia($media) : null;
        $sumber = $isi !== null ? @imagecreatefromstring($isi) : false;
        if (! $sumber) {
            return null;
        }

        $kanvas = self::kanvas($ukuran, $latar);
        $lebar = imagesx($sumber);
        $tinggi = imagesy($sumber);
        $skala = ($ukuran * $porsi) / max($lebar, $tinggi);
        $lebarBaru = (int) round($lebar * $skala);
        $tinggiBaru = (int) round($tinggi * $skala);

        imagecopyresampled(
            $kanvas, $sumber,
            intdiv($ukuran - $lebarBaru, 2), intdiv($ukuran - $tinggiBaru, 2), 0, 0,
            $lebarBaru, $tinggiBaru, $lebar, $tinggi,
        );

        return $kanvas;
    }

    private static function dariInisial(BrandPelanggan $brand, int $ukuran): GdImage
    {
        $kanvas = self::kanvas($ukuran, $brand->warnaUtama());
        $warnaHuruf = (int) imagecolorallocate($kanvas, ...BrandPelanggan::rgb($brand->warnaTeks()));
        $huruf = mb_strtoupper(mb_substr($brand->nama(), 0, 1)) ?: '?';
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        if (! function_exists('imagettftext') || ! is_file($font)) {
            imagestring($kanvas, 5, intdiv($ukuran, 2) - 4, intdiv($ukuran, 2) - 8, $huruf, $warnaHuruf);

            return $kanvas;
        }

        $besar = $ukuran * 0.45;
        $kotak = imagettfbbox($besar, 0, $font, $huruf);
        if ($kotak === false) {
            return $kanvas;
        }

        $x = (int) (($ukuran - ($kotak[2] - $kotak[0])) / 2 - $kotak[0]);
        $y = (int) (($ukuran - ($kotak[1] - $kotak[7])) / 2 - $kotak[7]);
        imagettftext($kanvas, $besar, 0, $x, $y, $warnaHuruf, $font, $huruf);

        return $kanvas;
    }

    private static function kanvas(int $ukuran, ?string $latar): GdImage
    {
        $kanvas = imagecreatetruecolor(max(1, $ukuran), max(1, $ukuran));
        imagesavealpha($kanvas, true);

        if ($latar === null) {
            imagealphablending($kanvas, false);
            imagefill($kanvas, 0, 0, (int) imagecolorallocatealpha($kanvas, 0, 0, 0, 127));
            imagealphablending($kanvas, true);

            return $kanvas;
        }

        imagefill($kanvas, 0, 0, (int) imagecolorallocate($kanvas, ...BrandPelanggan::rgb($latar)));

        return $kanvas;
    }

    private static function isiMedia(Media $media): ?string
    {
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        return $disk->exists($path) ? $disk->get($path) : null;
    }
}
