<?php

namespace App\Models;

use Database\Factories\IpPoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 * @property-read Router $router
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
     * Format CIDR notation (misal: "192.168.1.0/24").
     */
    public function labelNetwork(): string
    {
        return "{$this->ip_network}/{$this->cidr}";
    }
}
