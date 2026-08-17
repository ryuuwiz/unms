<?php

namespace App\Models;

use App\Enums\StatusOdpPort;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $odp_id
 * @property int $nomor_port
 * @property StatusOdpPort $status
 * @property int|null $layanan_pelanggan_id
 * @property-read Odp $odp
 * @property-read LayananPelanggan|null $layananPelanggan
 */
#[Fillable(['odp_id', 'nomor_port', 'status', 'layanan_pelanggan_id'])]
class OdpPort extends Model
{
    protected $table = 'odp_port';

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusOdpPort::class,
            'nomor_port' => 'integer',
        ];
    }

    /**
     * Relasi ke ODP induk.
     *
     * @return BelongsTo<Odp, $this>
     */
    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class, 'odp_id');
    }

    /**
     * Relasi ke layanan pelanggan yang menggunakan port ini.
     *
     * @return BelongsTo<LayananPelanggan, $this>
     */
    public function layananPelanggan(): BelongsTo
    {
        return $this->belongsTo(LayananPelanggan::class, 'layanan_pelanggan_id');
    }

    /**
     * Apakah port ini masih tersedia untuk digunakan.
     */
    public function isKosong(): bool
    {
        return $this->status === StatusOdpPort::Kosong;
    }
}
