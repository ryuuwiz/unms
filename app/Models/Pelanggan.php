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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
 * @property int $dibuat_oleh
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $pembuat
 * @property-read Perumahan|null $perumahan
 */
#[Fillable([
    'no_reg',
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
    'dibuat_oleh',
])]
class Pelanggan extends Model implements HasMedia
{
    /** @use HasFactory<PelangganFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $table = 'pelanggan';

    /**
     * Konfigurasi collection Spatie MediaLibrary pada disk privat.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ktp')
            ->singleFile()
            ->useDisk('local');

        $this->addMediaCollection('dokumen')
            ->useDisk('local');
    }

    /**
     * Cek apakah pelanggan memiliki berkas KTP.
     */
    public function hasKtp(): bool
    {
        return $this->hasMedia('ktp');
    }

    /**
     * Ambil objek Media KTP pertama pelanggan.
     */
    public function getKtpMedia(): ?Media
    {
        return $this->getFirstMedia('ktp');
    }

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
     * Scope filter pelanggan belum terpasang.
     *
     * @param  Builder<Pelanggan>  $query
     * @return Builder<Pelanggan>
     */
    public function scopeBelumTerpasang(Builder $query): Builder
    {
        return $query->where('status', StatusPelanggan::BelumTerpasang);
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
     * Format identitas lengkap pelanggan terpadu (No. Reg_Nama Pelanggan).
     */
    public function identitasLengkap(): string
    {
        return "{$this->no_reg}_{$this->namaLengkap()}";
    }

    /**
     * Accessor atribut identitas_lengkap.
     */
    public function getIdentitasLengkapAttribute(): string
    {
        return $this->identitasLengkap();
    }

    /**
     * Format label untuk dropdown selector dengan informasi sekunder (No. HP & Perumahan).
     */
    public function labelSelector(): string
    {
        $info = [];
        if (! empty($this->no_hp)) {
            $info[] = $this->no_hp;
        }

        $namaPerumahan = $this->relationLoaded('perumahan')
            ? $this->perumahan?->nama_perumahan
            : ($this->perumahan_id ? $this->perumahan?->nama_perumahan : null);

        if (! empty($namaPerumahan)) {
            $info[] = $namaPerumahan;
        }

        $suffix = ! empty($info) ? ' ('.implode(' • ', $info).')' : '';

        return "{$this->no_reg}_{$this->namaLengkap()}{$suffix}";
    }

    /**
     * Accessor atribut label_selector.
     */
    public function getLabelSelectorAttribute(): string
    {
        return $this->labelSelector();
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
     * Generate no_reg unik dengan format [Prefix][DDMMYYYY][Sequence 2-Digit] (contoh: BF2308202601).
     */
    public static function generateNoReg(?string $prefix = null): string
    {
        $cleanPrefix = $prefix ? strtoupper(trim($prefix)) : 'BF';
        $dateStr = now()->format('dmY');

        return DB::transaction(function () use ($cleanPrefix, $dateStr) {
            $prefixDate = $cleanPrefix.$dateStr;
            $pattern = $prefixDate.'%';

            $latest = static::withTrashed()
                ->lockForUpdate()
                ->where('no_reg', 'like', $pattern)
                ->orderBy('no_reg', 'desc')
                ->first();

            $nextNumber = 1;

            if ($latest && preg_match('/^'.preg_quote($prefixDate, '/').'(\d+)$/', $latest->no_reg, $matches)) {
                $nextNumber = ((int) $matches[1]) + 1;
            }

            return sprintf('%s%02d', $prefixDate, $nextNumber);
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
