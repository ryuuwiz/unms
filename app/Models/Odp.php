<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nama_odp
 * @property int|null $perumahan_id
 * @property int $kapasitas_port
 * @property float|null $latitude
 * @property float|null $longitude
 * @property-read Perumahan|null $perumahan
 * @property-read Collection<int, OdpPort> $ports
 */
#[Fillable(['nama_odp', 'perumahan_id', 'kapasitas_port', 'latitude', 'longitude'])]
class Odp extends Model
{
    protected $table = 'odp';

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kapasitas_port' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Relasi ke perumahan tempat ODP ini berada.
     *
     * @return BelongsTo<Perumahan, $this>
     */
    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class, 'perumahan_id');
    }

    /**
     * Relasi ke semua port di ODP ini.
     *
     * @return HasMany<OdpPort, $this>
     */
    public function ports(): HasMany
    {
        return $this->hasMany(OdpPort::class, 'odp_id');
    }

    /**
     * Jumlah port yang masih kosong / tersedia.
     */
    public function portTersedia(): int
    {
        return $this->ports()->where('status', 'kosong')->count();
    }
}
