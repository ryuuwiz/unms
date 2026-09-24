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
 * @property float $jumlah_tunggakan
 * @property int|null $digabung_ke_invoice_id
 * @property int|null $promo_id
 * @property StatusInvoice $status
 * @property Carbon $tanggal_terbit
 * @property Carbon $tanggal_jatuh_tempo
 * @property Carbon|null $tanggal_lunas
 * @property MetodePembayaran|null $metode_pembayaran
 * @property int|null $dibuat_oleh
 * @property int|null $dihapus_oleh
 * @property string|null $keterangan_hapus
 * @property string|null $periode_tagihan
 * @property string|null $keterangan
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $payment_gateway_url
 * @property string|null $payment_gateway_id
 * @property string|null $payment_gateway_provider
 * @property string|null $payment_gateway_status
 * @property Carbon|null $payment_gateway_expired_at
 * @property string|null $xendit_invoice_id
 * @property string|null $xendit_invoice_url
 * @property string|null $xendit_status
 * @property Carbon|null $xendit_expired_at
 * @property-read Pelanggan|null $pelanggan Null bila Pelanggan di-soft-delete
 * @property-read LayananPelanggan|null $layananPelanggan Null bila layanan di-soft-delete
 * @property-read Promo|null $promo
 * @property-read User|null $dibuatOleh
 * @property-read User|null $dihapusOleh
 */
