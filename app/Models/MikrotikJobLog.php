<?php

namespace App\Models;

use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $router_id
 * @property int|null $layanan_pelanggan_id
 * @property int|null $ip_pool_id
 * @property MikrotikJobType $job_type
 * @property MikrotikJobStatus $status
 * @property int $attempt_count
 * @property string|null $error_message
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Router $router
 * @property-read LayananPelanggan|null $layananPelanggan
 * @property-read IpPool|null $ipPool
 */
#[Fillable([
    'router_id',
    'layanan_pelanggan_id',
    'ip_pool_id',
    'job_type',
    'status',
    'attempt_count',
    'error_message',
    'payload',
    'finished_at',
])]
class MikrotikJobLog extends Model
{
    use HasFactory;

    protected $table = 'mikrotik_job_logs';

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_type' => MikrotikJobType::class,
            'status' => MikrotikJobStatus::class,
            'attempt_count' => 'integer',
            'payload' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke router tujuan.
     *
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    /**
     * Relasi ke layanan pelanggan jika ada.
     *
     * @return BelongsTo<LayananPelanggan, $this>
     */
    public function layananPelanggan(): BelongsTo
    {
        return $this->belongsTo(LayananPelanggan::class, 'layanan_pelanggan_id');
    }

    /**
     * Relasi ke IP pool jika ada.
     *
     * @return BelongsTo<IpPool, $this>
     */
    public function ipPool(): BelongsTo
    {
        return $this->belongsTo(IpPool::class, 'ip_pool_id');
    }
}
