<?php

namespace App\Models;

use App\Enums\Barang\StatusUnitBarang;
use Database\Factories\UnitBarangFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Satu fisik barang yang dilacak -- lihat CONTEXT.md "Unit Barang" / "Status Unit Barang".
 *
 * @property int $id
 * @property int $jenis_barang_id
 * @property string $kode
 * @property int $kondisi_barang_id
 * @property int|null $prefix_registrasi_id
 * @property StatusUnitBarang $status
 * @property string|null $serial_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JenisBarang $jenisBarang
 * @property-read KondisiBarang $kondisi
 */
#[Fillable(['jenis_barang_id', 'kode', 'kondisi_barang_id', 'prefix_registrasi_id', 'status', 'serial_number'])]
class UnitBarang extends Model
{
    /** @use HasFactory<UnitBarangFactory> */
    use HasFactory;

    protected $table = 'unit_barang';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => StatusUnitBarang::class];
    }

    /**
     * @return BelongsTo<JenisBarang, $this>
     */
    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarang::class, 'jenis_barang_id');
    }

    /**
     * @return BelongsTo<KondisiBarang, $this>
     */
    public function kondisi(): BelongsTo
    {
        return $this->belongsTo(KondisiBarang::class, 'kondisi_barang_id');
    }

    /**
     * @return BelongsToMany<MutasiBarang, $this>
     */
    public function mutasi(): BelongsToMany
    {
        return $this->belongsToMany(MutasiBarang::class, 'mutasi_barang_unit', 'unit_barang_id', 'mutasi_barang_id');
    }
}
