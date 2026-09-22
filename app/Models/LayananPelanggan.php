<?php

namespace App\Models;

use App\Enums\JenisKoneksi;
use App\Enums\PriceMode;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Models\Concerns\GracefullyDecryptsAttributes;
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
 * @property PriceMode $price_mode
 * @property float|null $price_custom
 * @property int|null $router_id
 * @property int|null $ip_pool_id
 * @property string $site_id
 * @property string|null $nama_site
 * @property string|null $ppp_username
 * @property string|null $ppp_password_terenkripsi
 * @property string|null $ip_static
 * @property string|null $ip_dynamic
 * @property int|null $odp_port_id
 * @property string|null $alamat_pemasangan
 * @property float|null $latitude
 * @property float|null $longitude
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
 * @property-read Router|null $router
 * @property-read IpPool|null $ipPool
 * @property-read OdpPort|null $odpPort
 */
#[Fillable([
    'pelanggan_id',
    'paket_layanan_id',
    'price_mode',
    'price_custom',
    'router_id',
    'ip_pool_id',
    'site_id',
    'nama_site',
    'ppp_username',
    'ppp_password_terenkripsi',
    'ip_static',
    'ip_dynamic',
    'odp_port_id',
    'alamat_pemasangan',
    'latitude',
    'longitude',
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
    use GracefullyDecryptsAttributes, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'layanan_pelanggan';

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'paket_layanan_id', 'router_id', 'tanggal_expired', 'nama_site'])
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
            'price_mode' => PriceMode::class,
            'price_custom' => 'float',
            'jenis_koneksi' => JenisKoneksi::class,
            'status' => StatusLayanan::class,
            'provisioning_status' => ProvisioningStatus::class,
            'ppp_password_terenkripsi' => 'encrypted',
            'latitude' => 'float',
            'longitude' => 'float',
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
     * Prioritas: ip_static (jika jenis IP Static) → ip_dynamic (IP literal hasil auto-assign dari IP Pool
     * untuk PPPoE dinamis, lihat MikrotikService::allocateDynamicIp()) → null.
     *
     * PENTING: nama IP Pool TIDAK BOLEH dikirim langsung sebagai remote-address -- pada `/ppp/secret`,
     * RouterOS menolaknya dengan "invalid value for argument remote-address" (berbeda dari `/ppp/profile`,
     * yang menerima nama pool). remote-address harus selalu berupa alamat IP literal.
     */
    public function resolveRemoteAddress(): ?string
    {
        if (! empty($this->ip_static) && filter_var($this->ip_static, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ip_static;
        }

        return $this->ip_dynamic;
    }

    /**
     * Tentukan nilai local-address (Gateway) yang harus dikirim ke PPP Secret RouterOS.
     *
     * Prioritas: Gateway dari IP Pool terkait → Subnet Gateway dari ip_static → null.
     */
    public function resolveLocalAddress(): ?string
    {
        if ($this->ipPool) {
            return $this->ipPool->getGatewayAddress();
        }

        if (! empty($this->ip_static) && filter_var($this->ip_static, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($this->ip_static);
            if ($ipLong !== false) {
                $networkLong = $ipLong & ip2long('255.255.255.0');

                return long2ip($networkLong + 1);
            }
        }

        return null;
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
     * Scope layanan Perlu Perhatian: berstatus aktif/suspend dengan tanggal expired dalam
     * jendela 30 hari ke belakang sampai `$leadDays` hari ke depan.
     *
     * @param  Builder<LayananPelanggan>  $query
     * @param  'all'|'overdue'|'soon'  $jenis
     * @return Builder<LayananPelanggan>
     */
    public function scopePerluPerhatian(Builder $query, int $leadDays, string $jenis = 'all'): Builder
    {
        $today = Carbon::today();

        $dari = $jenis === 'soon' ? $today : $today->copy()->subDays(30);
        $sampai = $jenis === 'overdue' ? $today->copy()->subDay() : $today->copy()->addDays($leadDays);

        return $query
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Suspend])
            ->whereBetween('tanggal_expired', [$dari->toDateString(), $sampai->toDateString()]);
    }

    /**
     * Cek apakah masa aktif layanan sudah kedaluwarsa (expired).
     */
    public function isExpired(): bool
    {
        if ($this->tanggal_expired === null) {
            return false;
        }

        return $this->tanggal_expired->isPast();
    }

    /**
     * Dapatkan label status untuk tampilan antarmuka.
     * Jika masa aktif (tanggal_expired) telah kedaluwarsa, status menampilkan 'EXPIRED'.
     */
    public function statusBadgeLabel(): string
    {
        if ($this->isExpired() && $this->status !== StatusLayanan::Berhenti && $this->status !== StatusLayanan::Proses) {
            return 'EXPIRED';
        }

        return $this->status->label();
    }

    /**
     * Dapatkan warna badge untuk status layanan.
     */
    public function statusBadgeColor(): string
    {
        if ($this->isExpired() && $this->status !== StatusLayanan::Berhenti && $this->status !== StatusLayanan::Proses) {
            return 'red';
        }

        return $this->status->color();
    }

    /**
     * Apakah layanan sedang aktif dan online.
     */
    public function isAktif(): bool
    {
        return $this->status === StatusLayanan::Aktif && ! $this->isExpired();
    }

    /**
     * Dapatkan periode tagihan target berikutnya (format YYYY-MM): bulan jatuh tempo (tanggal expired) siklus itu.
     */
    public function getNextPeriodeTagihan(): string
    {
        if (! $this->tanggal_expired) {
            return Carbon::today()->format('Y-m');
        }

        return Carbon::parse($this->tanggal_expired)->format('Y-m');
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
     * Suffix berupa 5-digit angka acak (10000-99999) via CSPRNG yang dijamin unik global.
     * Dilindungi retry guard (max 10 percobaan) untuk mencegah infinite loop.
     */
    public static function generatePppUsername(Pelanggan $pelanggan): string
    {
        return DB::transaction(function () use ($pelanggan) {
            $prefix = $pelanggan->no_reg;
            $attempts = 0;

            do {
                $suffix = (string) random_int(10000, 99999);
                $username = sprintf('%s_%s', $prefix, $suffix);

                $exists = static::withTrashed()
                    ->lockForUpdate()
                    ->where('ppp_username', $username)
                    ->exists();

                $attempts++;

                if ($attempts > 10) {
                    throw new \RuntimeException('Gagal menghasilkan ppp_username unik setelah 10 percobaan.');
                }
            } while ($exists);

            return $username;
        });
    }

    /**
     * Parse suffix 5-digit angka dari ppp_username berformat {no_reg}_{NNNNN}.
     *
     * Mengembalikan integer suffix, atau null jika format tidak sesuai.
     */
    public static function extractCounter(string $pppUsername): ?int
    {
        if (preg_match('/_([0-9]{5})$/', $pppUsername, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Dapatkan label nama site (nama_site atau fallback ke site_id).
     */
    public function getNamaSiteLabelAttribute(): string
    {
        return ! empty($this->nama_site) ? $this->nama_site : $this->site_id;
    }

    /**
     * Dapatkan harga dasar bulanan efektif layanan ini: `price_custom` jika `price_mode`
     * custom, jika tidak fallback ke harga PaketLayanan saat ini. Dipakai sebagai satu-satunya
     * sumber angka baik oleh tagihan pertama maupun tagihan bulanan berikutnya, sehingga
     * perubahan harga paket di kemudian hari tidak memengaruhi layanan yang sudah pakai
     * harga custom.
     */
    public function hargaDasar(): float
    {
        if ($this->price_mode === PriceMode::Custom && $this->price_custom !== null) {
            return (float) $this->price_custom;
        }

        return (float) $this->paketLayanan->harga;
    }

    /**
     * Dapatkan alamat pemasangan efektif (fallback ke alamat master pelanggan).
     */
    public function getAlamatEfektifAttribute(): string
    {
        if (! empty($this->alamat_pemasangan)) {
            return $this->alamat_pemasangan;
        }

        return $this->pelanggan?->alamat_lengkap ?? '';
    }

    /**
     * Dapatkan latitude efektif (fallback ke latitude master pelanggan).
     */
    public function getLatitudeEfektifAttribute(): ?float
    {
        return $this->latitude ?? $this->pelanggan?->latitude;
    }

    /**
     * Dapatkan longitude efektif (fallback ke longitude master pelanggan).
     */
    public function getLongitudeEfektifAttribute(): ?float
    {
        return $this->longitude ?? $this->pelanggan?->longitude;
    }
}
