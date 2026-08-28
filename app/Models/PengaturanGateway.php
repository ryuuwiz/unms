<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $provider
 * @property string $nama
 * @property array<string, mixed>|null $credentials
 * @property string|null $gateway
 * @property float $fee_va_nominal
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
    'bebankan_ke_pelanggan',
    'is_default',
    'is_active',
    'sandbox_mode',
    'keterangan',
])]
class PengaturanGateway extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pengaturan_gateway';

    protected $attributes = [
        'fee_va_nominal' => 4000.00,
        'fee_qris_persen' => 0.70,
        'fee_qris_nominal' => 0.00,
        'bebankan_ke_pelanggan' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['provider', 'nama', 'is_default', 'is_active', 'sandbox_mode', 'bebankan_ke_pelanggan', 'fee_va_nominal', 'fee_qris_persen'])
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
     * Ambil atau buat pengaturan untuk provider tertentu.
     */
    public static function getSettingForProvider(string $provider): ?self
    {
        return static::query()
            ->where('provider', strtolower($provider))
            ->orWhere('gateway', strtolower($provider))
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
                'fee_va_nominal' => 4000.00,
                'fee_qris_persen' => 0.70,
                'fee_qris_nominal' => 0.00,
                'bebankan_ke_pelanggan' => true,
                'is_default' => true,
                'is_active' => true,
                'sandbox_mode' => env('XENDIT_ENV', 'development') !== 'production',
            ]
        );

        return $setting;
    }

    /**
     * Hitung total fee gateway berdasarkan channel dan nominal tagihan.
     */
    public function hitungFee(string $channel, float $nominalInvoice): float
    {
        if (! $this->bebankan_ke_pelanggan) {
            return 0.0;
        }

        if ($channel === 'virtual_account') {
            return (float) $this->fee_va_nominal;
        }

        if ($channel === 'qris') {
            $feePersen = ($nominalInvoice * ((float) $this->fee_qris_persen / 100));

            return round($feePersen + (float) $this->fee_qris_nominal, 2);
        }

        return 0.0;
    }

    /**
     * Dapatkan nilai credential tertentu.
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }
}