#[Fillable([
    'no_invoice',
    'periode_tagihan',
    'keterangan',
    'pelanggan_id',
    'layanan_pelanggan_id',
    'jumlah',
    'jumlah_setelah_promo',
    'jumlah_tunggakan',
    'digabung_ke_invoice_id',
    'promo_id',
    'status',
    'tanggal_terbit',
    'tanggal_jatuh_tempo',
    'tanggal_lunas',
    'metode_pembayaran',
    'payment_gateway_url',
    'payment_gateway_id',
    'payment_gateway_provider',
    'payment_gateway_status',
    'payment_gateway_expired_at',
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
                $pelangganId = $invoice->pelanggan_id;
                if (! $pelangganId && $invoice->layanan_pelanggan_id) {
                    $pelangganId = DB::table('layanan_pelanggan')
                        ->where('id', $invoice->layanan_pelanggan_id)
                        ->value('pelanggan_id');
                }
                $invoice->no_invoice = static::generateNoInvoice($pelangganId, $invoice->periode_tagihan);
            }
        });
    }

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['no_invoice', 'periode_tagihan', 'status', 'jumlah_setelah_promo', 'jumlah_tunggakan', 'digabung_ke_invoice_id', 'metode_pembayaran', 'tanggal_lunas', 'payment_gateway_provider'])
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
            'jumlah_tunggakan' => 'decimal:2',
            'tanggal_terbit' => 'date',
            'tanggal_jatuh_tempo' => 'date',
            'tanggal_lunas' => 'date',
            'payment_gateway_expired_at' => 'datetime',
            'xendit_expired_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate nomor invoice menyertakan No. Registrasi Pelanggan: INV-[No.Reg]-[YYYYMM]-[Counter]
     */
    public static function generateNoInvoice(?int $pelangganId = null, ?string $periodeTagihan = null): string
    {
        $noReg = 'GENERAL';

        if ($pelangganId) {
            $foundNoReg = DB::table('pelanggan')->where('id', $pelangganId)->value('no_reg');
            if (! empty($foundNoReg)) {
                $noReg = trim($foundNoReg);
            }
        }

        if (! empty($periodeTagihan)) {
            $period = str_replace('-', '', $periodeTagihan);
        } else {
            $period = Carbon::now()->format('Ym');
        }

        $prefix = "INV-{$noReg}-{$period}-";

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

        return $prefix.str_pad((string) $nextNum, 2, '0', STR_PAD_LEFT);
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
     * Invoice siklus lebih baru yang menyerap nominal invoice ini (Tunggakan Akumulatif).
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function digabungKe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'digabung_ke_invoice_id');
    }

    /**
     * Invoice periodik lama yang nominalnya sudah diserap invoice ini.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoiceDigabung(): HasMany
    {
        return $this->hasMany(self::class, 'digabung_ke_invoice_id');
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

    public function isDibatalkan(): bool
    {
        return $this->status === StatusInvoice::Dibatalkan;
    }

    public function isDigabung(): bool
    {
        return $this->status === StatusInvoice::Digabung;
    }

    /**
     * Cek apakah invoice memiliki tautan pembayaran gateway yang aktif dan belum kedaluwarsa.
     */
    public function hasActivePaymentLink(): bool
    {
        $url = $this->payment_gateway_url ?: ($this->attributes['xendit_invoice_url'] ?? null);
        if (empty($url)) {
            return false;
        }

        // Link mock (dibuat oleh bug yang diperbaiki di ADR 0039, sebelum APP_ENV=testing
        // menjadi satu-satunya syarat mock) tidak pernah menjadi invoice Xendit sungguhan --
        // ID/URL-nya mengandung 'inv_mock_' dan tidak akan pernah bisa dibuka pelanggan.
        // Invoice lama yang masih menyimpan link ini harus dipaksa regenerasi (bukan dianggap
        // "masih aktif" hanya karena kolom status/expired_at belum lewat), walau tidak ada
        // migrasi backfill -- self-healing begitu invoice ini disentuh lagi. Dikecualikan di
        // APP_ENV=testing: di sana 'inv_mock_' SELALU sengaja dibuat (satu-satunya environment
        // yang boleh mock sama sekali) dan test lain bergantung padanya berperilaku seperti link
        // normal.
        if (str_contains($url, 'inv_mock_') && ! app()->environment('testing')) {
            return false;
        }

        $status = strtoupper((string) ($this->payment_gateway_status ?: ($this->attributes['xendit_status'] ?? '')));
        if ($status === 'EXPIRED' || $status === 'PAID') {
            return false;
        }

        $expiredAt = $this->payment_gateway_expired_at ?: $this->xendit_expired_at;
        if ($expiredAt && $expiredAt->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Alias kompatibilitas mundur.
     */
    public function hasActiveXenditInvoice(): bool
    {
        return $this->hasActivePaymentLink();
    }

    /**
     * Cek apakah sesi payment gateway invoice telah kedaluwarsa.
     */
    public function isPaymentLinkExpired(): bool
    {
        $status = strtoupper((string) ($this->payment_gateway_status ?: ($this->attributes['xendit_status'] ?? '')));
        if ($status === 'EXPIRED') {
            return true;
        }

        $expiredAt = $this->payment_gateway_expired_at ?: $this->xendit_expired_at;

        return $expiredAt ? $expiredAt->isPast() : false;
    }

    /**
     * Alias kompatibilitas mundur.
     */
    public function isXenditInvoiceExpired(): bool
    {
        return $this->isPaymentLinkExpired();
    }

    /**
     * Accessor URL link pembayaran (kompatibilitas mundur).
     */
    public function getXenditInvoiceUrlAttribute(?string $value): ?string
    {
        return $this->payment_gateway_url ?: $value;
    }

    /**
     * Accessor ID invoice gateway (kompatibilitas mundur).
     */
    public function getXenditInvoiceIdAttribute(?string $value): ?string
    {
        return $this->payment_gateway_id ?: $value;
    }

    /**
     * Accessor status gateway (kompatibilitas mundur).
     */
    public function getXenditStatusAttribute(?string $value): ?string
    {
        return $this->payment_gateway_status ?: $value;
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
     * Format tampilan nama bulan periode tagihan (contoh: 'Agustus 2026').
     */
    public function formattedPeriodeTagihan(): string
    {
        if (empty($this->periode_tagihan)) {
            return '-';
        }

        try {
            return Carbon::createFromFormat('Y-m', $this->periode_tagihan)->translatedFormat('F Y');
        } catch (\Throwable) {
            return $this->periode_tagihan;
        }
    }

    /**
     * Scope filter invoice berdasarkan periode tagihan (YYYY-MM).
     *
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopePeriode(Builder $query, string $periode): Builder
    {
        return $query->where('periode_tagihan', $periode);
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
