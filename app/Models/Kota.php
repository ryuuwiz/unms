<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nama_kota
 * @property string|null $keterangan
 * @property-read Collection<int, Kecamatan> $kecamatans
 */
#[Fillable(['nama_kota', 'keterangan'])]
class Kota extends Model
{
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
}
