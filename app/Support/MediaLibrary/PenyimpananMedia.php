<?php

namespace App\Support\MediaLibrary;

use App\Support\MediaLibraryVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Failover Penyimpanan (ADR-0068): disk cadangan saat S3 tidak terjangkau, penanda media yang
 * menunggu sinkron, dan pemindahan berkas antar-disk/path.
 */
class PenyimpananMedia
{
    public const PROPERTI_FAILOVER = 'failover_dari';

    private const CACHE_S3_MATI = 'media:s3-mati';

    private const MENIT_S3_MATI = 5;

    public static function diskS3(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    public static function s3Mati(): bool
    {
        return Cache::has(self::CACHE_S3_MATI);
    }

    public static function tandaiS3Mati(): void
    {
        Cache::put(self::CACHE_S3_MATI, true, now()->addMinutes(self::MENIT_S3_MATI));
    }

    public static function tandaiS3Pulih(): void
    {
        Cache::forget(self::CACHE_S3_MATI);
    }

    /**
     * Media Privat tidak boleh mendarat di `public` (disajikan langsung lewat /storage).
     */
    public static function diskCadangan(string $koleksi): string
    {
        return MediaLibraryVisibility::koleksiPublik($koleksi) ? 'public' : 'local';
    }

    /**
     * @return Builder<Media>
     */
    public static function menungguSinkron(): Builder
    {
        return Media::query()->whereNotNull('custom_properties->'.self::PROPERTI_FAILOVER);
    }

    /**
     * Salin semua berkas di bawah `$dari` (disk asal) ke `$ke` (disk tujuan), lalu hapus asalnya.
     * Asal baru dihapus setelah semua salinan berhasil, jadi kegagalan di tengah tidak kehilangan berkas.
     */
    public static function pindahkanDirektori(string $diskAsal, string $dari, string $diskTujuan, string $ke): bool
    {
        $asal = Storage::disk($diskAsal);
        $tujuan = Storage::disk($diskTujuan);

        foreach ($asal->allFiles($dari) as $berkas) {
            $stream = $asal->readStream($berkas);

            if ($stream === null || ! $tujuan->writeStream($ke.substr($berkas, strlen($dari)), $stream)) {
                return false;
            }
        }

        $asal->deleteDirectory($dari);

        return true;
    }
}
