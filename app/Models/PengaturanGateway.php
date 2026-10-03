<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'bebankan_ke_pelanggan' => true,
        'is_active' => true,
        'sandbox_mode' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['provider', 'nama', 'is_default', 'is_active', 'sandbox_mode', 'bebankan_ke_pelanggan'])
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
     * @return HasMany<ChannelPembayaran, $this>
     */
    public function channelPembayaran(): HasMany
    {
        return $this->hasMany(ChannelPembayaran::class);
    }

    /**
     * Dapatkan nilai credential tertentu.
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }
}
