<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $nama_perusahaan
 * @property string $nama_brand
 * @property string|null $tagline
 * @property string|null $alamat
 * @property string|null $kota
 * @property string|null $kode_pos
 * @property string|null $telepon
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $website
 * @property string|null $npwp
 * @property string|null $nama_bank
 * @property string|null $nomor_rekening
 * @property string|null $atas_nama
 * @property string|null $catatan_invoice
 * @property string|null $syarat_ketentuan
 * @property string|null $nama_penandatangan
 * @property string|null $jabatan_penandatangan
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $logo_url
 * @property-read string|null $logo_base64
 */
#[Fillable([
    'nama_perusahaan',
    'nama_brand',
    'tagline',
    'alamat',
    'kota',
    'kode_pos',
    'telepon',
    'whatsapp',
    'email',
    'website',
    'npwp',
    'nama_bank',
    'nomor_rekening',
    'atas_nama',
    'catatan_invoice',
    'syarat_ketentuan',
    'nama_penandatangan',
    'jabatan_penandatangan',
    'is_default',
])]
class Perusahaan extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity;

    protected $table = 'perusahaan';

    public const CACHE_KEY = 'perusahaan_default';

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('perusahaan');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Register Spatie MediaLibrary collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp']);
    }

    /**
     * Register Spatie MediaLibrary conversions.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->nonQueued();

        $this->addMediaConversion('invoice')
            ->width(400)
            ->height(120)
            ->nonQueued();
    }

    /**
     * Get the default company instance (cached safely as raw attributes).
     */
    public static function default(): self
    {
        $cached = null;
        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (\Throwable) {
            Cache::forget(self::CACHE_KEY);
        }

        if ($cached instanceof static) {
            return $cached;
        }

        if (is_array($cached)) {
            $model = new static;
            $model->setRawAttributes($cached, true);
            $model->exists = true;

            return $model;
        }

        // Evict corrupted / incomplete class from cache
        Cache::forget(self::CACHE_KEY);

        $perusahaan = static::where('is_default', true)->first()
            ?? static::first()
            ?? new static([
                'nama_perusahaan' => config('app.name', 'GOBILLING'),
                'nama_brand' => config('app.name', 'GOBILLING'),
                'tagline' => 'Solusi Billing & Manajemen ISP Terpadu',
                'email' => 'support@gobilling.id',
                'telepon' => '0812-3456-7890',
                'is_default' => true,
            ]);

        if ($perusahaan->exists) {
            Cache::forever(self::CACHE_KEY, $perusahaan->getAttributes());
        }

        return $perusahaan;
    }

    /**
     * Get public URL for the company logo.
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

        $path = $media->getPath();
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $mime = $media->mime_type ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($content);
        }

        return null;
    }

    /**
     * Synchronize public favicon files with company logo or GOBILLING default brand icon.
     */
    public function syncFaviconFiles(): void
    {
        $publicDir = public_path();
        $media = $this->getFirstMedia('logo');

        if ($media && file_exists($media->getPath())) {
            $logoContent = file_get_contents($media->getPath());
            $mime = $media->mime_type;

            @file_put_contents($publicDir.DIRECTORY_SEPARATOR.'favicon.ico', $logoContent);
            @file_put_contents($publicDir.DIRECTORY_SEPARATOR.'apple-touch-icon.png', $logoContent);

            if (str_contains((string) $mime, 'svg')) {
                @file_put_contents($publicDir.DIRECTORY_SEPARATOR.'favicon.svg', $logoContent);
            } else {
                @unlink($publicDir.DIRECTORY_SEPARATOR.'favicon.svg');
            }
        } else {
            $defaultSvg = self::defaultGobillingSvg();
            @file_put_contents($publicDir.DIRECTORY_SEPARATOR.'favicon.svg', $defaultSvg);
            @file_put_contents($publicDir.DIRECTORY_SEPARATOR.'favicon.ico', $defaultSvg);
            @file_put_contents($publicDir.DIRECTORY_SEPARATOR.'apple-touch-icon.png', $defaultSvg);
        }
    }

    /**
     * Default GOBILLING SVG icon markup for brand favicon fallback.
     */
    public static function defaultGobillingSvg(): string
    {
        return <<<'SVG'
<svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
  <rect width="64" height="64" rx="16" fill="url(#gobilling_grad)"/>
  <path d="M36 12L20 34H32L28 52L44 30H32L36 12Z" fill="white" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  <defs>
    <linearGradient id="gobilling_grad" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
      <stop stop-color="#6366F1"/>
      <stop offset="1" stop-color="#9333EA"/>
    </linearGradient>
  </defs>
</svg>
SVG;
    }
}
