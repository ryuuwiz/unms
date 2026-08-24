<?php

namespace App\Models;

use App\Enums\JenisKoneksi;
use App\Enums\ProvisioningStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $pelanggan_id
 * @property int $paket_layanan_id
 * @property int $router_id
 * @property int|null $ip_pool_id
 * @property string $site_id
 * @property string $ppp_username
 * @property string $ppp_password_terenkripsi
 * @property string|null $ip_static
 * @property int|null $odp_port_id
 * @property JenisKoneksi $jenis_koneksi
 * @property StatusLayanan $status
 * @property Carbon $tanggal_mulai
 * @property Carbon|null $tanggal_expired
 * @property Carbon|null $terprovisi_pada
 * @property ProvisioningStatus $provisioning_status
 * @property string|null $last_provisioning_error
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pelanggan $pelanggan
 * @property-read PaketLayanan $paketLayanan
 * @property-read Router $router
 * @property-read IpPool|null $ipPool
 * @property-read OdpPort|null $odpPort
 */
#[Fillable([
    'pelanggan_id',
    'paket_layanan_id',
    'router_id',
    'ip_pool_id',
    'ppp_username',
    'ppp_password_terenkripsi',
    'ip_static',
    'odp_port_id',
    'jenis_koneksi',
    'status',
    'tanggal_mulai',
    'tanggal_expired',
    'terprovisi_pada',
    'provisioning_status',
    'last_provisioning_error',
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
            'provisioning_status' => ProvisioningStatus::class,
            'ppp_password_terenkripsi' => 'encrypted',
            'tanggal_mulai' => 'date',
            'tanggal_expired' => 'date',
            'terprovisi_pada' => 'datetime',
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
     * Relasi ke IP Pool yang digunakan sebagai remote-address PPP Secret.
     *
     * @return BelongsTo<IpPool, $this>
     */
    public function ipPool(): BelongsTo
    {
        return $this->belongsTo(IpPool::class, 'ip_pool_id');
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
     * Tentukan nilai remote-address yang harus dikirim ke PPP Secret RouterOS.
     *
     * Prioritas: ip_static (jika ada) → nama IP Pool (ip_pool_id) → null.
     * Nilai ini mencegah tabrakan IP antar pelanggan dan menjamin UNMS
     * sebagai satu-satunya sumber kebenaran alokasi IP.
     */
    public function resolveRemoteAddress(): ?string
    {
        if (! empty($this->ip_static)) {
            return $this->ip_static;
        }

        return $this->ipPool?->nama_pool ?? null;
    }

    /**
     * Relasi ke seluruh riwayat job MikroTik layanan ini.
     *
     * @return HasMany<MikrotikJobLog, $this>
     */
    public function jobLogs(): HasMany
    {
        return $this->hasMany(MikrotikJobLog::class, 'layanan_pelanggan_id');
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
     * Relasi ke seluruh tiket yang terkait dengan layanan ini.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'layanan_pelanggan_id');
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
     * Dapatkan periode tagihan target berikutnya (format YYYY-MM) berdasarkan tanggal expired.
     */
    public function getNextPeriodeTagihan(): string
    {
        if (! $this->tanggal_expired) {
            return Carbon::today()->format('Y-m');
        }

        $expired = Carbon::parse($this->tanggal_expired);

        // Jika tanggal expired di akhir bulan (>= tgl 25), siklus tagihan berikutnya mencakup bulan depan (H-7)
        if ($expired->day >= 25) {
            return $expired->copy()->addDays(7)->format('Y-m');
        }

        return $expired->format('Y-m');
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

    /**
     * Generate ppp_username unik untuk pelanggan dengan format {no_reg}_{NNNNN}.
     *
     * Counter di-increment dari nilai tertinggi yang sudah ada per pelanggan.
     * Operasi dilindungi DB transaction + lockForUpdate untuk mencegah race condition.
     */
    public static function generatePppUsername(Pelanggan $pelanggan): string
    {
        return DB::transaction(function () use ($pelanggan) {
            $prefix = $pelanggan->no_reg;
            $pattern = $prefix.'_%';

            $existing = static::withTrashed()
                ->lockForUpdate()
                ->where('pelanggan_id', $pelanggan->id)
                ->where('ppp_username', 'like', $pattern)
                ->get(['ppp_username']);

            $maxCounter = $existing
                ->map(fn (self $l) => static::extractCounter($l->ppp_username))
                ->filter(fn (?int $c) => $c !== null)
                ->max() ?? 0;

            $next = $maxCounter + 1;

            return sprintf('%s_%05d', $prefix, $next);
        });
    }

    /**
     * Parse suffix 5-digit angka dari ppp_username berformat {no_reg}_{NNNNN}.
     *
     * Mengembalikan integer counter, atau null jika format tidak sesuai.
     */
    public static function extractCounter(string $pppUsername): ?int
    {
        if (preg_match('/_([0-9]{5})$/', $pppUsername, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
