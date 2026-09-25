<?php

namespace App\Models;

use App\Enums\StatusOdpPort;
use Database\Factories\OdpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $nama_odp
 * @property int|null $perumahan_id
 * @property int $kapasitas_port
 * @property string|null $keterangan
 * @property float|null $latitude
 * @property float|null $longitude
 * @property-read Perumahan|null $perumahan
 * @property-read Collection<int, OdpPort> $ports
 * @property-read int $port_kosong_count Hanya terisi bila diambil dengan withCount(['ports as port_kosong_count' => ...])
 * @property-read int $port_terpakai_count Idem, alias withCount status terpakai
 * @property-read int $port_rusak_count Idem, alias withCount status rusak
 * @property-read float $jarak Hanya terisi bila diambil dengan selectRaw jarak (Haversine)
 */
#[Fillable(['nama_odp', 'perumahan_id', 'kapasitas_port', 'keterangan', 'latitude', 'longitude'])]
class Odp extends Model
{
    /** @use HasFactory<OdpFactory> */
    use HasFactory;

    protected $table = 'odp';

    /** Toleransi jarak ODP ke pelanggan saat mencari ODP terdekat -- lihat CONTEXT.md "ODP Terdekat". */
    public const RADIUS_PELANGGAN_METER = 150;

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kapasitas_port' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Relasi ke perumahan tempat ODP ini berada.
     *
     * @return BelongsTo<Perumahan, $this>
     */
    public function perumahan(): BelongsTo
    {
        return $this->belongsTo(Perumahan::class, 'perumahan_id');
    }

    /**
     * Relasi ke semua port di ODP ini.
     *
     * @return HasMany<OdpPort, $this>
     */
    public function ports(): HasMany
    {
        return $this->hasMany(OdpPort::class, 'odp_id');
    }

    /**
     * ODP yang punya port dipakai layanan: berstatus Terpakai atau dirujuk `odp_port_id` layanan mana
     * pun (termasuk yang soft-deleted, agar pemulihannya tidak kehilangan port). ODP seperti ini tidak
     * boleh dihapus -- lihat CONTEXT.md "Penghapusan ODP".
     *
     * @param  Builder<Odp>  $query
     * @return Builder<Odp>
     */
    public function scopePortDipakai(Builder $query): Builder
    {
        return $query->whereHas('ports', fn (Builder $port) => $port
            ->where('status', StatusOdpPort::Terpakai)
            ->orWhereExists(fn ($layanan) => $layanan->selectRaw('1')
                ->from('layanan_pelanggan')
                ->whereColumn('layanan_pelanggan.odp_port_id', 'odp_port.id')));
    }

    /**
     * Jumlah port yang masih kosong / tersedia.
     */
    public function portTersedia(): int
    {
        return $this->ports()->where('status', StatusOdpPort::Kosong)->count();
    }

    /**
     * Jumlah port yang sudah terpakai.
     */
    public function portTerpakai(): int
    {
        return $this->ports()->where('status', StatusOdpPort::Terpakai)->count();
    }

    /**
     * Generate baris port default 1..N saat ODP baru dibuat.
     */
    public function generateDefaultPorts(?int $kapasitas = null): void
    {
        $targetKapasitas = $kapasitas ?? $this->kapasitas_port;
        if ($targetKapasitas <= 0) {
            return;
        }

        DB::transaction(function () use ($targetKapasitas) {
            $ports = [];
            for ($i = 1; $i <= $targetKapasitas; $i++) {
                $ports[] = [
                    'odp_id' => $this->id,
                    'nomor_port' => $i,
                    'status' => StatusOdpPort::Kosong->value,
                    'layanan_pelanggan_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            OdpPort::insert($ports);
        });
    }

    /**
     * Sesuaikan kapasitas port ODP (tambah jika naik, validasi jika turun).
     */
    public function adjustPortCapacity(int $newCapacity): bool
    {
        if ($newCapacity <= 0) {
            return false;
        }

        $currentMax = $this->ports()->max('nomor_port') ?? 0;

        if ($newCapacity > $currentMax) {
            // Tambahkan port baru
            $portsToAdd = [];
            for ($i = $currentMax + 1; $i <= $newCapacity; $i++) {
                $portsToAdd[] = [
                    'odp_id' => $this->id,
                    'nomor_port' => $i,
                    'status' => StatusOdpPort::Kosong->value,
                    'layanan_pelanggan_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            OdpPort::insert($portsToAdd);
            $this->update(['kapasitas_port' => $newCapacity]);

            return true;
        }

        if ($newCapacity < $currentMax) {
            // Cek apakah ada port nomor > newCapacity yang berstatus terpakai
            $usedHighPorts = $this->ports()
                ->where('nomor_port', '>', $newCapacity)
                ->where('status', '!=', StatusOdpPort::Kosong->value)
                ->exists();

            if ($usedHighPorts) {
                return false;
            }

            // Hapus port kosong berlebih
            $this->ports()->where('nomor_port', '>', $newCapacity)->delete();
            $this->update(['kapasitas_port' => $newCapacity]);

            return true;
        }

        return true;
    }

    /**
     * Cek apakah ODP aman untuk dihapus (tidak memiliki port terpakai).
     */
    public function canBeDeleted(): bool
    {
        return ! $this->ports()->where('status', StatusOdpPort::Terpakai->value)->exists();
    }

    /**
     * Scope filter pencarian berdasarkan nama ODP, keterangan, atau nama perumahan.
     *
     * @param  Builder<Odp>  $query
     * @return Builder<Odp>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $clean = trim($term);

        return $query->where(function (Builder $q) use ($clean) {
            $q->where('nama_odp', 'like', "%{$clean}%")
                ->orWhere('keterangan', 'like', "%{$clean}%")
                ->orWhereHas('perumahan', fn (Builder $sub) => $sub->where('nama_perumahan', 'like', "%{$clean}%"));
        });
    }

    /**
     * Scope untuk mencari ODP terdekat berdasarkan koordinat (latitude, longitude).
     * Memanfaatkan fungsi spasial ST_Distance_Sphere bawaan MySQL.
     *
     * @param  Builder<Odp>  $query
     * @param  int|null  $radius  Maksimal jarak dalam meter (opsional)
     * @param  string|null  $search  Filter nama/PON ODP
     * @return Builder<Odp>
     */
    public function scopeTerdekat(Builder $query, float $latitude, float $longitude, ?int $radius = null, ?string $search = null): Builder
    {
        $q = $query->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (filled($search)) {
            $cleanSearch = trim($search);
            $q->where(function (Builder $sub) use ($cleanSearch) {
                $sub->where('nama_odp', 'like', "%{$cleanSearch}%")
                    ->orWhere('keterangan', 'like', "%{$cleanSearch}%");
            });
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // Fallback komputasi jarak meter untuk environment SQLite (Testing)
            $q->select('*')
                ->selectRaw('((abs(latitude - ?) * 111320) + (abs(longitude - ?) * 111320)) as jarak', [$latitude, $longitude]);
        } else {
            // Native MySQL 8 Spatial Function (Production)
            $q->select('*')
                ->selectRaw('ST_Distance_Sphere(point(longitude, latitude), point(?, ?)) as jarak', [$longitude, $latitude]);
        }

        if ($radius !== null && $radius > 0) {
            $q->having('jarak', '<=', $radius);
        }

        return $q->orderBy('jarak', 'asc');
    }
}
