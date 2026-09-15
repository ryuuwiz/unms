<?php

namespace App\Models;

use App\Enums\DiskonTipe;
use App\Enums\JenisPromo;
use Database\Factories\PromoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $kode_promo
 * @property string $nama_promo
 * @property JenisPromo $jenis
 * @property string|null $deskripsi
 * @property string|null $aturan
 * @property int|null $bayar_bulan
 * @property int|null $bonus_bulan
 * @property DiskonTipe|null $diskon_tipe
 * @property float|null $diskon_nilai
 * @property float|null $minimal_nominal_invoice
 * @property int|null $kuota_global
 * @property int|null $kuota_per_pelanggan
 * @property int $terpakai_global
 * @property Carbon|null $berlaku_dari
 * @property Carbon|null $berlaku_sampai
 * @property bool $aktif
 * @property bool $tampil_ke_customer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'kode_promo',
    'nama_promo',
    'jenis',
    'deskripsi',
    'aturan',
    'bayar_bulan',
    'bonus_bulan',
    'diskon_tipe',
    'diskon_nilai',
    'minimal_nominal_invoice',
    'kuota_global',
    'kuota_per_pelanggan',
    'terpakai_global',
    'berlaku_dari',
    'berlaku_sampai',
    'aktif',
    'tampil_ke_customer',
])]
class Promo extends Model
{
    /** @use HasFactory<PromoFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'promo';

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['kode_promo', 'nama_promo', 'jenis', 'diskon_nilai', 'aktif'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('promo');
    }

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisPromo::class,
            'diskon_tipe' => DiskonTipe::class,
            'diskon_nilai' => 'decimal:2',
            'minimal_nominal_invoice' => 'decimal:2',
            'berlaku_dari' => 'date',
            'berlaku_sampai' => 'date',
            'aktif' => 'boolean',
            'tampil_ke_customer' => 'boolean',
        ];
    }

    /**
     * Relasi ke seluruh penggunaan promo.
     *
     * @return HasMany<PromoPenggunaan, $this>
     */
    public function penggunaans(): HasMany
    {
        return $this->hasMany(PromoPenggunaan::class, 'promo_id');
    }

    /**
     * Relasi ke seluruh invoice yang memakai promo ini.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'promo_id');
    }

    /**
     * Scope promo yang sedang aktif dan dalam periode valid.
     *
     * @param  Builder<Promo>  $query
     * @return Builder<Promo>
     */
    public function scopeAktif(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query->where('aktif', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('berlaku_dari')->orWhere('berlaku_dari', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $today);
            })
            ->where(function ($q) {
                $q->whereNull('kuota_global')->orWhereColumn('terpakai_global', '<', 'kuota_global');
            });
    }

    /**
     * Cari promo aktif berdasarkan kode yang diketik manual (mis. kode promo global/musiman).
     * Dipakai oleh Invoice\Create dan Pelanggan\Show untuk resolusi kode promo pada invoice manual.
     */
    public static function findAktifByKode(string $kode): ?self
    {
        return static::query()->aktif()->where('kode_promo', trim($kode))->first();
    }

    /**
     * Hitung total potongan diskon dari sebuah nominal.
     */
    public function hitungDiskon(float $nominal): float
    {
        if ($this->minimal_nominal_invoice && $nominal < (float) $this->minimal_nominal_invoice) {
            return 0.0;
        }

        if ($this->jenis !== JenisPromo::Diskon) {
            return 0.0;
        }

        if ($this->diskon_tipe === DiskonTipe::Persentase) {
            $potongan = ($nominal * (float) $this->diskon_nilai) / 100;

            return min($potongan, $nominal);
        }

        if ($this->diskon_tipe === DiskonTipe::Nominal) {
            return min((float) $this->diskon_nilai, $nominal);
        }

        return 0.0;
    }
}
