<?php

namespace App\Models;

use App\Enums\GatewayChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $provider
 * @property string $nama
 * @property array<string, mixed>|null $credentials
 * @property string|null $gateway
 * @property float $fee_va_nominal
 * @property float $fee_va_persen
 * @property bool $fee_va_termasuk_ppn
 * @property bool $fee_qris_termasuk_ppn
 * @property float $biaya_pemrosesan
 * @property float $ppn_persen
 * @property float $fee_qris_persen
 * @property float $fee_qris_nominal
 * @property bool $bebankan_ke_pelanggan
 * @property bool $is_default
 * @property bool $is_active
 * @property bool $sandbox_mode
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'provider',
    'nama',
    'credentials',
    'gateway',
    'fee_va_nominal',
    'fee_qris_persen',
    'fee_qris_nominal',
    'fee_va_persen',
    'fee_va_termasuk_ppn',
    'fee_qris_termasuk_ppn',
    'biaya_pemrosesan',
    'ppn_persen',
    'bebankan_ke_pelanggan',
    'is_default',
    'is_active',
    'sandbox_mode',
    'keterangan',
])]
class PengaturanGateway extends Model
{
    use LogsActivity;

    protected $table = 'pengaturan_gateway';

    protected $attributes = [
        'fee_va_nominal' => 9000.00,
        'fee_va_persen' => 0.00,
        'fee_va_termasuk_ppn' => false,
        'fee_qris_persen' => 0.70,
        'fee_qris_nominal' => 0.00,
        'fee_qris_termasuk_ppn' => true,
        'biaya_pemrosesan' => 4000.00,
        'ppn_persen' => 11.00,
        'bebankan_ke_pelanggan' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['provider', 'nama', 'is_default', 'is_active', 'sandbox_mode', 'bebankan_ke_pelanggan', 'fee_va_nominal', 'fee_va_persen', 'fee_va_termasuk_ppn', 'fee_qris_nominal', 'fee_qris_persen', 'fee_qris_termasuk_ppn', 'biaya_pemrosesan', 'ppn_persen'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pengaturan_gateway');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'fee_va_nominal' => 'decimal:2',
            'fee_qris_persen' => 'decimal:2',
            'fee_qris_nominal' => 'decimal:2',
            'fee_va_persen' => 'decimal:2',
            'fee_va_termasuk_ppn' => 'boolean',
            'fee_qris_termasuk_ppn' => 'boolean',
            'biaya_pemrosesan' => 'decimal:2',
            'ppn_persen' => 'decimal:2',
            'bebankan_ke_pelanggan' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sandbox_mode' => 'boolean',
        ];
    }

    /**
     * @param  Builder<PengaturanGateway>  $query
     * @return Builder<PengaturanGateway>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<PengaturanGateway>  $query
     * @return Builder<PengaturanGateway>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Dapatkan koneksi default gateway yang aktif.
     */
    public static function getDefault(): ?self
    {
        /** @var self|null $default */
        $default = static::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($default) {
            return $default;
        }

        /** @var self|null $firstActive */
        $firstActive = static::query()->where('is_active', true)->first();
        if ($firstActive) {
            return $firstActive;
        }

        return static::query()->first();
    }

    /**
     * Jadikan koneksi gateway ini sebagai default tunggal.
     */
    public function setAsDefault(): void
    {
        DB::transaction(function () {
            static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);
            $this->update(['is_default' => true, 'is_active' => true]);
        });
    }

    /**
     * Ambil koneksi untuk provider tertentu, mengutamakan yang aktif lalu yang default.
     * Satu provider boleh punya beberapa koneksi (mis. live dan sandbox) -- mengambil baris
     * pertama tanpa urutan membuat token/API key koneksi yang salah dipakai (ADR-0067).
     */
    public static function getSettingForProvider(string $provider): ?self
    {
        $provider = strtolower($provider);

        return static::query()
            ->where(fn ($query) => $query->where('provider', $provider)->orWhere('gateway', $provider))
            ->orderByDesc('is_active')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * Ambil pengaturan Xendit (kompatibilitas mundur).
     */
    public static function getXenditSetting(): self
    {
        /** @var self $setting */
        $setting = static::firstOrCreate(
            ['provider' => 'xendit'],
            [
                'nama' => 'Xendit Gateway',
                'gateway' => 'xendit',
                'bebankan_ke_pelanggan' => true,
                'is_default' => true,
                'is_active' => true,
                'sandbox_mode' => config('services.xendit.env') !== 'production',
            ]
        );

        return $setting;
    }

    /**
     * Biaya Admin Gateway untuk satu metode bayar: tarif metode + biaya pemrosesan, masing-masing
     * ditambah PPN bila tarifnya belum termasuk PPN, dibulatkan ke atas ke rupiah penuh (ADR-0072).
     */
    public function hitungFee(GatewayChannel $metode, float $nominalInvoice): int
    {
        if (! $this->bebankan_ke_pelanggan) {
            return 0;
        }

        [$nominal, $persen, $termasukPpn] = match ($metode) {
            GatewayChannel::VirtualAccount => [$this->fee_va_nominal, $this->fee_va_persen, $this->fee_va_termasuk_ppn],
            GatewayChannel::Qris => [$this->fee_qris_nominal, $this->fee_qris_persen, $this->fee_qris_termasuk_ppn],
            default => throw new InvalidArgumentException("Metode bayar {$metode->value} tidak punya tarif."),
        };

        $faktorPpn = 1 + (float) $this->ppn_persen / 100;
        $biayaMetode = (float) $nominal + $nominalInvoice * (float) $persen / 100;
        $fee = ($termasukPpn ? $biayaMetode : $biayaMetode * $faktorPpn) + (float) $this->biaya_pemrosesan * $faktorPpn;

        // Galat float (14430.000000000002) dibuang dengan round 6 desimal sebelum ceil, tanpa membuang
        // pecahan rupiah sungguhan seperti 5141.001.
        return (int) ceil(round($fee, 6));
    }

    /**
     * Dapatkan nilai credential tertentu.
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }
}
