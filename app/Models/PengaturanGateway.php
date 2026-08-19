<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $gateway
 * @property float $fee_va_nominal
 * @property float $fee_qris_persen
 * @property float $fee_qris_nominal
 * @property bool $bebankan_ke_pelanggan
 * @property bool $is_active
 * @property bool $sandbox_mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'gateway',
    'fee_va_nominal',
    'fee_qris_persen',
    'fee_qris_nominal',
    'bebankan_ke_pelanggan',
    'is_active',
    'sandbox_mode',
])]
class PengaturanGateway extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'pengaturan_gateway';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
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
            'fee_va_nominal' => 'decimal:2',
            'fee_qris_persen' => 'decimal:2',
            'fee_qris_nominal' => 'decimal:2',
            'bebankan_ke_pelanggan' => 'boolean',
            'is_active' => 'boolean',
            'sandbox_mode' => 'boolean',
        ];
    }

    /**
     * Ambil singleton konfigurasi Xendit.
     */
    public static function getXenditSetting(): self
    {
        return static::firstOrCreate(
            ['gateway' => 'xendit'],
            [
                'fee_va_nominal' => 0,
                'fee_qris_persen' => 0,
                'fee_qris_nominal' => 0,
                'bebankan_ke_pelanggan' => false,
                'is_active' => true,
                'sandbox_mode' => env('XENDIT_ENV', 'development') !== 'production',
            ]
        );
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
}
