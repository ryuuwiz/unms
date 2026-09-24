<?php

namespace App\Models;

use Database\Factories\KelurahanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $kecamatan_id
 * @property string $nama_kelurahan
 * @property string|null $keterangan
 * @property-read Kecamatan $kecamatan
 * @property-read Collection<int, Perumahan> $perumahans
 */
#[Fillable(['kecamatan_id', 'nama_kelurahan', 'keterangan'])]
class Kelurahan extends Model
{
    /** @use HasFactory<KelurahanFactory> */
    use HasFactory;

    protected $table = 'kelurahan';

    /**
     * Relasi ke kecamatan induk.
     *
     * @return BelongsTo<Kecamatan, $this>
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class, 'kecamatan_id');
    }

    /**
     * Relasi ke perumahan-perumahan di kelurahan ini.
     *
     * @return HasMany<Perumahan, $this>
     */
    public function perumahans(): HasMany
    {
        return $this->hasMany(Perumahan::class, 'kelurahan_id');
    }

    /**
     * Periksa apakah kelurahan aman untuk dihapus (tidak memiliki data perumahan turunan).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->perumahans()->exists();
    }
}
