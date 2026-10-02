<?php

namespace App\Models;

use App\Enums\AksiPelunasanSusulan;
use Database\Factories\KasusPelunasanSusulanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kasus Pelunasan Susulan (CONTEXT.md): pembayaran gateway yang perlu tindakan manual staf.
 *
 * @property int $id
 * @property string $external_id
 * @property string $alasan
 * @property AksiPelunasanSusulan $aksi
 * @property string|null $xendit_id
 * @property string|null $koneksi
 * @property int|null $invoice_id
 * @property string|null $nominal
 * @property Carbon|null $dibayar_pada
 * @property string|null $status_invoice_saat_itu
 * @property Carbon|null $ditangani_pada
 * @property int|null $ditangani_oleh
 * @property string|null $catatan_penanganan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice|null $invoice
 * @property-read User|null $penangan
 */
#[Fillable([
    'external_id',
    'alasan',
    'aksi',
    'xendit_id',
    'koneksi',
    'invoice_id',
    'nominal',
    'dibayar_pada',
    'status_invoice_saat_itu',
    'ditangani_pada',
    'ditangani_oleh',
    'catatan_penanganan',
])]
class KasusPelunasanSusulan extends Model
{
    /** @use HasFactory<KasusPelunasanSusulanFactory> */
    use HasFactory;

    protected $table = 'kasus_pelunasan_susulan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aksi' => AksiPelunasanSusulan::class,
            'nominal' => 'decimal:2',
            'dibayar_pada' => 'datetime',
            'ditangani_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function penangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    /**
     * Kasus yang belum ditandai Sudah Ditangani.
     *
     * @param  Builder<self>  $query
     */
    public function scopeTerbuka(Builder $query): void
    {
        $query->whereNull('ditangani_pada');
    }

    public function sudahDitangani(): bool
    {
        return $this->ditangani_pada !== null;
    }
}
