<?php

namespace App\Support\MediaLibrary;

use App\Support\MediaLibraryVisibility;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Media Privat selalu tampil lewat URL bertanda tangan yang kedaluwarsa (ADR-0068), sehingga setiap
 * `getUrl()` di view otomatis aman. Disk yang tidak bisa membuat URL sementara (mis. `public` saat
 * pengembangan tanpa S3) jatuh ke URL biasa.
 */
class UrlGeneratorPrivat extends DefaultUrlGenerator
{
    public const MENIT_BERLAKU = 60;

    public function getUrl(): string
    {
        $disk = Storage::disk($this->getDiskName());

        if (MediaLibraryVisibility::publik($this->media) || ! $disk->providesTemporaryUrls()) {
            return parent::getUrl();
        }

        return $this->getTemporaryUrl(now()->addMinutes(self::MENIT_BERLAKU));
    }
}
