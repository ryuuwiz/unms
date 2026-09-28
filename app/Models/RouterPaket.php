<?php

namespace App\Models;

use App\Enums\StatusLayanan;
use Database\Factories\RouterPaketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Router Paket (ADR-0063): router yang boleh menjual sebuah paket dan IP Pool profile PPP-nya.
 *
 * @property int $id
 * @property int $paket_layanan_id
 * @property int $router_id
 * @property int $ip_pool_id
 * @property string|null $deskripsi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PaketLayanan $paketLayanan
 * @property-read Router $router
 * @property-read IpPool $ipPool
 */
#[Fillable(['paket_layanan_id', 'router_id', 'ip_pool_id', 'deskripsi'])]
class RouterPaket extends Model
{
    /** @use HasFactory<RouterPaketFactory> */
    use HasFactory;

    protected $table = 'router_paket';

    public const PESAN_BELUM_TERDAFTAR = 'Paket ini belum didaftarkan ke router tersebut. Tambahkan router di Detail Paket.';

    public static function terdaftar(mixed $routerId, mixed $paketLayananId): bool
    {
        return static::query()->where('router_id', $routerId)->where('paket_layanan_id', $paketLayananId)->exists();
    }

    /**
     * @return BelongsTo<PaketLayanan, $this>
     */
    public function paketLayanan(): BelongsTo
    {
        return $this->belongsTo(PaketLayanan::class, 'paket_layanan_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id');
    }

    /**
     * @return BelongsTo<IpPool, $this>
     */
    public function ipPool(): BelongsTo
    {
        return $this->belongsTo(IpPool::class, 'ip_pool_id');
    }

    /**
     * Nama PPP Profile di router = nama paket.
     */
    public function namaProfile(): string
    {
        return trim((string) $this->paketLayanan->nama_paket);
    }

    /**
     * Masih ada layanan (selain Berhenti) memakai paket ini di router ini -- Router Paket tidak boleh dihapus.
     */
    public function masihDipakai(): bool
    {
        return LayananPelanggan::query()
            ->where('paket_layanan_id', $this->paket_layanan_id)
            ->where('router_id', $this->router_id)
            ->where('status', '!=', StatusLayanan::Berhenti)
            ->exists();
    }
}
