<?php

namespace App\Models;

use App\Enums\StatusRouter;
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
])]
class Router extends Model
{
    /** @use HasFactory<RouterFactory> */
    use HasFactory, LogsActivity;

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
            'port' => 'integer',
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
     * Label koneksi untuk API (ip:port).
     */
    public function labelKoneksi(): string
    {
        return "{$this->ip_address}:{$this->port}";
    }
}
