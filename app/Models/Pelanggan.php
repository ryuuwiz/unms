<?php

namespace App\Models;

use App\Enums\StatusPelanggan;
use App\Enums\TipePelanggan;
use Database\Factories\PelangganFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $no_reg
 * @property TipePelanggan $tipe_pelanggan
 * @property string|null $nik
 * @property string $nama_depan
 * @property string|null $nama_belakang
 * @property string|null $email
 * @property string $no_hp
 * @property string|null $telepon_rumah
 * @property int|null $perumahan_id
 * @property string|null $rt
 * @property string|null $rw
 * @property string|null $no_rumah
 * @property string|null $kode_pos
 * @property string $alamat_lengkap
 * @property float|null $latitude
 * @property float|null $longitude
 * @property StatusPelanggan $status
 * @property string $kode_pembayaran
 * @property string|null $gambar_ktp_path
 * @property int $dibuat_oleh
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $pembuat
 * @property-read Perumahan|null $perumahan
 */
#[Fillable([
    'tipe_pelanggan',
    'nik',
    'nama_depan',
    'nama_belakang',
    'email',
    'no_hp',
    'telepon_rumah',
    'perumahan_id',
    'rt',
    'rw',
    'no_rumah',
    'kode_pos',
    'alamat_lengkap',
    'latitude',
    'longitude',
    'status',
    'gambar_ktp_path',
    'dibuat_oleh',
])]
class Pelanggan extends Model
{
    /** @use HasFactory<PelangganFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'pelanggan';

    /**
     * Konfigurasi logging aktivitas via Spatie activitylog.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_depan', 'nama_belakang', 'email', 'no_hp', 'status', 'tipe_pelanggan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pelanggan');
    }

    /**
     * Boot model untuk auto-generate no_reg dan kode_pembayaran saat creating.
     */
    protected static function booted(): void
    {
        static::creating(function (Pelanggan $pelanggan) {
            if (empty($pelanggan->no_reg)) {
                $pelanggan->no_reg = static::generateNoReg();
            }

            if (empty($pelanggan->kode_pembayaran)) {
                $pelanggan->kode_pembayaran = static::generateKodePembayaran();
            }

            if (! empty($pelanggan->no_hp)) {
                $pelanggan->no_hp = static::normalizePhone($pelanggan->no_hp);
            }
        });

        static::created(function (Pelanggan $pelanggan) {
            if (! empty($pelanggan->email) && ! $pelanggan->akunPelanggan()->exists()) {
                AkunPelanggan::firstOrCreate(
                    ['email' => strtolower(trim($pelanggan->email))],
                    [
                        'pelanggan_id' => $pelanggan->id,
                        'password' => '12345678',
                    ]
                );
            }
        });
    }

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe_pelanggan' => TipePelanggan::class,
            'status' => StatusPelanggan::class,
            'nik' => 'encrypted',
            'latitude' => 'float',
            'longitude' => 'float',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke user pembuat data pelanggan.
     *
     * @return BelongsTo<User, $this>
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Alias relasi ke user pembuat data pelanggan (dibuat_oleh).
     *
     * @return BelongsTo<User, $this>
     */
    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * Relasi ke perumahan tempat tinggal pelanggan.
     *
     * @return BelongsTo<Perumahan, $this>
     */
    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class, 'perumahan_id');
    }

    /**
     * Relasi ke semua layanan internet yang dimiliki pelanggan.
     *
     * @return HasMany<LayananPelanggan, $this>
     */
    public function layanans(): HasMany
    {
        return $this->hasMany(LayananPelanggan::class, 'pelanggan_id');
    }

    /**
     * Relasi ke semua invoice tagihan pelanggan.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'pelanggan_id');
    }

    /**
     * Relasi ke akun portal pelanggan.
     *
     * @return HasOne<AkunPelanggan, $this>
     */
    public function akunPelanggan(): HasOne
    {
        return $this->hasOne(AkunPelanggan::class, 'pelanggan_id');
    }

    /**
     * Relasi ke semua tiket permohonan/gangguan milik pelanggan.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'pelanggan_id');
    }

    /**
     * Scope filter pelanggan aktif.
     *
     * @param  Builder<Pelanggan>  $query
     * @return Builder<Pelanggan>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StatusPelanggan::Aktif);
    }

    /**
     * Scope filter pelanggan prospek (belum aktif).
     *
     * @param  Builder<Pelanggan>  $query
     * @return Builder<Pelanggan>
     */
    public function scopeProspek(Builder $query): Builder
    {
        return $query->where('status', StatusPelanggan::Prospek);
    }

    /**
     * Scope pencarian berdasarkan nama, no. HP, email, atau no_reg.
     *
     * @param  Builder<Pelanggan>  $query
     * @return Builder<Pelanggan>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('nama_depan', 'like', "%{$term}%")
                ->orWhere('nama_belakang', 'like', "%{$term}%")
                ->orWhere('no_hp', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('no_reg', 'like', "%{$term}%");
        });
    }

    /**
     * Nama lengkap pelanggan (gabungan nama depan dan belakang).
     */
    public function namaLengkap(): string
    {
        return trim("{$this->nama_depan} {$this->nama_belakang}");
    }

    /**
     * Cek apakah pelanggan berstatus aktif.
     */
    public function isAktif(): bool
    {
        return $this->status === StatusPelanggan::Aktif;
    }

    /**
     * Normalisasi nomor HP Indonesia ke format standar 628xxxxxxxxxx.
     */
    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        if (str_starts_with($cleaned, '+62')) {
            return substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '08')) {
            return '628'.substr($cleaned, 2);
        }

        if (str_starts_with($cleaned, '628')) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '8')) {
            return '62'.$cleaned;
        }

        return $cleaned;
    }

    /**
     * Generate no_reg unik dengan format REG-YYYY-NNNNNN (global sequence).
     * MariaDB: CAST(... AS UNSIGNED INTEGER).
     */
    public static function generateNoReg(): string
    {
        return DB::transaction(function () {
            $year = now()->year;

            $latest = static::withTrashed()
                ->lockForUpdate()
                ->where('no_reg', 'like', 'REG-%')
                ->orderByRaw('CAST(SUBSTRING(no_reg, 12) AS UNSIGNED INTEGER) DESC')
                ->first();

            $nextNumber = 1;

            if ($latest && preg_match('/^REG-\d{4}-(\d+)$/', $latest->no_reg, $matches)) {
                $nextNumber = ((int) $matches[1]) + 1;
            }

            return sprintf('REG-%d-%06d', $year, $nextNumber);
        });
    }

    /**
     * Generate kode_pembayaran unik 10-char alphanumeric untuk VA Xendit.
     */
    public static function generateKodePembayaran(): string
    {
        do {
            $kode = strtoupper(Str::random(10));
        } while (static::where('kode_pembayaran', $kode)->exists());

        return $kode;
    }
}
