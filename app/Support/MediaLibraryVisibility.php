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
 */
class MediaLibraryVisibility
{
    /**
     * @var array<int, string>
     */
    private const COLLECTION_TERLARANG = ['ktp', 'dokumen'];

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
