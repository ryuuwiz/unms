<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $kelurahan_id
 * @property string $nama_perumahan
 * @property string|null $singkatan
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $keterangan
 * @property-read Kelurahan $kelurahan
 * @property-read Collection<int, Pelanggan> $pelanggans
 * @property-read Collection<int, Odp> $odps
 */
#[Fillable(['kelurahan_id', 'nama_perumahan', 'singkatan', 'latitude', 'longitude', 'keterangan'])]
class Perumahan extends Model
{
    use HasFactory;

    protected $table = 'perumahan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Relasi ke kelurahan induk.
     *
     * @return BelongsTo<Kelurahan, $this>
     */
    public function kelurahan(): BelongsTo
    {
        return $this->belongsTo(Kelurahan::class, 'kelurahan_id');
    }

    /**
     * Relasi ke pelanggan yang beralamat di perumahan ini.
     *
     * @return HasMany<Pelanggan, $this>
     */
    public function pelanggans(): HasMany
    {
        return $this->hasMany(Pelanggan::class, 'perumahan_id');
    }

    /**
     * Relasi ke ODP yang berada di perumahan ini.
     *
     * @return HasMany<Odp, $this>
     */
    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class, 'perumahan_id');
    }

    /**
     * Periksa apakah perumahan aman untuk dihapus (tidak memiliki data pelanggan atau ODP).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->pelanggans()->exists() && ! $this->odps()->exists();
    }
}
