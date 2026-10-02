<?php

namespace App\Support;

use App\Models\Pelanggan;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Aturan visibilitas berkas Media yang dipakai bersama oleh halaman Media Library dan
 * fitur "pilih dari Media Library" lainnya (mis. picker logo Perusahaan). Mengecualikan
 * collection dokumen pribadi Pelanggan (KTP, dokumen legalitas) -- lihat ADR-0024 dan
 * ADR-0046. Setiap collection privat baru harus ditambahkan di sini, satu-satunya tempat.
 *
 * Juga memisahkan Media Publik (URL permanen) dari Media Privat (URL bertanda tangan) -- ADR-0068.
 */
class MediaLibraryVisibility
{
    /**
     * @var array<int, string>
     */
    private const COLLECTION_TERLARANG = ['ktp', 'dokumen'];

    /**
     * Logo Perusahaan, logo Prefix Registrasi, dan ikon aplikasi: dilihat tanpa login (login, PWA, PDF).
     *
     * @var array<int, string>
     */
    private const KOLEKSI_PUBLIK = ['logo', BrandPelanggan::KOLEKSI_IKON];

    public static function publik(Media $media): bool
    {
        return self::koleksiPublik($media->collection_name);
    }

    public static function koleksiPublik(string $koleksi): bool
    {
        return in_array($koleksi, self::KOLEKSI_PUBLIK, true);
    }

    /**
     * KTP dan dokumen (Pelanggan maupun Pengguna) dienkripsi di disk sendiri (ADR-0024) dan berada
     * di luar path acak, failover, dan URL bertanda tangan Media Library.
     */
    public static function dokumenPribadi(Media $media): bool
    {
        return in_array($media->collection_name, self::COLLECTION_TERLARANG, true);
    }

    /**
     * @return Builder<Media>
     */
    public static function query(): Builder
    {
        return Media::query()
            ->whereNot(function (Builder $q) {
                $q->where('model_type', Pelanggan::class)
                    ->whereIn('collection_name', self::COLLECTION_TERLARANG);
            });
    }
}
