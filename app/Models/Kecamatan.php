<?php

namespace App\Models;

use Database\Factories\KecamatanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $kota_id
 * @property string $nama_kecamatan
 * @property string|null $keterangan
 * @property-read Kota $kota
 * @property-read Collection<int, Kelurahan> $kelurahans
 */
#[Fillable(['kota_id', 'nama_kecamatan', 'keterangan'])]
class Kecamatan extends Model
{
    /** @use HasFactory<KecamatanFactory> */
    use HasFactory;

    protected $table = 'kecamatan';

    /**
     * Relasi ke kota induk.
     *
     * @return BelongsTo<Kota, $this>
     */
    public function kota(): BelongsTo
    {
        return $this->belongsTo(Kota::class, 'kota_id');
    }

    /**
     * Relasi ke kelurahan-kelurahan di kecamatan ini.
     *
     * @return HasMany<Kelurahan, $this>
     */
    public function kelurahans(): HasMany
    {
        return $this->hasMany(Kelurahan::class, 'kecamatan_id');
    }

    /**
     * Periksa apakah kecamatan aman untuk dihapus (tidak memiliki data kelurahan turunan).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->kelurahans()->exists();
    }
}
