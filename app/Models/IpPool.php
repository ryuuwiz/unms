<?php

namespace App\Models;

use Database\Factories\IpPoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $router_id
 * @property string $nama_pool
 * @property string $ip_network
 * @property int $cidr
 * @property string $rentang_ip_awal
 * @property string $rentang_ip_akhir
 * @property int $priority_tx
 * @property int $priority_rx
 * @property Carbon|null $applied_to_router_at
 * @property string|null $sync_status
 * @property string|null $last_sync_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Router $router
 * @property-read Collection<int, LayananPelanggan> $layanans
 */
#[Fillable([
    'router_id',
    'nama_pool',
    'ip_network',
    'cidr',
    'rentang_ip_awal',
    'rentang_ip_akhir',
    'priority_tx',
    'priority_rx',
    'applied_to_router_at',
    'sync_status',
    'last_sync_error',
])]
class IpPool extends Model
{
    /** @use HasFactory<IpPoolFactory> */
    use HasFactory;

    protected $table = 'ip_pool';

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cidr' => 'integer',
            'priority_tx' => 'integer',
            'priority_rx' => 'integer',
            'applied_to_router_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke router induk.
     *
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    /**
     * Relasi ke seluruh layanan pelanggan yang menggunakan IP Pool ini.
     *
     * @return HasMany<LayananPelanggan, $this>
     */
    public function layanans(): HasMany
    {
        return $this->hasMany(LayananPelanggan::class, 'ip_pool_id');
    }

    /**
     * Relasi ke seluruh riwayat job MikroTik IP Pool ini.
     *
     * @return HasMany<MikrotikJobLog, $this>
     */
    public function jobLogs(): HasMany
    {
        return $this->hasMany(MikrotikJobLog::class, 'ip_pool_id');
    }

    /**
     * Format CIDR notation (misal: "192.168.1.0/24").
     */
    public function labelNetwork(): string
    {
        return "{$this->ip_network}/{$this->cidr}";
    }

    /**
     * Cari IP Pool lain pada router yang sama yang rentang IP-nya beririsan dengan rentang ini.
     */
    public static function findOverlapping(int $routerId, string $awal, string $akhir, ?int $ignoreId = null): ?self
    {
        return static::where('router_id', $routerId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->first(fn (self $pool): bool => ip2long($awal) <= ip2long($pool->rentang_ip_akhir)
                && ip2long($pool->rentang_ip_awal) <= ip2long($akhir));
    }

    /**
     * Periksa apakah IP Pool aman untuk dihapus (tidak digunakan oleh layanan pelanggan).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->layanans()->withTrashed()->exists();
    }

    /**
     * Dapatkan alamat IP Gateway (host pertama) dari network pool ini (misal: "10.0.0.1").
     */
    public function getGatewayAddress(): string
    {
        $networkLong = ip2long($this->ip_network);
        if ($networkLong !== false) {
            return long2ip($networkLong + 1);
        }

        return $this->ip_network;
    }
}
