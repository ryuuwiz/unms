<?php

namespace App\Models;

use App\Enums\MasaAktifSatuan;
use App\Enums\StatusPaket;
use Database\Factories\PaketLayananFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $nama_paket
 * @property int $profil_bandwidth_id
 * @property float $harga
 * @property int $masa_aktif_nilai
 * @property MasaAktifSatuan $masa_aktif_satuan
 * @property string|null $keterangan
 * @property StatusPaket $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProfilBandwidth $profilBandwidth
 */
#[Fillable([
    'nama_paket',
    'profil_bandwidth_id',
    'harga',
    'masa_aktif_nilai',
    'masa_aktif_satuan',
    'keterangan',
    'status',
])]
class PaketLayanan extends Model
{
    /** @use HasFactory<PaketLayananFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'paket_layanan';

    /**
     * Konfigurasi logging aktivitas.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_paket', 'harga', 'status', 'masa_aktif_nilai', 'masa_aktif_satuan'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('paket_layanan');
    }

    /**
     * Cast atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPaket::class,
            'masa_aktif_satuan' => MasaAktifSatuan::class,
            'harga' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke profil bandwidth yang digunakan paket ini.
     *
     * @return BelongsTo<ProfilBandwidth, $this>
     */
    public function profilBandwidth(): BelongsTo
    {
        return $this->belongsTo(ProfilBandwidth::class, 'profil_bandwidth_id');
    }

    /**
     * Relasi ke semua layanan pelanggan yang menggunakan paket ini.
     *
     * @return HasMany<LayananPelanggan, $this>
     */
    public function layanans(): HasMany
    {
        return $this->hasMany(LayananPelanggan::class, 'paket_layanan_id');
    }

    /**
     * Scope filter paket yang berstatus aktif.
     *
     * @param  Builder<PaketLayanan>  $query
     * @return Builder<PaketLayanan>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StatusPaket::Aktif);
    }

    /**
     * Scope pencarian berdasarkan nama paket atau keterangan.
     *
     * @param  Builder<PaketLayanan>  $query
     * @return Builder<PaketLayanan>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('nama_paket', 'like', "%{$term}%")
                ->orWhere('keterangan', 'like', "%{$term}%");
        });
    }

    /**
     * Format harga dalam Rupiah (misal: "Rp 250.000 / bln").
     */
    public function formattedHarga(): string
    {
        return 'Rp '.number_format((float) $this->harga, 0, ',', '.').' / '.$this->masa_aktif_satuan->label();
    }

    /**
     * Label masa aktif (misal: "30 Hari" atau "1 Bulan").
     */
    public function labelMasaAktif(): string
    {
        return "{$this->masa_aktif_nilai} {$this->masa_aktif_satuan->label()}";
    }
}
