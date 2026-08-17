<?php

namespace App\Models;

use Database\Factories\ProfilBandwidthFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $nama_bandwidth
 * @property int $max_limit_tx
 * @property int $max_limit_rx
 * @property int|null $burst_rate_tx
 * @property int|null $burst_rate_rx
 * @property int|null $burst_threshold_tx
 * @property int|null $burst_threshold_rx
 * @property int|null $burst_time_tx
 * @property int|null $burst_time_rx
 * @property int|null $limit_rate_tx
 * @property int|null $limit_rate_rx
 * @property int $priority
 */
#[Fillable([
    'nama_bandwidth',
    'max_limit_tx',
    'max_limit_rx',
    'burst_rate_tx',
    'burst_rate_rx',
    'burst_threshold_tx',
    'burst_threshold_rx',
    'burst_time_tx',
    'burst_time_rx',
    'limit_rate_tx',
    'limit_rate_rx',
    'priority',
])]
class ProfilBandwidth extends Model
{
    /** @use HasFactory<ProfilBandwidthFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'profil_bandwidth';

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_bandwidth', 'max_limit_tx', 'max_limit_rx', 'priority'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('profil_bandwidth');
    }

    /**
     * Cast semua field speed ke integer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_limit_tx' => 'integer',
            'max_limit_rx' => 'integer',
            'burst_rate_tx' => 'integer',
            'burst_rate_rx' => 'integer',
            'burst_threshold_tx' => 'integer',
            'burst_threshold_rx' => 'integer',
            'burst_time_tx' => 'integer',
            'burst_time_rx' => 'integer',
            'limit_rate_tx' => 'integer',
            'limit_rate_rx' => 'integer',
            'priority' => 'integer',
        ];
    }

    /**
     * Relasi ke paket layanan yang menggunakan profil bandwidth ini.
     *
     * @return HasMany<PaketLayanan, $this>
     */
    public function pakets(): HasMany
    {
        return $this->hasMany(PaketLayanan::class, 'profil_bandwidth_id');
    }

    /**
     * Label kecepatan maksimum (misal: "20/10 Mbps" atau "20 Mbps (1:1)").
     */
    public function labelKecepatan(): string
    {
        $tx = $this->max_limit_tx / 1000;
        $rx = $this->max_limit_rx / 1000;

        if ($tx === $rx) {
            return "{$tx} Mbps (1:1)";
        }

        return "{$tx}/{$rx} Mbps";
    }

    /**
     * Format RouterOS rate string untuk max-limit (misal: "20M/10M").
     */
    public function routerOsMaxLimit(): string
    {
        return "{$this->max_limit_tx}K/{$this->max_limit_rx}K";
    }

    /**
     * Apakah profil ini memiliki konfigurasi burst.
     */
    public function hasBurst(): bool
    {
        return $this->burst_rate_tx !== null && $this->burst_rate_rx !== null;
    }
}
