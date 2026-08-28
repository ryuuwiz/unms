<?php

namespace App\Models;

use App\Enums\Sysblas\SysblasProvider;
use App\Services\Wablas\WablasClient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $nama
 * @property SysblasProvider $provider
 * @property string|null $nomor
 * @property string $url_api
 * @property string $api_token
 * @property string|null $api_secret
 * @property int $limit_per_menit
 * @property bool $is_default
 * @property bool $is_aktif
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nama',
    'provider',
    'nomor',
    'url_api',
    'api_token',
    'api_secret',
    'limit_per_menit',
    'is_default',
    'is_aktif',
    'keterangan',
])]
class Sysblas extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'sysblas';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('sysblas');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SysblasProvider::class,
            'limit_per_menit' => 'integer',
            'is_default' => 'boolean',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AntrianWaBlast, $this>
     */
    public function antrian(): HasMany
    {
        return $this->hasMany(AntrianWaBlast::class, 'sysblas_id');
    }

    /**
     * @param  Builder<Sysblas>  $query
     * @return Builder<Sysblas>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }

    /**
     * @param  Builder<Sysblas>  $query
     * @return Builder<Sysblas>
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
            ->where('is_aktif', true)
            ->first();

        if ($default) {
            return $default;
        }

        /** @var self|null $firstActive */
        $firstActive = static::query()->where('is_aktif', true)->first();
        if ($firstActive) {
            return $firstActive;
        }

        return static::query()->first();
    }

    /**
     * Jadikan koneksi ini sebagai default tunggal.
     */
    public function setAsDefault(): void
    {
        DB::transaction(function () {
            static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);
            $this->update(['is_default' => true, 'is_aktif' => true]);
        });
    }

    /**
     * Instansiasi HTTP API Client untuk koneksi ini.
     */
    public function makeClient(): WablasClient
    {
        return new WablasClient(
            host: $this->url_api ?: (string) config('services.wablas.host', 'https://tegal.wablas.com'),
            number: $this->nomor ?: (string) config('services.wablas.number', ''),
            token: $this->api_token ?: (string) config('services.wablas.token', ''),
            secret: $this->api_secret ?: (string) config('services.wablas.secret', '')
        );
    }
}
