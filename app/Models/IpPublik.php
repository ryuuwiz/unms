<?php

namespace App\Models;

use Database\Factories\IpPublikFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $router_id
 * @property string $alamat_ip
 * @property string $gateway
 * @property float $harga_bulanan
 * @property float|null $harga_ditagih
 * @property int|null $layanan_pelanggan_id
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Router $router
 * @property-read LayananPelanggan|null $layananPelanggan
 */
#[Fillable([
    'router_id',
    'alamat_ip',
    'gateway',
    'harga_bulanan',
    'harga_ditagih',
    'layanan_pelanggan_id',
    'keterangan',
])]
class IpPublik extends Model
{
    /** @use HasFactory<IpPublikFactory> */
    use HasFactory;

    protected $table = 'ip_publik';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harga_bulanan' => 'decimal:2',
            'harga_ditagih' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    /**
     * @return BelongsTo<LayananPelanggan, $this>
     */
    public function layananPelanggan(): BelongsTo
    {
        return $this->belongsTo(LayananPelanggan::class, 'layanan_pelanggan_id');
    }

    /**
     * Status turunan: terpakai jika dipegang sebuah layanan, selain itu tersedia.
     */
    public function isTersedia(): bool
    {
        return $this->layanan_pelanggan_id === null;
    }

    /**
     * Pesan galat jika alamat berada di dalam rentang IP Pool router yang sama, null jika aman.
     */
    public static function bentrokDenganPool(int $routerId, string $address): ?string
    {
        $pool = IpPool::findContaining($routerId, $address);

        return $pool ? "Alamat {$address} berada di dalam rentang IP Pool {$pool->nama_pool} pada router yang sama." : null;
    }
}
