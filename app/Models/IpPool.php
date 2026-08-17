<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $router_id
 * @property string $name
 * @property string $ip_network
 * @property int $cidr
 * @property string|null $ip_range_start
 * @property string|null $ip_range_end
 * @property float $queue_tx_mbps
 * @property float $queue_rx_mbps
 * @property int $priority_tx
 * @property int $priority_rx
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'router_id',
    'name',
    'ip_network',
    'cidr',
    'ip_range_start',
    'ip_range_end',
    'queue_tx_mbps',
    'queue_rx_mbps',
    'priority_tx',
    'priority_rx',
])]
class IpPool extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'cidr' => 'integer',
            'queue_tx_mbps' => 'decimal:2',
            'queue_rx_mbps' => 'decimal:2',
            'priority_tx' => 'integer',
            'priority_rx' => 'integer',
        ];
    }

    /**
     * Get the router that owns the IP pool.
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }
}
