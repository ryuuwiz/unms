<?php

namespace App\Models;

use App\Enums\StatusRouter;
use App\Models\Concerns\GracefullyDecryptsAttributes;
use Database\Factories\RouterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $nama_router
 * @property string $ip_address
 * @property int $port
 * @property string $username
 * @property string $password_terenkripsi
 * @property string|null $deskripsi
 * @property StatusRouter $status_koneksi
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $last_ping_at
 * @property string|null $last_ping_status
 * @property string|null $last_ping_message
 * @property int|null $cpu_load
 * @property int|null $memory_free
 * @property int|null $memory_total
 * @property string|null $uptime
 * @property string|null $board_name
 * @property string|null $routeros_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nama_router',
    'ip_address',
    'port',
    'username',
    'password_terenkripsi',
    'deskripsi',
    'status_koneksi',
    'last_sync_at',
    'last_ping_at',
    'last_ping_status',
    'last_ping_message',
    'cpu_load',
    'memory_free',
    'memory_total',
    'uptime',
    'board_name',
    'routeros_version',
])]
class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use GracefullyDecryptsAttributes, HasFactory, LogsActivity;

    protected $table = 'router';

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_router', 'ip_address', 'port', 'username', 'status_koneksi'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('router');
    }

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_koneksi' => StatusRouter::class,
            'password_terenkripsi' => 'encrypted',
            'last_sync_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'port' => 'integer',
            'cpu_load' => 'integer',
            'memory_free' => 'integer',
            'memory_total' => 'integer',
        ];
    }

    /**
     * Relasi ke semua IP pool yang dimiliki router ini.
     *
     * @return HasMany<IpPool, $this>
     */
    public function ipPools(): HasMany
    {
        return $this->hasMany(IpPool::class, 'router_id');
    }

    /**
     * Relasi ke semua layanan pelanggan yang terhubung ke router ini.
     *
     * @return HasMany<LayananPelanggan, $this>
     */
    public function layanans(): HasMany
    {
        return $this->hasMany(LayananPelanggan::class, 'router_id');
    }

    /**
     * Relasi ke seluruh riwayat job MikroTik router ini.
     *
     * @return HasMany<MikrotikJobLog, $this>
     */
    public function jobLogs(): HasMany
    {
        return $this->hasMany(MikrotikJobLog::class, 'router_id');
    }

    /**
     * Scope filter router yang berstatus online.
     *
     * @param  Builder<Router>  $query
     * @return Builder<Router>
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('status_koneksi', StatusRouter::Online);
    }

    /**
     * Periksa apakah router aman untuk dihapus (tidak memiliki data layanan pelanggan).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->layanans()->withTrashed()->exists()
            && ! $this->ipPools()->whereHas('layanans', fn ($q) => $q->withTrashed())->exists();
    }

    /**
     * Label koneksi untuk API (ip:port).
     */
    public function labelKoneksi(): string
    {
        return "{$this->ip_address}:{$this->port}";
    }
}
