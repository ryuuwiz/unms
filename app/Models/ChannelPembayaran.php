<?php

namespace App\Models;

use App\Enums\GatewayChannel;
use Database\Factories\ChannelPembayaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Channel Pembayaran: satu pilihan bayar (mis. BCA VA, QRIS) milik satu Koneksi Gateway (ADR-0073).
 *
 * @property int $id
 * @property int $pengaturan_gateway_id
 * @property GatewayChannel $tipe
 * @property string $kode
 * @property float $fee_admin
 * @property bool $fee_persen
 * @property string|null $icon_url
 * @property bool $is_active
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PengaturanGateway $pengaturanGateway
 */
#[Fillable([
    'pengaturan_gateway_id',
    'tipe',
    'kode',
    'fee_admin',
    'fee_persen',
    'icon_url',
    'is_active',
    'keterangan',
])]
class ChannelPembayaran extends Model
{
    /** @use HasFactory<ChannelPembayaranFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'channel_pembayaran';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['pengaturan_gateway_id', 'tipe', 'kode', 'fee_admin', 'fee_persen', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('channel_pembayaran');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => GatewayChannel::class,
            'fee_admin' => 'decimal:2',
            'fee_persen' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PengaturanGateway, $this>
     */
    public function pengaturanGateway(): BelongsTo
    {
        return $this->belongsTo(PengaturanGateway::class);
    }

    /**
     * Channel ON yang Koneksi Gateway-nya aktif dan driver-nya mendukung pembayaran per channel.
     *
     * @param  Builder<ChannelPembayaran>  $query
     * @param  list<string>  $provider
     * @return Builder<ChannelPembayaran>
     */
    public function scopeTersedia(Builder $query, array $provider): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('pengaturanGateway', fn (Builder $q) => $q->where('is_active', true)->whereIn('provider', $provider));
    }

    /**
     * Fee Admin dalam rupiah bulat untuk nominal tagihan ini.
     */
    public function hitungFee(float $nominalTagihan): float
    {
        return round($this->fee_persen ? $nominalTagihan * (float) $this->fee_admin / 100 : (float) $this->fee_admin);
    }

    public function labelFee(): string
    {
        if ($this->fee_persen) {
            return rtrim(rtrim(number_format((float) $this->fee_admin, 2, ',', '.'), '0'), ',').'%';
        }

        return 'Rp '.number_format((float) $this->fee_admin, 0, ',', '.');
    }
}
