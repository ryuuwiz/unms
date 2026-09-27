<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Logo tunggal di media collection `logo`, untuk model yang memakai InteractsWithMedia.
 *
 * @property-read string|null $logo_url
 * @property-read string|null $logo_base64
 */
trait HasLogo
{
    /**
     * Get public URL for the logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->hasMedia('logo')) {
            return $this->getFirstMediaUrl('logo');
        }

        return null;
    }

    /**
     * Get Base64 encoded logo string for DomPDF.
     */
    public function getLogoBase64Attribute(): ?string
    {
        $media = $this->getFirstMedia('logo');
        if (! $media) {
            return null;
        }

        $content = $this->getLogoContent();
        if ($content === null) {
            return null;
        }

        $mime = $media->mime_type ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }

    /**
     * Ambil isi biner logo dari disk media (disk-agnostic: local, s3, dsb).
     */
    public function getLogoContent(): ?string
    {
        $media = $this->getFirstMedia('logo');
        if (! $media) {
            return null;
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        return $disk->exists($path) ? $disk->get($path) : null;
    }
}
