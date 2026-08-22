<?php

namespace App\Models;

use App\Enums\MetodePembayaran;
use App\Enums\StatusInvoice;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $no_invoice
 * @property int $pelanggan_id
 * @property int $layanan_pelanggan_id
 * @property float $jumlah
 * @property float $jumlah_setelah_promo
 * @property int|null $promo_id
 * @property StatusInvoice $status
 * @property Carbon $tanggal_terbit
 * @property Carbon $tanggal_jatuh_tempo
 * @property Carbon|null $tanggal_lunas
 * @property MetodePembayaran|string|null $metode_pembayaran
 * @property int|null $dibuat_oleh
 * @property int|null $dihapus_oleh
 * @property string|null $keterangan_hapus
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $xendit_invoice_id
 * @property string|null $xendit_invoice_url
 * @property string|null $xendit_status
 * @property Carbon|null $xendit_expired_at
 * @property-read Pelanggan $pelanggan
 * @property-read LayananPelanggan $layananPelanggan
 * @property-read Promo|null $promo
 * @property-read User|null $dibuatOleh
 * @property-read User|null $dihapusOleh
 */
#[Fillable([
    'no_invoice',
    'pelanggan_id',
    'layanan_pelanggan_id',
    'jumlah',
    'jumlah_setelah_promo',
    'promo_id',
    'status',
    'tanggal_terbit',
    'tanggal_jatuh_tempo',
    'tanggal_lunas',
    'metode_pembayaran',
    'xendit_invoice_id',
    'xendit_invoice_url',
    'xendit_status',
    'xendit_expired_at',
    'dibuat_oleh',
    'dihapus_oleh',
    'keterangan_hapus',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'invoice';

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->no_invoice)) {
                $invoice->no_invoice = static::generateNoInvoice();
            }
        });
    }

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['no_invoice', 'status', 'jumlah_setelah_promo', 'metode_pembayaran', 'tanggal_lunas'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('invoice');
    }

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusInvoice::class,
            'metode_pembayaran' => MetodePembayaran::class,
            'jumlah' => 'decimal:2',
            'jumlah_setelah_promo' => 'decimal:2',
            'tanggal_terbit' => 'date',
            'tanggal_jatuh_tempo' => 'date',
            'tanggal_lunas' => 'date',
            'xendit_expired_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate nomor invoice berurutan per bulan: INV-YYYYMM-NNNNNN
     */
    public static function generateNoInvoice(): string
    {
        $prefix = 'INV-'.Carbon::now()->format('Ym').'-';

        $last = DB::table('invoice')
            ->where('no_invoice', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('no_invoice');

        if ($last) {
            $lastNum = (int) substr($last, strlen($prefix));
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix.str_pad((string) $nextNum, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Pelanggan, $this>
     */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    /**
     * @return BelongsTo<LayananPelanggan, $this>
     */
    public function layananPelanggan(): BelongsTo
    {
        return $this->belongsTo(LayananPelanggan::class, 'layanan_pelanggan_id');
    }

    /**
     * @return BelongsTo<Promo, $this>
     */
    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class, 'promo_id');
    }

    /**
     * @return HasMany<Pembayaran, $this>
     */
    public function pembayarans(): HasMany
    {
        return $this->hasMany(Pembayaran::class, 'invoice_id');
    }

    /**
     * @return HasMany<TransaksiPaymentGateway, $this>
     */
    public function transaksiPaymentGateways(): HasMany
    {
        return $this->hasMany(TransaksiPaymentGateway::class, 'invoice_id');
    }

    /**
     * Transaksi payment gateway terakhir/aktif.
     */
    public function transaksiPaymentGatewayAktif(): ?TransaksiPaymentGateway
    {
        return $this->transaksiPaymentGateways()
            ->latest('id')
            ->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dihapusOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihapus_oleh');
    }

    public function isLunas(): bool
    {
        return $this->status === StatusInvoice::Lunas;
    }

    public function isMenungguPembayaran(): bool
    {
        return $this->status === StatusInvoice::MenungguPembayaran;
    }

    public function isKadaluarsa(): bool
    {
        return $this->status === StatusInvoice::Kadaluarsa;
    }

    /**
     * Cek apakah invoice memiliki tautan pembayaran Xendit yang aktif dan belum kedaluwarsa.
     */
    public function hasActiveXenditInvoice(): bool
    {
        if (empty($this->xendit_invoice_url)) {
            return false;
        }

        if ($this->xendit_status === 'EXPIRED' || $this->xendit_status === 'PAID') {
            return false;
        }

        if ($this->xendit_expired_at && $this->xendit_expired_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Cek apakah sesi Xendit invoice telah kedaluwarsa.
     */
    public function isXenditInvoiceExpired(): bool
    {
        if ($this->xendit_status === 'EXPIRED') {
            return true;
        }

        return $this->xendit_expired_at ? $this->xendit_expired_at->isPast() : false;
    }

    public function formattedJumlah(): string
    {
        return 'Rp '.number_format((float) $this->jumlah, 0, ',', '.');
    }

    public function formattedJumlahSetelahPromo(): string
    {
        return 'Rp '.number_format((float) $this->jumlah_setelah_promo, 0, ',', '.');
    }

    /**
     * Scope filter invoice menunggu pembayaran.
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeMenungguPembayaran(Builder $query): Builder
    {
        return $query->where('status', StatusInvoice::MenungguPembayaran);
    }

    /**
     * Scope filter invoice lunas.
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeLunas(Builder $query): Builder
    {
        return $query->where('status', StatusInvoice::Lunas);
    }

    /**
     * Scope filter pencarian teks.
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('no_invoice', 'like', "%{$term}%")
                ->orWhereHas('pelanggan', function (Builder $customerQuery) use ($term) {
                    $customerQuery->where('nama_depan', 'like', "%{$term}%")
                        ->orWhere('nama_belakang', 'like', "%{$term}%")
                        ->orWhere('no_reg', 'like', "%{$term}%")
                        ->orWhere('no_hp', 'like', "%{$term}%");
                })
                ->orWhereHas('layananPelanggan', function (Builder $layananQuery) use ($term) {
                    $layananQuery->where('site_id', 'like', "%{$term}%")
                        ->orWhere('ppp_username', 'like', "%{$term}%");
                });
        });
    }
}
