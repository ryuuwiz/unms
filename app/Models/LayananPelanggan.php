<?php

namespace App\Models;

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use Database\Factories\LayananPelangganFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $pelanggan_id
 * @property int $paket_layanan_id
 * @property int $router_id
 * @property string $site_id
 * @property string $ppp_username
 * @property string $ppp_password_terenkripsi
 * @property string|null $ip_static
 * @property int|null $odp_port_id
 * @property JenisKoneksi $jenis_koneksi
 * @property StatusLayanan $status
 * @property Carbon $tanggal_mulai
 * @property Carbon|null $tanggal_expired
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pelanggan $pelanggan
 * @property-read PaketLayanan $paketLayanan
 * @property-read Router $router
 * @property-read OdpPort|null $odpPort
 */
#[Fillable([
    'pelanggan_id',
    'paket_layanan_id',
    'router_id',
    'ppp_username',
    'ppp_password_terenkripsi',
    'ip_static',
    'odp_port_id',
    'jenis_koneksi',
    'status',
    'tanggal_mulai',
    'tanggal_expired',
])]
class LayananPelanggan extends Model
{
    /** @use HasFactory<LayananPelangganFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'layanan_pelanggan';

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'paket_layanan_id', 'router_id', 'tanggal_expired'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('layanan_pelanggan');
    }

    /**
     * Auto-generate site_id saat creating.
     */
    protected static function booted(): void
    {
        static::creating(function (LayananPelanggan $layanan) {
            if (empty($layanan->site_id)) {
                $layanan->site_id = static::generateSiteId();
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
            'jenis_koneksi' => JenisKoneksi::class,
            'status' => StatusLayanan::class,
            'ppp_password_terenkripsi' => 'encrypted',
            'tanggal_mulai' => 'date',
            'tanggal_expired' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke pelanggan pemilik layanan ini.
     *
     * @return BelongsTo<Pelanggan, $this>
     */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    /**
     * Relasi ke paket layanan yang digunakan.
     *
     * @return BelongsTo<PaketLayanan, $this>
     */
    public function paketLayanan(): BelongsTo
    {
        return $this->belongsTo(PaketLayanan::class, 'paket_layanan_id');
    }

    /**
     * Relasi ke router yang mengelola layanan ini.
     *
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    /**
     * Relasi ke port ODP yang digunakan (opsional).
     *
     * @return BelongsTo<OdpPort, $this>
     */
    public function odpPort(): BelongsTo
    {
        return $this->belongsTo(OdpPort::class, 'odp_port_id');
    }

    /**
     * Relasi ke seluruh invoice tagihan layanan ini.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'layanan_pelanggan_id');
    }

    /**
     * Scope filter layanan yang berstatus aktif.
     *
     * @param  Builder<LayananPelanggan>  $query
     * @return Builder<LayananPelanggan>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StatusLayanan::Aktif);
    }

    /**
     * Scope filter layanan yang jatuh tempo sebelum tanggal tertentu.
     *
     * @param  Builder<LayananPelanggan>  $query
     * @return Builder<LayananPelanggan>
     */
    public function scopeExpiredSebelum(Builder $query, Carbon $tanggal): Builder
    {
        return $query->where('tanggal_expired', '<=', $tanggal);
    }

    /**
     * Apakah layanan sedang aktif dan online.
     */
    public function isAktif(): bool
    {
        return $this->status === StatusLayanan::Aktif;
    }

    /**
     * Generate site_id unik (format: SITE-XXXXXXXX).
     */
    public static function generateSiteId(): string
    {
        do {
            $siteId = 'SITE-'.strtoupper(Str::random(8));
        } while (static::where('site_id', $siteId)->exists());

        return $siteId;
    }
}
