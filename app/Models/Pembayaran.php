<?php

namespace App\Models;

use App\Enums\MetodePembayaran;
use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $invoice_id
 * @property MetodePembayaran $metode
 * @property string|null $referensi_transaksi
 * @property float $jumlah_dibayar
 * @property Carbon $dibayar_pada
 * @property string|null $bukti_pembayaran_path
 * @property int|null $dicatat_oleh
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Invoice $invoice
 * @property-read User|null $dicatatOleh
 */
#[Fillable([
    'invoice_id',
    'metode',
    'referensi_transaksi',
    'jumlah_dibayar',
    'dibayar_pada',
    'bukti_pembayaran_path',
    'dicatat_oleh',
    'catatan',
])]
class Pembayaran extends Model
{
    /** @use HasFactory<PembayaranFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'pembayaran';

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['invoice_id', 'metode', 'jumlah_dibayar', 'referensi_transaksi', 'dibayar_pada'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pembayaran');
    }

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metode' => MetodePembayaran::class,
            'jumlah_dibayar' => 'decimal:2',
            'dibayar_pada' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function formattedJumlah(): string
    {
        return 'Rp '.number_format((float) $this->jumlah_dibayar, 0, ',', '.');
    }
}
