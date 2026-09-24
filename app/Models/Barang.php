<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $kode_barang
 * @property int $jenis_barang_id
 * @property int $kondisi_barang_id
 * @property int $cabang_barang_id
 * @property string $nama_barang
 * @property string $satuan
 * @property int $stok
 * @property bool $is_active
 * @property string|null $keterangan
 * @property int|null $dibuat_oleh
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PengaturanJenisBarang $jenisBarang
 * @property-read PengaturanKondisiBarang $kondisiBarang
 * @property-read PengaturanCabangBarang $cabangBarang
 */
#[Fillable([
    'kode_barang',
    'jenis_barang_id',
    'kondisi_barang_id',
    'cabang_barang_id',
    'nama_barang',
    'satuan',
    'stok',
    'is_active',
    'keterangan',
    'dibuat_oleh',
])]
class Barang extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'barang';

    protected $attributes = [
        'satuan' => 'unit',
        'stok' => 0,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Barang $barang) {
            if (empty($barang->kode_barang)) {
                $jenis = PengaturanJenisBarang::findOrFail($barang->jenis_barang_id);
                $kondisi = PengaturanKondisiBarang::findOrFail($barang->kondisi_barang_id);
                $cabang = PengaturanCabangBarang::findOrFail($barang->cabang_barang_id);

                $barang->kode_barang = static::generateKodeBarang($jenis->kode, $kondisi->kode, $cabang->kode);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['kode_barang', 'nama_barang', 'stok', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('barang');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PengaturanJenisBarang, $this>
     */
    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(PengaturanJenisBarang::class, 'jenis_barang_id');
    }

    /**
     * @return BelongsTo<PengaturanKondisiBarang, $this>
     */
    public function kondisiBarang(): BelongsTo
    {
        return $this->belongsTo(PengaturanKondisiBarang::class, 'kondisi_barang_id');
    }

    /**
     * @return BelongsTo<PengaturanCabangBarang, $this>
     */
    public function cabangBarang(): BelongsTo
    {
        return $this->belongsTo(PengaturanCabangBarang::class, 'cabang_barang_id');
    }

    /**
     * @return HasMany<BarangMasuk, $this>
     */
    public function barangMasuks(): HasMany
    {
        return $this->hasMany(BarangMasuk::class, 'barang_id');
    }

    /**
     * @return HasMany<BarangKeluar, $this>
     */
    public function barangKeluars(): HasMany
    {
        return $this->hasMany(BarangKeluar::class, 'barang_id');
    }

    /**
     * @param  Builder<Barang>  $query
     * @return Builder<Barang>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('nama_barang', 'like', "%{$term}%")
                ->orWhere('kode_barang', 'like', "%{$term}%");
        });
    }

    /**
     * Render barcode Code128 dari kode_barang sebagai markup SVG siap-embed (dipakai untuk cetak label).
     */
    public function barcodeSvg(): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode($this->kode_barang, $generator::TYPE_CODE_128);
    }

    /**
     * Generate kode_barang unik dengan format [Jenis]-[Kondisi]-[Cabang]-[Counter 3-Digit]
     * (contoh: MDM-NEW-BF-240). Counter berjalan per kombinasi jenis+kondisi+cabang.
     */
    public static function generateKodeBarang(string $jenisKode, string $kondisiKode, string $cabangKode): string
    {
        $jenisKode = strtoupper(trim($jenisKode));
        $kondisiKode = strtoupper(trim($kondisiKode));
        $cabangKode = strtoupper(trim($cabangKode));

        return DB::transaction(function () use ($jenisKode, $kondisiKode, $cabangKode) {
            $prefix = "{$jenisKode}-{$kondisiKode}-{$cabangKode}-";
            $pattern = $prefix.'%';

            $latest = static::withTrashed()
                ->lockForUpdate()
                ->where('kode_barang', 'like', $pattern)
                ->orderBy('kode_barang', 'desc')
                ->first();

            $nextNumber = 1;

            if ($latest && preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $latest->kode_barang, $matches)) {
                $nextNumber = ((int) $matches[1]) + 1;
            }

            return sprintf('%s%03d', $prefix, $nextNumber);
        });
    }
}
