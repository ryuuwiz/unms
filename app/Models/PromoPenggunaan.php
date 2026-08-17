<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $promo_id
 * @property int $pelanggan_id
 * @property int $invoice_id
 * @property Carbon $digunakan_pada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Promo $promo
 * @property-read Pelanggan $pelanggan
 * @property-read Invoice $invoice
 */
#[Fillable([
    'promo_id',
    'pelanggan_id',
    'invoice_id',
    'digunakan_pada',
])]
class PromoPenggunaan extends Model
{
    protected $table = 'promo_penggunaan';

    protected function casts(): array
    {
        return [
            'digunakan_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Promo, $this>
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class, 'promo_id');
    }

    /**
     * @return BelongsTo<Pelanggan, $this>
     */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
