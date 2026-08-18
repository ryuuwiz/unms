<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $nama_kota
 * @property string|null $keterangan
 * @property-read Collection<int, Kecamatan> $kecamatans
 * @property-read Collection<int, Kelurahan> $kelurahans
 */
#[Fillable(['nama_kota', 'keterangan'])]
class Kota extends Model
{
    use HasFactory;

    protected $table = 'kota';

    /**
     * Relasi ke kecamatan-kecamatan di kota ini.
     *
     * @return HasMany<Kecamatan, $this>
     */
    public function kecamatans(): HasMany
    {
        return $this->hasMany(Kecamatan::class, 'kota_id');
    }

    /**
     * Relasi ke kelurahan-kelurahan di kota ini melalui kecamatan.
     *
     * @return HasManyThrough<Kelurahan, Kecamatan, $this>
     */
    public function kelurahans(): HasManyThrough
    {
        return $this->hasManyThrough(Kelurahan::class, Kecamatan::class, 'kota_id', 'kecamatan_id');
    }

    /**
     * Periksa apakah kota aman untuk dihapus (tidak memiliki data kecamatan turunan).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->kecamatans()->exists();
    }
}
