<?php

namespace App\Support\MediaLibrary;

use App\Support\MediaLibraryVisibility;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

/**
 * `public/{uuid}/` untuk Media Publik, `private/{uuid}/` untuk Media Privat: tanpa ID berurutan yang
 * bisa dienumerasi, dan hanya prefix `public/` yang dibuka bucket policy (ADR-0068). Dokumen pribadi
 * tetap di path bawaan karena di luar cakupan.
 */
class PathGeneratorAcak extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        if (MediaLibraryVisibility::dokumenPribadi($media)) {
            return parent::getBasePath($media);
        }

        return self::prefix($media).'/'.$media->uuid;
    }

    public static function prefix(Media $media): string
    {
        return MediaLibraryVisibility::publik($media) ? 'public' : 'private';
    }
}
