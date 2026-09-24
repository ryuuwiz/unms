<?php

namespace App\Models;

use App\Enums\StatusWebhookLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property int|null $transaksi_payment_gateway_id
 * @property string $event_type
 * @property string|null $provider_event_id
 * @property string|null $xendit_event_id
 * @property array<string, mixed>|null $payload
 * @property StatusWebhookLog $status_proses
 * @property string|null $catatan_error
 * @property Carbon $diterima_pada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TransaksiPaymentGateway|null $transaksiPaymentGateway
 */
#[Fillable([
    'provider',
    'transaksi_payment_gateway_id',
    'event_type',
    'provider_event_id',
    'xendit_event_id',
    'payload',
    'status_proses',
    'catatan_error',
    'diterima_pada',
])]
class WebhookLog extends Model
{
    protected $table = 'webhook_log';

    public function setProviderEventIdAttribute(?string $value): void
    {
        $this->attributes['provider_event_id'] = ! empty($value) ? trim($value) : null;
    }

    public function setXenditEventIdAttribute(?string $value): void
    {
        $val = ! empty($value) ? trim($value) : null;
        $this->attributes['xendit_event_id'] = $val;
        if (empty($this->attributes['provider_event_id'])) {
            $this->attributes['provider_event_id'] = $val;
        }
    }

    public function getXenditEventIdAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['provider_event_id'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_proses' => StatusWebhookLog::class,
            // Payload gateway memuat PII (email, nomor VA, nama, no. HP pelanggan) --
            // dienkripsi at-rest, konsisten dengan PengaturanGateway::credentials.
            'payload' => 'encrypted:array',
            'diterima_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TransaksiPaymentGateway, $this>
     */
    public function transaksiPaymentGateway(): BelongsTo
    {
        return $this->belongsTo(TransaksiPaymentGateway::class, 'transaksi_payment_gateway_id');
    }
}
