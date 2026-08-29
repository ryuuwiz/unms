<?php

namespace App\Models;

use App\Enums\GatewayChannel;
use App\Enums\StatusTransaksiGateway;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $gateway
 * @property string $external_id
 * @property string|null $provider_reference_id
 * @property string|null $xendit_reference_id
 * @property GatewayChannel $channel
 * @property string|null $channel_detail
 * @property string|null $nomor_pembayaran
 * @property string|null $qr_string
 * @property float $total_tagihan
 * @property float $fee_gateway
 * @property StatusTransaksiGateway $status
 * @property Carbon|null $expired_at
 * @property array|null $payload_request
 * @property array|null $payload_response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Collection<int, WebhookLog> $webhookLogs
 */
#[Fillable([
    'invoice_id',
    'gateway',
    'external_id',
    'provider_reference_id',
    'xendit_reference_id',
    'channel',
    'channel_detail',
    'nomor_pembayaran',
    'qr_string',
    'total_tagihan',
    'fee_gateway',
    'status',
    'expired_at',
    'payload_request',
    'payload_response',
])]
class TransaksiPaymentGateway extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'transaksi_payment_gateway';

    public function setXenditReferenceIdAttribute(?string $value): void
    {
        $this->attributes['xendit_reference_id'] = $value;
        if (empty($this->attributes['provider_reference_id'])) {
            $this->attributes['provider_reference_id'] = $value;
        }
    }

    public function getXenditReferenceIdAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['provider_reference_id'] ?? null);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['external_id', 'channel', 'channel_detail', 'status', 'total_tagihan', 'fee_gateway'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('payment_gateway');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => GatewayChannel::class,
            'status' => StatusTransaksiGateway::class,
            'total_tagihan' => 'decimal:2',
            'fee_gateway' => 'decimal:2',
            'expired_at' => 'datetime',
            'payload_request' => 'array',
            'payload_response' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * @return HasMany<WebhookLog, $this>
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(WebhookLog::class, 'transaksi_payment_gateway_id');
    }

    public function isPending(): bool
    {
        return $this->status === StatusTransaksiGateway::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === StatusTransaksiGateway::Paid;
    }

    public function isExpired(): bool
    {
        return $this->status === StatusTransaksiGateway::Expired || ($this->expired_at && $this->expired_at->isPast() && $this->status === StatusTransaksiGateway::Pending);
    }

    public function formattedTotalTagihan(): string
    {
        return 'Rp '.number_format((float) $this->total_tagihan, 0, ',', '.');
    }

    public function formattedFeeGateway(): string
    {
        return 'Rp '.number_format((float) $this->fee_gateway, 0, ',', '.');
    }

    /**
     * Scope filter transaksi pending.
     *
     * @param  Builder<TransaksiPaymentGateway>  $query
     * @return Builder<TransaksiPaymentGateway>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StatusTransaksiGateway::Pending);
    }

    /**
     * Scope filter transaksi berhasil/paid.
     *
     * @param  Builder<TransaksiPaymentGateway>  $query
     * @return Builder<TransaksiPaymentGateway>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', StatusTransaksiGateway::Paid);
    }
}
