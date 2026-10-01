<?php

namespace App\Models;

use App\Models\Concerns\HasLogo;
use App\Support\BrandPelanggan;
use Database\Factories\PengaturanPrefixRegistrasiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $kode
 * @property string $nama
 * @property bool $is_active
 * @property string|null $nama_pendek
 * @property string|null $warna_utama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $logo_url
 * @property-read string|null $logo_base64
 */
#[Fillable(['kode', 'nama', 'is_active', 'nama_pendek', 'warna_utama'])]
class PengaturanPrefixRegistrasi extends Model implements HasMedia
{
    /** @use HasFactory<PengaturanPrefixRegistrasiFactory> */
    use HasFactory, HasLogo, InteractsWithMedia, LogsActivity;

    protected $table = 'pengaturan_prefix_registrasi';

    protected $attributes = [
        'is_active' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['kode', 'nama', 'is_active', 'nama_pendek', 'warna_utama'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pengaturan_prefix_registrasi');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PengaturanPrefixRegistrasi>  $query
     * @return Builder<PengaturanPrefixRegistrasi>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp']);

        $this->addMediaCollection(BrandPelanggan::KOLEKSI_IKON)
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp']);
    }

    /**
     * Prefix milik sebuah No. Registrasi (huruf awalnya), termasuk yang nonaktif -- lihat
     * CONTEXT.md "Brand Pelanggan" dan ADR-0061.
     */
    public static function untukNoReg(?string $noReg): ?self
    {
        if (! preg_match('/^[A-Za-z]+/', (string) $noReg, $cocok)) {
            return null;
        }

        return static::where('kode', strtoupper($cocok[0]))->first();
    }

    /**
     * Kode prefix milik No. Registrasi untuk awalan nomor invoice dan Site ID; kosong bila tidak cocok.
     */
    public static function kodeUntuk(?string $noReg): string
    {
        return static::untukNoReg($noReg)->kode ?? '';
    }
}
